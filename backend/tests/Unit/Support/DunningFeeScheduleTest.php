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
