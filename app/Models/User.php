<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const ROLE_ADMIN     = 'admin';
    public const ROLE_PERSONNEL = 'personnel';
    public const DELAI_EN_LIGNE_MINUTES = 15;

    protected $fillable = [
        'name',
        'email',
        'password',
        'telephone',
        'notes_internes',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'               => 'datetime',
            'password'                        => 'hashed',
            'last_login_at'                   => 'datetime',
            'avatar_maj_at'                   => 'datetime',
            'verification_code_expires_at'    => 'datetime',
            'reset_password_code_expires_at'  => 'datetime',
            'locked_until'                    => 'datetime',
        ];
    }

    public function estAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function libelleRole(): string
    {
        return $this->estAdmin() ? 'Administrateur' : 'Personnel';
    }

    // ------------------------------------------------------------------
    // Avatar — Corrigé pour ne pas dépendre de Apache/XAMPP
    // ------------------------------------------------------------------

    public function avatarUrl(): ?string
    {
        if (!filled($this->avatar)) {
            return null;
        }
        if (!Storage::disk('public')->exists($this->avatar)) {
            return null;
        }
        // Utilise une route Laravel au lieu du dossier public
        $v = $this->avatar_maj_at?->timestamp ?? 0;
        return route('avatar.show', ['user' => $this->id]) . ($v ? '?v=' . $v : '');
    }

    public function initiales(): string
    {
        $nom = trim((string) $this->name);
        if ($nom === '') return '?';
        $morceaux = preg_split('/[\s\-_\']+/u', $nom, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($morceaux) >= 2) {
            return mb_strtoupper(mb_substr($morceaux[0], 0, 1) . mb_substr($morceaux[1], 0, 1));
        }
        return mb_strtoupper(mb_substr($morceaux[0], 0, 2));
    }

    public function couleurAvatar(): string
    {
        $palette = ['#7c3aed', '#0ea5e9', '#10b981', '#f59e0b', '#ef4444', '#ec4899', '#8b5cf6', '#6366f1'];
        $hash = crc32(mb_strtolower(trim((string) $this->name)));
        return $palette[abs($hash) % count($palette)];
    }

    public function estEnLigne(): bool
    {
        return $this->last_login_at && $this->last_login_at->gt(now()->subMinutes(self::DELAI_EN_LIGNE_MINUTES));
    }

    public function dernierSigneDeVie(): string
    {
        if (!$this->last_login_at) return 'Jamais connecté';
        return 'Vu ' . $this->last_login_at->locale('fr')->diffForHumans();
    }

    // ------------------------------------------------------------------
    // Vérification email & Réinitialisation mot de passe
    // ------------------------------------------------------------------

    public function genererCodeVerification(): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $this->verification_code = $code;
        $this->verification_code_expires_at = now()->addMinutes(15);
        $this->save();
        return $code;
    }

    public function verifierCode(string $code): bool
    {
        return $this->verification_code === $code
            && $this->verification_code_expires_at
            && $this->verification_code_expires_at->isFuture();
    }

    public function marquerEmailVerifie(): void
    {
        $this->email_verified_at = now();
        $this->verification_code = null;
        $this->verification_code_expires_at = null;
        $this->save();
    }

    public function genererCodeReinitialisation(): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $this->reset_password_code = $code;
        $this->reset_password_code_expires_at = now()->addMinutes(15);
        $this->save();
        return $code;
    }

    public function verifierCodeReinitialisation(string $code): bool
    {
        return $this->reset_password_code === $code
            && $this->reset_password_code_expires_at
            && $this->reset_password_code_expires_at->isFuture();
    }

    public function effacerCodesReinitialisation(): void
    {
        $this->reset_password_code = null;
        $this->reset_password_code_expires_at = null;
        $this->save();
    }

    // ------------------------------------------------------------------
    // Étape 6 : Sécurité Login
    // ------------------------------------------------------------------

    public function estVerrouille(): bool
    {
        return $this->locked_until && $this->locked_until->isFuture();
    }

    public function enregistrerEchecConnexion(int $max = 5, int $minutesBlocage = 15): void
    {
        $this->failed_login_attempts++;
        if ($this->failed_login_attempts >= $max) {
            $this->locked_until = now()->addMinutes($minutesBlocage);
            $this->failed_login_attempts = 0;
        }
        $this->save();
    }

    public function reinitialiserEchecsConnexion(): void
    {
        if ($this->failed_login_attempts > 0 || $this->locked_until !== null) {
            $this->failed_login_attempts = 0;
            $this->locked_until = null;
            $this->save();
        }
    }

    public function debloquer(): void
    {
        $this->failed_login_attempts = 0;
        $this->locked_until = null;
        $this->save();
    }
}