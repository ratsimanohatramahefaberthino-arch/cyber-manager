<?php

namespace App\Http\Controllers;

use App\Models\Tarif;
use App\Models\TarifRaccourci;
use App\Services\TarifService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TarifController extends Controller
{
    public function __construct(private TarifService $tarifService) {}

    // -----------------------------------------------------------------
    // Page principale
    // -----------------------------------------------------------------

    public function index()
    {
        $mode = $this->tarifService->mode();

        $unifie   = $mode === 'unifie'   ? $this->tarifService->tarifUnifie() : null;
        $ethernet = $mode === 'separe'   ? $this->tarifService->tarifEthernet() : null;
        $wifi     = $mode === 'separe'   ? $this->tarifService->tarifWifi() : null;

        // S'assurer qu'on a au moins une config, sinon on la crée
        if (!$unifie && !$ethernet && !$wifi) {
            $unifie = Tarif::create([
                'cible'              => Tarif::CIBLE_UNIFIE,
                'montant_par_minute' => 20,
                'montant_minimum'    => 300,
                'arrondi_actif'      => true,
                'unite_arrondi'      => 100,
                'seuil_arrondi'      => 50,
            ]);
            $mode = 'unifie';
        }

        return view('tarifs.index', compact('mode', 'unifie', 'ethernet', 'wifi'));
    }

    // -----------------------------------------------------------------
    // Modification d'un tarif existant
    // -----------------------------------------------------------------

    public function update(Request $request, Tarif $tarif): RedirectResponse
    {
        $data = $request->validate([
            'montant_par_minute' => ['required', 'integer', 'min:1', 'max:100000'],
            'montant_minimum'    => ['required', 'integer', 'min:0', 'max:1000000'],
            'arrondi_actif'      => ['nullable', 'boolean'],
            'unite_arrondi'      => ['nullable', 'integer', 'min:1', 'max:10000'],
            'seuil_arrondi'      => ['nullable', 'integer', 'min:0', 'max:10000'],
            'description'        => ['nullable', 'string', 'max:500'],
        ]);

        $tarif->update([
            'montant_par_minute' => $data['montant_par_minute'],
            'montant_minimum'    => $data['montant_minimum'],
            'arrondi_actif'      => $request->boolean('arrondi_actif'),
            'unite_arrondi'      => (int) ($data['unite_arrondi'] ?? 100),
            'seuil_arrondi'      => (int) ($data['seuil_arrondi'] ?? 50),
            'description'        => $data['description'] ?? null,
        ]);

        // Recalcule les durées des raccourcis selon le nouveau prix/minute
        $this->tarifService->resynchroniserRaccourcis($tarif->fresh());

        return redirect()
            ->route('tarifs.index')
            ->with('success', "Tarif « {$tarif->libelle()} » mis à jour. Les raccourcis ont été recalculés.");
    }

    // -----------------------------------------------------------------
    // Passage unifié ⇄ séparé
    // -----------------------------------------------------------------

    public function passerSepare(Request $request): RedirectResponse
    {
        $request->validate([
            'confirmation' => ['required', Rule::in(['SEPARER'])],
        ], [
            'confirmation.in' => 'Tapez exactement « SEPARER » pour confirmer.',
        ]);

        $unifie = $this->tarifService->tarifUnifie();

        if (!$unifie) {
            return back()->with('error', 'Le mode est déjà séparé.');
        }

        DB::transaction(function () use ($unifie) {
            // On duplique la config unifiée en deux lignes distinctes
            Tarif::create([
                'cible'              => Tarif::CIBLE_ETHERNET,
                'montant_par_minute' => $unifie->montant_par_minute,
                'montant_minimum'    => $unifie->montant_minimum,
                'arrondi_actif'      => $unifie->arrondi_actif,
                'unite_arrondi'      => $unifie->unite_arrondi,
                'seuil_arrondi'      => $unifie->seuil_arrondi,
                'description'        => $unifie->description,
            ]);

            Tarif::create([
                'cible'              => Tarif::CIBLE_WIFI,
                'montant_par_minute' => $unifie->montant_par_minute,
                'montant_minimum'    => $unifie->montant_minimum,
                'arrondi_actif'      => $unifie->arrondi_actif,
                'unite_arrondi'      => $unifie->unite_arrondi,
                'seuil_arrondi'      => $unifie->seuil_arrondi,
                'description'        => $unifie->description,
            ]);

            // Raccourcis du tarif unifié → dupliqués aussi
            foreach ($unifie->raccourcis as $r) {
                Tarif::where('cible', Tarif::CIBLE_ETHERNET)->first()->raccourcis()->create([
                    'montant' => $r->montant, 'duree' => $r->duree,
                    'libelle' => $r->libelle, 'ordre' => $r->ordre,
                ]);
                Tarif::where('cible', Tarif::CIBLE_WIFI)->first()->raccourcis()->create([
                    'montant' => $r->montant, 'duree' => $r->duree,
                    'libelle' => $r->libelle, 'ordre' => $r->ordre,
                ]);
            }

            $unifie->delete();
        });

        return redirect()
            ->route('tarifs.index')
            ->with('success', 'Mode séparé activé : configurez maintenant Ethernet et Wi-Fi indépendamment.');
    }

    public function passerUnifie(Request $request): RedirectResponse
    {
        $request->validate([
            'confirmation' => ['required', Rule::in(['UNIFIER'])],
            'base'         => ['required', Rule::in(['ethernet', 'wifi'])],
        ], [
            'confirmation.in' => 'Tapez exactement « UNIFIER » pour confirmer.',
        ]);

        $base = $request->input('base'); // laquelle garder comme source
        $source = $base === 'wifi'
            ? $this->tarifService->tarifWifi()
            : $this->tarifService->tarifEthernet();

        if (!$source) {
            return back()->with('error', "Le tarif « {$base} » n'existe pas.");
        }

        DB::transaction(function () use ($source) {
            // Supprimer toutes les lignes existantes
            TarifRaccourci::query()->delete();
            Tarif::query()->delete();

            $nouveau = Tarif::create([
                'cible'              => Tarif::CIBLE_UNIFIE,
                'montant_par_minute' => $source->montant_par_minute,
                'montant_minimum'    => $source->montant_minimum,
                'arrondi_actif'      => $source->arrondi_actif,
                'unite_arrondi'      => $source->unite_arrondi,
                'seuil_arrondi'      => $source->seuil_arrondi,
                'description'        => $source->description,
            ]);

            foreach ($source->raccourcis as $r) {
                $nouveau->raccourcis()->create([
                    'montant' => $r->montant, 'duree' => $r->duree,
                    'libelle' => $r->libelle, 'ordre' => $r->ordre,
                ]);
            }
        });

        return redirect()
            ->route('tarifs.index')
            ->with('success', 'Mode unifié activé : un seul tarif pour Ethernet et Wi-Fi.');
    }

    // -----------------------------------------------------------------
    // Réinitialisation (avec confirmation textuelle)
    // -----------------------------------------------------------------

    public function reinitialiser(Request $request): RedirectResponse
    {
        $request->validate([
            'confirmation' => ['required', Rule::in(['REINITIALISER'])],
        ], [
            'confirmation.in' => 'Tapez exactement « REINITIALISER » pour confirmer.',
        ]);

        DB::transaction(function () {
            TarifRaccourci::query()->delete();
            Tarif::query()->delete();

            $t = Tarif::create([
                'cible'              => Tarif::CIBLE_UNIFIE,
                'montant_par_minute' => 20,
                'montant_minimum'    => 300,
                'arrondi_actif'      => true,
                'unite_arrondi'      => 100,
                'seuil_arrondi'      => 50,
                'description'        => 'Tarif par défaut (20 Ar/min, minimum 300 Ar).',
            ]);

            $t->raccourcis()->createMany([
                ['montant' => 300,  'duree' => 15, 'libelle' => '15 min',  'ordre' => 10],
                ['montant' => 500,  'duree' => 25, 'libelle' => '25 min',  'ordre' => 20],
                ['montant' => 600,  'duree' => 30, 'libelle' => '30 min',  'ordre' => 30],
                ['montant' => 1000, 'duree' => 50, 'libelle' => '50 min',  'ordre' => 40],
            ]);
        });

        return redirect()
            ->route('tarifs.index')
            ->with('success', 'Tarifs réinitialisés aux valeurs par défaut.');
    }

    // -----------------------------------------------------------------
    // Raccourcis
    // -----------------------------------------------------------------

    public function storeRaccourci(Request $request, Tarif $tarif): RedirectResponse
    {
        $data = $request->validate([
            'mode'    => ['nullable', Rule::in(['montant', 'duree'])],
            'montant' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'h'       => ['nullable', 'integer', 'min:0', 'max:999'],
            'm'       => ['nullable', 'integer', 'min:0', 'max:59'],
            's'       => ['nullable', 'integer', 'min:0', 'max:59'],
            'libelle' => ['nullable', 'string', 'max:100'],
        ]);

        $data['mode'] = $data['mode'] ?? 'montant';

        if ($data['mode'] === 'montant') {
            if (empty($data['montant'])) {
                return back()->withErrors(['montant' => 'Indiquez un montant.']);
            }

            $montant = (int) $data['montant'];
            $duree   = $this->tarifService->calculerDuree($montant, $tarif);
        } else {
            $h = (int) ($data['h'] ?? 0);
            $m = (int) ($data['m'] ?? 0);
            $s = (int) ($data['s'] ?? 0);
            $minutes = $h * 60 + $m + ($s > 0 ? 1 : 0); // arrondi à la minute sup.

            if ($minutes < 1) {
                return back()->withErrors(['h' => 'Indiquez une durée supérieure à 0.']);
            }

            $montant = $this->tarifService->montantPourMinutes($minutes, $tarif);
            $duree   = $minutes;
        }

        if ($tarif->raccourcis()->where('montant', $montant)->exists()) {
            return back()
                ->withInput()
                ->withErrors([
                    "raccourci_tarif_{$tarif->id}" => "Un raccourci à " . number_format($montant, 0, ',', ' ') . " Ar existe déjà.",
                ]);
        }

        $tarif->raccourcis()->create([
            'montant' => $montant,
            'duree'   => $duree,
            'libelle' => $data['libelle'] ?? null,
            'ordre'   => ($tarif->raccourcis()->max('ordre') ?? 0) + 10,
        ]);

        return redirect()
            ->route('tarifs.index')
            ->with('success', "Raccourci ajouté : {$montant} Ar → {$duree} min.");
    }

    public function destroyRaccourci(Tarif $tarif, TarifRaccourci $raccourci): RedirectResponse
    {
        if ($raccourci->tarif_id !== $tarif->id) {
            abort(404);
        }

        $raccourci->delete();

        return redirect()
            ->route('tarifs.index')
            ->with('success', 'Raccourci supprimé.');
    }

    // -----------------------------------------------------------------
    // Simulateur
    // -----------------------------------------------------------------

    public function simuler(Request $request): JsonResponse
    {
        $data = $request->validate([
            'cible'  => ['required', Rule::in(['unifie', 'ethernet', 'wifi'])],
            'mode'   => ['required', Rule::in(['montant', 'duree'])],
            'entree' => ['required', 'integer', 'min:0', 'max:10000000'],
        ]);

        $tarif = $this->tarifService->tarifPour($data['cible']);

        return response()->json(
            $this->tarifService->simuler((int) $data['entree'], $tarif, $data['mode'])
        );
    }
}