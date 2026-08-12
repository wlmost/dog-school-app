<?php

declare(strict_types=1);

use App\Models\Invoice;
use App\Services\InvoiceNumberGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
uses()->group('feature', 'invoice');

beforeEach(function () {
    $this->generator = new InvoiceNumberGenerator;
});

it('liefert die erste nummer des jahres wenn noch keine rechnung existiert', function () {
    $year = date('Y');

    expect($this->generator->generate())->toBe("RE-{$year}-0001");
});

it('liefert die nächsthöhere nummer wenn bereits rechnungen im laufenden jahr existieren', function () {
    $year = date('Y');
    Invoice::factory()->create(['status' => 'sent', 'invoice_number' => "RE-{$year}-0003"]);

    expect($this->generator->generate())->toBe("RE-{$year}-0004");
});

it('startet die zählung für ein neues jahr wieder bei 0001, unabhängig von vorjahres-nummern', function () {
    $currentYear = date('Y');
    $previousYear = (string) ((int) $currentYear - 1);
    Invoice::factory()->create(['status' => 'sent', 'invoice_number' => "RE-{$previousYear}-0099"]);

    expect($this->generator->generate())->toBe("RE-{$currentYear}-0001");
});

it('ignoriert rechnungsnummern ohne das erwartete jahres-präfix bei der maximumsuche', function () {
    $year = date('Y');
    // Altbestand aus der Zeit vor diesem Change nutzte das Format
    // "INV-######" (siehe InvoiceFactory::definition()-Default) statt
    // "RE-{Jahr}-####" — solche Nummern dürfen die Suche nach der höchsten
    // "RE-{Jahr}-%"-Nummer nicht beeinflussen.
    Invoice::factory()->create(['status' => 'sent']); // behält den Factory-Default "INV-######"

    expect($this->generator->generate())->toBe("RE-{$year}-0001");
});

// Dokumentiert die in `task-T03.notes.md` ("Wichtiger Befund") beschriebene,
// bewusst nicht behobene Lücke: `lockForUpdate()` kann eine leere
// Ergebnismenge nicht sperren, daher liefern zwei aufeinanderfolgende
// `generate()`-Aufrufe ohne zwischenzeitliches Persistieren dieselbe Nummer.
// Dieser Test mockt **nichts** — er ruft den echten Service zweimal
// unmittelbar hintereinander auf und dokumentiert damit den realen,
// zugrunde liegenden Konflikt, den die Retry-Logik in
// `InvoiceController::finalize()`/`cancel()` abfedert (siehe
// `InvoiceApiTest.php`, Tests `'finalize retries ...'`/`'cancel retries ...'`
// für die Absicherung auf Controller-Ebene mittels einer echten
// DB-Unique-Constraint-Verletzung).
it('liefert bei zwei aufeinanderfolgenden aufrufen ohne zwischenzeitliches persistieren dieselbe nummer (dokumentierte race condition)', function () {
    $first = $this->generator->generate();
    $second = $this->generator->generate();

    expect($first)->toBe($second);
});

// Gegenprobe zum vorherigen Test: sobald die erste Nummer tatsächlich
// persistiert wird (der Normalfall bei einem einzelnen finalize()/cancel()-
// Aufruf), liefert der nächste generate()-Aufruf korrekt die nächsthöhere
// Nummer — die Race Condition betrifft ausschließlich echte Gleichzeitigkeit,
// nicht den sequenziellen Normalfall.
it('liefert nach dem persistieren der ersten nummer korrekt die nächsthöhere nummer', function () {
    $year = date('Y');
    $first = $this->generator->generate();
    Invoice::factory()->create(['status' => 'sent', 'invoice_number' => $first]);

    $second = $this->generator->generate();

    expect($first)->toBe("RE-{$year}-0001");
    expect($second)->toBe("RE-{$year}-0002");
});
