<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Dunning Fees
    |--------------------------------------------------------------------------
    |
    | Fixed fee (in EUR) charged for each dunning level. Bindende
    | User-Entscheidung 3 (siehe openspec/changes/add-invoice-dunning-
    | dashboard/design.md Decision D4): fest im Code/als Konfiguration
    | hinterlegt, nicht frei im Dialog eingebbar. Overridable per Env for
    | shared-hosting deployments without a redeploy (CLAUDE.md Abschnitt 6).
    |
    */

    'dunning_fees' => [
        1 => (float) env('DUNNING_FEE_LEVEL_1', 5.00),
        2 => (float) env('DUNNING_FEE_LEVEL_2', 10.00),
        3 => (float) env('DUNNING_FEE_LEVEL_3', 15.00),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maximum Dunning Level
    |--------------------------------------------------------------------------
    |
    | No further dunning is possible via the app once this level is
    | reached (bindende Entscheidung 3).
    |
    */

    'max_dunning_level' => 3,

];
