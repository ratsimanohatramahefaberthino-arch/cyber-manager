<?php

namespace App\Http\Controllers;

use App\Models\AppareilWifi;
use App\Models\LotVoucher;
use App\Models\Parametre;
use App\Models\Voucher;
use App\Services\MikroTikService;
use App\Services\VoucherService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HotspotController extends Controller
{
    private const PAR_PAGE = 30;

    public function __construct(
        private VoucherService $voucherService,
        private MikroTikService $mikroTik,
    ) {
    }

    public function index(Request $request)
    {
        $filtres  = $this->filtresDepuis($request);
        $vouchers = $this->requeteBase($filtres)->paginate(self::PAR_PAGE)->withQueryString();

        return view('hotspot.index', [
            'vouchers'  => $vouchers,
            'lignes'    => $vouchers->getCollection()->map(fn (Voucher $v) => $this->serialiser($v))->values(),
            'stats'     => $this->voucherService->statistiques(),
            'filtres'   => $filtres,
            'lotGenere' => session()->pull('hotspot.lot_genere'),
        ]);
    }

    /** Endpoint JSON scruté toutes les 6s par le tableau (état quasi temps réel). */
    public function etat(Request $request)
    {
        $filtres  = $this->filtresDepuis($request);
        $vouchers = $this->requeteBase($filtres)->paginate(self::PAR_PAGE)->withQueryString();

        return response()->json([
            'lignes' => $vouchers->getCollection()->map(fn (Voucher $v) => $this->serialiser($v))->values(),
            'stats'  => $this->voucherService->statistiques(),
        ]);
    }

    /** Listes "Serveur" et "Profil" en direct depuis le MikroTik, pour les modales. */
    public function optionsMikrotik()
    {
        try {
            $serveurs = collect($this->mikroTik->listHotspotServers())->pluck('name')->filter()->values();
        } catch (\Throwable $e) {
            $serveurs = collect();
        }

        try {
            $profils = collect($this->mikroTik->listHotspotProfiles())->pluck('name')->filter()->values();
        } catch (\Throwable $e) {
            $profils = collect();
        }

        return response()->json([
            'serveurs' => $serveurs->prepend('all')->unique()->values(),
            'profils'  => $profils->isNotEmpty() ? $profils->values() : collect([config('mikrotik.hotspot_profile', 'default')]),
        ]);
    }

    public function generer(Request $request)
    {
        $data = $request->validate([
            'quantite'       => ['required', 'integer', 'min:1', 'max:200'],
            'mode'           => ['required', Rule::in(['distinct', 'identique'])],
            'longueur'       => ['required', 'integer', 'min:3', 'max:8'],
            'prefixe'        => ['nullable', 'string', 'max:16'],
            'jeu'            => ['required', Rule::in(['minuscules', 'majuscules', 'mixte', 'minuscules_chiffres', 'majuscules_chiffres', 'mixte_chiffres'])],
            'serveur'        => ['nullable', 'string', 'max:64'],
            'profil'         => ['nullable', 'string', 'max:64'],
            'duree'          => ['nullable', 'integer', 'min:0'],
            'limite_data_mo' => ['nullable', 'integer', 'min:0'],
            'commentaire'    => ['nullable', 'string', 'max:255'],
            'force'          => ['nullable', 'boolean'],
        ]);

        $stats = $this->voucherService->statistiques();

        if (empty($data['force']) && $stats['pool_disponibles'] >= $stats['pool_seuil']) {
            return back()->withInput()->withErrors([
                'global' => "Il reste encore {$stats['pool_disponibles']} identifiant(s) disponible(s) (seuil {$stats['pool_seuil']}). Cochez « Générer quand même » pour continuer.",
            ]);
        }

        try {
            $lot = $this->voucherService->genererEnMasse($data);
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors(['global' => $e->getMessage()]);
        }

        session(['hotspot.lot_genere' => $lot->id]);

        return redirect()->route('hotspot.index')->with('success', "{$lot->quantite_generee} identifiant(s) généré(s).");
    }

    public function ajouter(Request $request)
    {
        $data = $request->validate([
            'nom'             => ['nullable', 'string', 'max:100'],
            'username'        => ['required', 'string', 'max:64', 'regex:/^\S+$/'],
            'password'        => ['required', 'string', 'min:3', 'max:64', 'regex:/^\S+$/'],
            'serveur'         => ['nullable', 'string', 'max:64'],
            'profil'          => ['nullable', 'string', 'max:64'],
            'duree'           => ['nullable', 'integer', 'min:0'],
            'limite_data_mo'  => ['nullable', 'integer', 'min:0'],
            'commentaire'     => ['nullable', 'string', 'max:255'],
            'reutilisable'    => ['nullable', 'boolean'],
            'protege'         => ['nullable', 'boolean'],
        ], [
            'username.regex' => 'Le username ne doit pas contenir d’espace.',
            'password.regex' => 'Le mot de passe ne doit pas contenir d’espace.',
        ]);

        try {
            $voucher = $this->voucherService->creerIdentifiantManuel($data);
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors(['global' => $e->getMessage()]);
        }

        $message = "Identifiant « {$voucher->username} » créé.";
        if ($voucher->mikrotik_synced_at === null) {
            $message .= ' ⚠ Non synchronisé avec MikroTik.';
        }

        return redirect()->route('hotspot.index')->with('success', $message);
    }

    public function imprimerLot(LotVoucher $lot)
    {
        $vouchers = Voucher::where('lot_voucher_id', $lot->id)->orderBy('id')->get();

        if ($vouchers->isEmpty()) {
            abort(404);
        }

        return view('hotspot.print-lot', ['vouchers' => $vouchers]);
    }

    public function proteger(Voucher $voucher)
    {
        $voucher = $this->voucherService->basculerProtection($voucher);

        return response()->json(['protege' => $voucher->protege]);
    }

    public function renommerAppareil(Request $request, AppareilWifi $appareil)
    {
        $data = $request->validate(['nom' => ['required', 'string', 'max:100']]);

        $appareil->update(['nom_affichage' => trim($data['nom'])]);

        return response()->json(['nom' => $appareil->nom_affichage]);
    }

    public function synchroniserTout()
    {
        $result = $this->voucherService->synchroniserVouchersEnAttente(100);

        $msg = "Synchronisation : {$result['ok']} OK";
        if ($result['ko'] > 0) {
            $msg .= ", {$result['ko']} échec(s)";
        }
        $msg .= ". Restants : {$result['restants']}.";

        return back()->with('success', $msg);
    }

    public function supprimerMasse(Request $request)
    {
        $data = $request->validate([
            'ids'   => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer'],
        ]);

        $r = $this->voucherService->supprimerEnMasse($data['ids']);

        $msg = "{$r['supprimes']} identifiant(s) supprimé(s)";
        if ($r['proteges_ignores'] > 0) {
            $msg .= ", {$r['proteges_ignores']} protégé(s) ignoré(s)";
        }
        if ($r['echecs'] > 0) {
            $msg .= ", {$r['echecs']} en échec";
        }

        return back()->with('success', $msg . '.');
    }

    public function poolMode(Request $request)
    {
        $data = $request->validate(['mode' => ['required', Rule::in(['auto', 'manuel'])]]);

        Parametre::set('hotspot_pool_mode', $data['mode']);

        return back()->with('success', $data['mode'] === 'auto'
            ? 'Réapprovisionnement automatique activé.'
            : 'Réapprovisionnement automatique désactivé — mode manuel.');
    }

    // -----------------------------------------------------------------

    private function filtresDepuis(Request $request): array
    {
        return [
            'etat'         => $request->string('etat')->toString(),
            'q'            => $request->string('q')->toString(),
            'reutilisable' => $request->string('reutilisable')->toString(),
            'protege'      => $request->string('protege')->toString(),
        ];
    }

    private function requeteBase(array $filtres)
    {
        return Voucher::query()
            ->with('appareilWifi')
            ->withSum('sessions as volume_total_octets', 'volume_total')
            ->filtrer($filtres)
            ->orderByRaw("FIELD(etat, 'utilise', 'en_cours', 'disponible')")
            ->orderByDesc('id');
    }

    private function serialiser(Voucher $v): array
    {
        return [
            'id'             => $v->id,
            'username'       => $v->username,
            'nom'            => $v->nom,
            'password'       => $v->password,
            'protege'        => (bool) $v->protege,
            'reutilisable'   => $v->estReutilisable(),
            'etat'           => $v->etat,
            'etat_libelle'   => $v->libelleEtat(),
            'etat_couleur'   => $v->couleurEtat(),
            'classe_ligne'   => $v->classeLigne(),
            'appareil_id'    => $v->appareilWifi?->id,
            'appareil'       => $v->appareilWifi?->libelle(),
            'appareil_icone' => $v->iconeAppareil(),
            'volume'         => $v->volumeLisible(),
            'synced'         => (bool) $v->mikrotik_synced_at,
        ];
    }
}