<?php

namespace App\Http\Controllers;

use App\Models\AgentMiseAJour;
use App\Models\AgentVersion;
use App\Models\Poste;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class VersionsController extends Controller
{
    public function index(Request $request)
    {
        $filtre = $request->query('statut');
        
        $query = AgentVersion::orderByDesc('id');
        if ($filtre && in_array($filtre, ['brouillon', 'pilote', 'general', 'archivee'])) {
            $query->where('statut', $filtre);
        }
        
        $versions = $query->get();
        
        // Statistiques
        $stats = [
            'brouillon' => AgentVersion::where('statut', 'brouillon')->count(),
            'pilote'    => AgentVersion::where('statut', 'pilote')->count(),
            'general'   => AgentVersion::where('statut', 'general')->count(),
            'archivee'  => AgentVersion::where('statut', 'archivee')->count(),
        ];

        $generale = AgentVersion::generale();
        $pilote = AgentVersion::pilote();

        // Pourcentage à jour
        $totalPostes = Poste::count();
        $postesAJour = 0;
        if ($generale && $totalPostes > 0) {
            $postesAJour = AgentMiseAJour::where('version', $generale->version)
                ->where('succes', true)
                ->distinct('poste_id')
                ->count('poste_id');
        }
        $pourcentageAJour = $totalPostes > 0 ? round(($postesAJour / $totalPostes) * 100) : 0;

        return view('parametres.versions', [
            'versions'    => $versions,
            'postes'      => Poste::orderBy('nom_poste')->get(['id', 'nom_poste']),
            'historique'  => AgentMiseAJour::with('poste:id,nom_poste')->orderByDesc('id')->limit(30)->get(),
            'filtre'      => $filtre,
            'stats'       => $stats,
            'pourcentageAJour' => $pourcentageAJour,
            'postesAJour' => $postesAJour,
            'totalPostes' => $totalPostes,
        ]);
    }

    public function televerser(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'version' => ['required', 'regex:/^[0-9A-Za-z._-]{1,32}$/', 'unique:agent_versions,version'],
            'paquet'  => ['required', 'file', 'mimes:zip', 'max:307200'],
            'notes'   => ['nullable', 'string', 'max:500'],
        ], [
            'version.required' => 'Le numéro de version est obligatoire.',
            'version.regex'    => 'Version invalide (lettres, chiffres, point, tiret ; 32 caractères max).',
            'version.unique'   => 'Cette version existe déjà.',
            'paquet.required'  => 'Choisissez le fichier .zip. S\'il est gros, vérifiez upload_max_filesize et post_max_size dans php.ini (XAMPP : 40M par défaut).',
            'paquet.uploaded'  => 'Envoi impossible : le fichier dépasse la limite PHP. Augmentez upload_max_filesize et post_max_size dans php.ini, puis redémarrez Apache.',
            'paquet.mimes'     => 'Le paquet doit être un fichier .zip.',
            'paquet.max'       => 'Le paquet ne doit pas dépasser 300 Mo.',
        ]);

        $nom = 'ecran-' . $data['version'] . '.zip';
        $request->file('paquet')->storeAs(AgentVersion::DOSSIER, $nom, 'local');

        $chemin = Storage::disk('local')->path(AgentVersion::DOSSIER . '/' . $nom);
        AgentVersion::create([
            'version' => $data['version'],
            'fichier' => $nom,
            'sha256'  => strtolower((string) hash_file('sha256', $chemin)),
            'taille'  => (int) filesize($chemin),
            'notes'   => $data['notes'] ?? null,
            'statut'  => 'brouillon',
        ]);

        return back()->with('success', "Version {$data['version']} envoyée (brouillon). Publiez-la d'abord sur un poste pilote.");
    }

    public function pilote(Request $request, AgentVersion $version): RedirectResponse
    {
        $data = $request->validate([
            'postes'   => ['required', 'array', 'min:1'],
            'postes.*' => ['integer', 'exists:postes,id'],
        ], ['postes.required' => 'Cochez au moins un poste pilote.']);

        if (!$version->fichierExiste()) {
            return back()->withErrors(['Le fichier de la version ' . $version->version . ' est introuvable sur le serveur.']);
        }
        if ($version->statut === 'general') {
            return back()->withErrors(['Cette version est déjà publiée pour tous les postes.']);
        }

        DB::transaction(function () use ($version, $data) {
            AgentVersion::where('statut', 'pilote')->where('id', '!=', $version->id)->update(['statut' => 'brouillon']);
            $version->update([
                'statut'            => 'pilote',
                'pilotes'           => array_values(array_map('intval', $data['postes'])),
                'publiee_pilote_at' => now(),
            ]);
        });

        return back()->with('success', "Version {$version->version} en test sur " . count($data['postes']) . ' poste(s) pilote(s).');
    }

    public function general(AgentVersion $version): RedirectResponse
    {
        if (!$version->fichierExiste()) {
            return back()->withErrors(['Le fichier de la version ' . $version->version . ' est introuvable sur le serveur.']);
        }

        DB::transaction(function () use ($version) {
            AgentVersion::where('statut', 'general')->where('id', '!=', $version->id)->update(['statut' => 'archivee']);
            $version->update(['statut' => 'general', 'pilotes' => null, 'publiee_generale_at' => now()]);
        });

        return back()->with('success', "Version {$version->version} publiée pour tous les postes. Chaque poste la recevra quand il sera libre et verrouillé.");
    }

    public function depublier(AgentVersion $version): RedirectResponse
    {
        $version->update(['statut' => $version->statut === 'general' ? 'archivee' : 'brouillon', 'pilotes' => null]);

        return back()->with('success', "Version {$version->version} retirée : plus aucun poste ne la reçoit.");
    }

    public function supprimer(AgentVersion $version): RedirectResponse
    {
        if (in_array($version->statut, ['pilote', 'general'], true)) {
            return back()->withErrors(['Retirez d\'abord cette version avant de la supprimer.']);
        }

        Storage::disk('local')->delete(AgentVersion::DOSSIER . '/' . $version->fichier);
        $nom = $version->version;
        $version->delete();

        return back()->with('success', "Version {$nom} supprimée.");
    }

    /** Télécharger une version archivée ou n'importe quelle version */
    public function telecharger(AgentVersion $version)
    {
        if (!$version->fichierExiste()) {
            return back()->withErrors(['Le fichier est introuvable sur le serveur.']);
        }
        return response()->download($version->chemin(), 'ecran-' . $version->version . '.zip');
    }

    /** Forcer la mise à jour immédiate de tous les postes */
    public function forcerMiseAJour(Request $request): RedirectResponse
    {
        $generale = AgentVersion::generale();
        if (!$generale) {
            return back()->withErrors(['Aucune version publiée pour le moment. Publiez une version d\'abord.']);
        }

        // On crée un événement de mise à jour pour chaque poste actif
        $postes = Poste::where('actif', true)->get();
        $count = 0;
        foreach ($postes as $poste) {
            // On utilise la table AgentMiseAJour pour dire "ce poste doit se mettre à jour"
            // Le service de mise à jour (côté agent) va lire ceci.
            AgentMiseAJour::create([
                'poste_id' => $poste->id,
                'version'  => $generale->version,
                'succes'   => false,
                'message'  => 'Mise à jour immédiate demandée par l\'administrateur.',
            ]);
            $count++;
        }

        return back()->with('success', "Ordre de mise à jour envoyé à {$count} poste(s). Ils vont se mettre à jour dès qu'ils seront libres.");
    }
}