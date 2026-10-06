<?php

namespace Database\Seeders;

use App\Models\Tarif;
use App\Models\TarifRaccourci;
use Illuminate\Database\Seeder;

class TarifSeeder extends Seeder
{
    public function run(): void
    {
        TarifRaccourci::query()->delete();
        Tarif::query()->delete();

        $tarif = Tarif::create([
            'cible'              => Tarif::CIBLE_UNIFIE,
            'montant_par_minute' => 20,
            'montant_minimum'    => 300,
            'arrondi_actif'      => true,
            'unite_arrondi'      => 100,
            'seuil_arrondi'      => 50,
            'description'        => 'Tarif initial : 20 Ar/min, minimum 300 Ar.',
        ]);

        $tarif->raccourcis()->createMany([
            ['montant' => 300,  'duree' => 15, 'libelle' => '15 min', 'ordre' => 10],
            ['montant' => 500,  'duree' => 25, 'libelle' => '25 min', 'ordre' => 20],
            ['montant' => 600,  'duree' => 30, 'libelle' => '30 min', 'ordre' => 30],
            ['montant' => 1000, 'duree' => 50, 'libelle' => '50 min', 'ordre' => 40],
        ]);

        $this->command?->info('Tarif initial créé (unifié : Ethernet + Wi-Fi, 20 Ar/min).');
    }
}