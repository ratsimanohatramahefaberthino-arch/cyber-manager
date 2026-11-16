<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class AgentVersion extends Model
{
    /** Dossier PRIVÉ (disque « local » = storage/app/private). Jamais le disque « public ». */
    public const DOSSIER = 'agent-packages';

    public const FUSEAU = 'Indian/Antananarivo';   // UTC+3

    protected $table = 'agent_versions';

    protected $fillable = [
        'version', 'fichier', 'sha256', 'taille', 'notes', 'statut',
        'pilotes', 'publiee_pilote_at', 'publiee_generale_at',
    ];

    protected $casts = [
        'pilotes'             => 'array',
        'taille'              => 'integer',
        'publiee_pilote_at'   => 'datetime',
        'publiee_generale_at' => 'datetime',
    ];

    public function chemin(): string
    {
        return Storage::disk('local')->path(self::DOSSIER . '/' . $this->fichier);
    }

    public function fichierExiste(): bool
    {
        return is_file($this->chemin());
    }

    /** Version publiée pour tous les postes (la plus récemment publiée). */
    public static function generale(): ?self
    {
        return static::where('statut', 'general')->orderByDesc('publiee_generale_at')->orderByDesc('id')->first();
    }

    /** Version en test sur les postes pilotes. */
    public static function pilote(): ?self
    {
        return static::where('statut', 'pilote')->orderByDesc('publiee_pilote_at')->orderByDesc('id')->first();
    }

    /** Version visée pour un poste : la version pilote s'il en est un, sinon la version générale. */
    public static function viseePour(Poste $poste): ?self
    {
        $p = static::pilote();
        if ($p && in_array((int) $poste->id, array_map('intval', $p->pilotes ?? []), true)) {
            return $p;
        }

        return static::generale();
    }

    /** Date/heure affichée en heure de Madagascar (la base reste en UTC). */
    public static function heure($date, string $format = 'd/m/Y H:i'): string
    {
        if (empty($date)) {
            return '—';
        }

        return Carbon::parse($date)->timezone(self::FUSEAU)->format($format);
    }

    public static function tailleLisible(int $octets): string
    {
        return $octets >= 1048576
            ? number_format($octets / 1048576, 1, ',', ' ') . ' Mo'
            : number_format($octets / 1024, 0, ',', ' ') . ' Ko';
    }
}