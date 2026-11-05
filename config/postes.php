<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Poste injoignable pendant une session
    |--------------------------------------------------------------------------
    |
    | Le temps pendant lequel un poste ne donne aucun signe de vie n'est JAMAIS facturé.
    | La session reste ouverte pendant cette tolérance (redémarrage, coupure courte) ;
    | au-delà, elle est clôturée automatiquement, facturée jusqu'au dernier signe de vie.
    | 0 = ne jamais clôturer automatiquement (le gérant ferme à la main).
    |
    */
    'hors_ligne_tolerance_minutes' => (int) env('POSTES_HORS_LIGNE_TOLERANCE_MIN', 10),

    // Après (re)démarrage du planificateur, délai (s) pendant lequel on ne clôture pas pour
    // injoignabilité : laisse le temps aux agents de se reconnecter.
    'garde_redemarrage_secondes' => (int) env('POSTES_GARDE_REDEMARRAGE', 90),

];