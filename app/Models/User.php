<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_ADMIN     = 'admin';
    public const ROLE_PERSONNEL = 'personnel';

    /**
     * Le rôle n'est volontairement PAS assignable en masse : seul PersonnelController le définit.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
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
}