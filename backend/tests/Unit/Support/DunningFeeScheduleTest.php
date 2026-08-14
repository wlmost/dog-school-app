<?php

declare(strict_types=1);

use App\Support\DunningFeeSchedule;
use Tests\TestCase;

// Bewusst an Tests\TestCase gebunden (abweichend vom Standard für
// tests/Unit/, siehe tests/Pest.php-Kommentar "ohne Container"): der
// `config('invoicing...')`-Aufruf in DunningFeeSchedule benötigt den
// gebooteten Laravel-Container. Kein RefreshDatabase, da kein DB-Zugriff
// stattfindet (Präzedenzfall: tests/Unit/Models/*Test.php binden aus
// demselben Grund lokal an TestCase).
uses(TestCase::class);
uses()->group('unit', 'support');

it('liefert stufe 1 als nächste stufe wenn noch keine mahnung existiert', function () {
    expect(DunningFeeSchedule::nextLevel(null))->toBe(1);
});

it('liefert die jeweils nächsthöhere stufe ausgehend von der aktuellen stufe', function () {
    expect(DunningFeeSchedule::nextLevel(1))->toBe(2);
    expect(DunningFeeSchedule::nextLevel(2))->toBe(3);
});

it('liefert null als nächste stufe wenn die maximale stufe bereits erreicht ist', function () {
    expect(DunningFeeSchedule::nextLevel(3))->toBeNull();
});

it('liefert die konfigurierten gebühren für die stufen 1 bis 3', function () {
    expect(DunningFeeSchedule::feeForLevel(1))->toBe(5.0);
    expect(DunningFeeSchedule::feeForLevel(2))->toBe(10.0);
    expect(DunningFeeSchedule::feeForLevel(3))->toBe(15.0);
});

it('liefert null für eine gebühr jenseits der maximalen stufe', function () {
    expect(DunningFeeSchedule::feeForLevel(4))->toBeNull();
});

it('liefert null für eine gebühr der stufe 0', function () {
    expect(DunningFeeSchedule::feeForLevel(0))->toBeNull();
});

it('liefert null für eine gebühr wenn für die stufe kein konfigurierter betrag hinterlegt ist', function () {
    // Simuliert eine lückenhafte Konfiguration (z. B. eine fehlende
    // env-Variable, die nicht sauber auf den PHP-Default zurückfällt) —
    // Stufe 2 fehlt bewusst im konfigurierten Array, unabhängig vom
    // `max_dunning_level`-Wert.
    config(['invoicing.dunning_fees' => [1 => 5.0, 3 => 15.0]]);

    expect(DunningFeeSchedule::feeForLevel(2))->toBeNull();
    expect(DunningFeeSchedule::feeForLevel(1))->toBe(5.0);
});

it('respektiert eine abweichend konfigurierte maximale mahnstufe statt den wert 3 hart zu codieren', function () {
    // Regressionsschutz dafür, dass `nextLevel()` tatsächlich
    // `config('invoicing.max_dunning_level')` liest, statt den in
    // `config/invoicing.php` aktuell hinterlegten Default (3) fest im Code
    // zu verankern — mit dieser abweichenden Konfiguration muss bereits
    // Stufe 1 die letzte zulässige Stufe sein.
    config(['invoicing.max_dunning_level' => 1]);

    expect(DunningFeeSchedule::nextLevel(null))->toBe(1);
    expect(DunningFeeSchedule::nextLevel(1))->toBeNull();
});
