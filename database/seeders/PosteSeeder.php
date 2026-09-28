<?php

namespace Database\Seeders;

use App\Models\Poste;
use Illuminate\Database\Seeder;

class PosteSeeder extends Seeder
{
    public function run(): void
    {
        Poste::create([
            'nom_poste' => 'POSTE1',
            'nom_windows' => null,
            'adresse_mac' => null,
            'adresse_ip' => '128.1.0.152',
            'type_connexion' => 'ethernet',
            'etat' => 'disponible',
            'actif' => true,
        ]);

        Poste::create([
            'nom_poste' => 'POSTE2',
            'nom_windows' => null,
            'adresse_mac' => null,
            'adresse_ip' => '128.1.0.150',
            'type_connexion' => 'ethernet',
            'etat' => 'disponible',
            'actif' => true,
        ]);

        Poste::create([
            'nom_poste' => 'POSTE3',
            'nom_windows' => null,
            'adresse_mac' => null,
            'adresse_ip' => null,
            'type_connexion' => 'ethernet',
            'etat' => 'disponible',
            'actif' => true,
        ]);

        Poste::create([
            'nom_poste' => 'POSTE7',
            'nom_windows' => null,
            'adresse_mac' => 'C0-3F-D5-5E-EE-B7',
            'adresse_ip' => '128.1.0.165',
            'type_connexion' => 'ethernet',
            'etat' => 'disponible',
            'actif' => true,
        ]);
    }
}