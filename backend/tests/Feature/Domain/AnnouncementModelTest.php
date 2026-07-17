<?php

declare(strict_types=1);

use App\Models\Announcement;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
uses()->group('domain', 'announcement');

it('berechnet expires_at beim erstellen aus created_at plus display_days', function () {
    // Vergleich auf Sekundengenauigkeit: die `timestamp`-Spalte in der
    // Migration hat keine Sub-Sekunden-Präzision (Standardpräzision 0),
    // die DB rundet Mikrosekunden beim Speichern weg. `->fresh()` liest
    // deshalb den tatsächlich persistierten (sekundengenauen) Wert.
    $announcement = Announcement::factory()->create(['display_days' => 10])->fresh();

    $expected = $announcement->created_at->copy()->addDays(10);

    expect($announcement->expires_at->format('Y-m-d H:i:s'))->toBe($expected->format('Y-m-d H:i:s'));
});

it('liefert isActive true für eine frisch erstellte ankündigung', function () {
    $announcement = Announcement::factory()->create(['display_days' => 30]);

    expect($announcement->isActive())->toBeTrue();
});

it('liefert isActive false für eine ankündigung aus dem expired-factory-state', function () {
    $announcement = Announcement::factory()->expired()->create();

    expect($announcement->isActive())->toBeFalse();
});

it('berechnet expires_at beim erhöhen von display_days auf einer bestehenden ankündigung ab dem ursprünglichen created_at neu, nicht ab jetzt', function () {
    $originalCreatedAt = now()->subDays(5);
    $announcement = Announcement::factory()->create(['display_days' => 7]);
    $announcement->forceFill(['created_at' => $originalCreatedAt])->saveQuietly();

    $announcement->update(['display_days' => 20]);

    $expected = $originalCreatedAt->copy()->addDays(20);

    expect($announcement->fresh()->expires_at->format('Y-m-d H:i:s'))->toBe($expected->format('Y-m-d H:i:s'));
});

it('berechnet expires_at beim verringern von display_days auf einer bestehenden ankündigung ebenfalls ab dem ursprünglichen created_at neu', function () {
    $originalCreatedAt = now()->subDays(3);
    $announcement = Announcement::factory()->create(['display_days' => 30]);
    $announcement->forceFill(['created_at' => $originalCreatedAt])->saveQuietly();

    $announcement->update(['display_days' => 5]);

    $expected = $originalCreatedAt->copy()->addDays(5);

    expect($announcement->fresh()->expires_at->format('Y-m-d H:i:s'))->toBe($expected->format('Y-m-d H:i:s'));
});

it('lässt expires_at unverändert wenn beim aktualisieren nur der titel geändert wird und display_days gleich bleibt', function () {
    $announcement = Announcement::factory()->create(['display_days' => 10]);
    $originalExpiresAt = $announcement->expires_at->copy();

    $announcement->update(['title' => 'Neuer Titel']);

    expect($announcement->fresh()->expires_at->eq($originalExpiresAt))->toBeTrue();
});

it('scopeActive liefert nur datensätze mit expires_at in der zukunft', function () {
    $active = Announcement::factory()->create();
    Announcement::factory()->expired()->create();

    $result = Announcement::active()->get();

    expect($result)->toHaveCount(1);
    expect($result->first()->id)->toBe($active->id);
});

it('scopeActive liefert eine leere sammlung wenn alle ankündigungen abgelaufen sind', function () {
    Announcement::factory()->expired()->create();
    Announcement::factory()->expired()->create();

    $result = Announcement::active()->get();

    expect($result)->toBeEmpty();
});
