<?php

declare(strict_types=1);

use App\Models\PricingItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
uses()->group('api', 'pricing');

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

// ---------------------------------------------------------------------------
// Öffentliche Route: GET /api/v1/pricing-items
// ---------------------------------------------------------------------------

it('liefert http 200 für die öffentliche preisübersicht ohne authentication', function () {
    $response = $this->getJson('/api/v1/pricing-items');

    $response->assertOk();
});

it('liefert ein leeres data-array wenn keine pricing-items vorhanden sind', function () {
    $response = $this->getJson('/api/v1/pricing-items');

    $response->assertOk();
    expect($response->json('data'))->toBeEmpty();
});

it('liefert die response-struktur mit einem data-array', function () {
    $response = $this->getJson('/api/v1/pricing-items');

    $response->assertOk()
        ->assertJsonStructure(['data']);

    expect($response->json('data'))->toBeArray();
});

it('gibt pricing-items gruppiert nach kategorie zurück', function () {
    PricingItem::factory()->create(['category' => 'Welpenkurs', 'title' => 'Kurs A']);
    PricingItem::factory()->create(['category' => 'Welpenkurs', 'title' => 'Kurs B']);
    PricingItem::factory()->create(['category' => 'Einzelstunden', 'title' => 'Stunde C']);

    $response = $this->getJson('/api/v1/pricing-items');

    $response->assertOk();

    $data = $response->json('data');
    expect($data)->toHaveCount(2);

    $categories = collect($data)->pluck('category')->toArray();
    expect($categories)->toContain('Welpenkurs');
    expect($categories)->toContain('Einzelstunden');

    $welpenkurs = collect($data)->firstWhere('category', 'Welpenkurs');
    expect($welpenkurs['items'])->toHaveCount(2);
});

it('liefert je einen gruppen-eintrag mit category und items-schlüssel', function () {
    PricingItem::factory()->create(['category' => 'Gruppentraining']);

    $response = $this->getJson('/api/v1/pricing-items');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'category',
                    'items',
                ],
            ],
        ]);
});

it('serialisiert isFromPrice als camelcase-schlüssel', function () {
    PricingItem::factory()->create(['category' => 'Welpenkurs', 'is_from_price' => true]);

    $response = $this->getJson('/api/v1/pricing-items');

    $response->assertOk();

    $item = $response->json('data.0.items.0');
    expect($item)->toHaveKey('isFromPrice');
    expect($item['isFromPrice'])->toBeTrue();
});

// ---------------------------------------------------------------------------
// Admin-Route: GET /api/v1/admin/pricing-items
// ---------------------------------------------------------------------------

it('weist die admin-liste zurück wenn kein token vorhanden ist', function () {
    $response = $this->getJson('/api/v1/admin/pricing-items');

    $response->assertUnauthorized();
});

it('liefert eine flache liste aller pricing-items für admins', function () {
    PricingItem::factory()->count(3)->create();

    $response = $this->actingAs($this->admin)
        ->getJson('/api/v1/admin/pricing-items');

    $response->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'category',
                    'title',
                    'price',
                    'unit',
                    'description',
                    'isFromPrice',
                    'createdAt',
                    'updatedAt',
                ],
            ],
        ]);
});

// ---------------------------------------------------------------------------
// Admin-Route: POST /api/v1/admin/pricing-items
// ---------------------------------------------------------------------------

it('weist das erstellen zurück wenn kein token vorhanden ist', function () {
    $response = $this->postJson('/api/v1/admin/pricing-items', [
        'category' => 'Welpenkurs',
        'title'    => 'Test',
        'price'    => 99.00,
    ]);

    $response->assertUnauthorized();
});

it('erstellt ein pricing-item als admin mit validen daten', function () {
    $payload = [
        'category'    => 'Welpenkurs',
        'title'       => 'Welpenkurs Grundkurs',
        'price'       => 129.50,
        'unit'        => 'je Kurs',
        'description' => 'Grundlagen für Welpen',
        'isFromPrice' => false,
    ];

    $response = $this->actingAs($this->admin)
        ->postJson('/api/v1/admin/pricing-items', $payload);

    $response->assertCreated()
        ->assertJsonStructure([
            'data' => [
                'id',
                'category',
                'title',
                'price',
                'unit',
                'description',
                'isFromPrice',
            ],
        ])
        ->assertJsonPath('data.category', 'Welpenkurs')
        ->assertJsonPath('data.title', 'Welpenkurs Grundkurs');

    $this->assertDatabaseHas('pricing_items', [
        'category' => 'Welpenkurs',
        'title'    => 'Welpenkurs Grundkurs',
    ]);
});

it('lehnt das erstellen ab wenn pflichtfelder fehlen', function () {
    $response = $this->actingAs($this->admin)
        ->postJson('/api/v1/admin/pricing-items', []);

    $response->assertUnprocessable()
        ->assertJsonStructure(['errors'])
        ->assertJsonValidationErrors(['category', 'title', 'price']);
});

// ---------------------------------------------------------------------------
// Admin-Route: PUT /api/v1/admin/pricing-items/{id}
// ---------------------------------------------------------------------------

it('aktualisiert ein pricing-item als admin', function () {
    $item = PricingItem::factory()->create([
        'category' => 'Welpenkurs',
        'title'    => 'Alter Titel',
        'price'    => 100.00,
    ]);

    $response = $this->actingAs($this->admin)
        ->putJson("/api/v1/admin/pricing-items/{$item->id}", [
            'category' => 'Welpenkurs',
            'title'    => 'Neuer Titel',
            'price'    => 120.00,
        ]);

    $response->assertOk()
        ->assertJsonPath('data.title', 'Neuer Titel');

    $this->assertDatabaseHas('pricing_items', [
        'id'    => $item->id,
        'title' => 'Neuer Titel',
    ]);
});

// ---------------------------------------------------------------------------
// Admin-Route: DELETE /api/v1/admin/pricing-items/{id}
// ---------------------------------------------------------------------------

it('löscht ein pricing-item als admin', function () {
    $item = PricingItem::factory()->create();

    $response = $this->actingAs($this->admin)
        ->deleteJson("/api/v1/admin/pricing-items/{$item->id}");

    $response->assertNoContent();

    $this->assertDatabaseMissing('pricing_items', ['id' => $item->id]);
});

it('weist das löschen zurück wenn kein token vorhanden ist', function () {
    $item = PricingItem::factory()->create();

    $response = $this->deleteJson("/api/v1/admin/pricing-items/{$item->id}");

    $response->assertUnauthorized();
});
