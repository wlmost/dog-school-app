<?php

declare(strict_types=1);

use App\Models\CreditPackage;
use App\Models\CustomerCredit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->trainer = User::factory()->create(['role' => 'trainer']);
    $this->customerUser = User::factory()->create(['role' => 'customer']);
});

test('authenticated users can list credit packages', function () {
    CreditPackage::factory()->count(5)->create();

    $this->actingAs($this->customerUser)
        ->getJson('/api/v1/credit-packages')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'totalCredits',
                    'price',
                    'validityDays',
                    'description',
                ],
            ],
        ]);
});

test('credit packages can be searched by name', function () {
    CreditPackage::factory()->create(['name' => '10er Karte']);
    CreditPackage::factory()->create(['name' => '5er Karte']);
    CreditPackage::factory()->create(['name' => 'Premium Package']);

    $response = $this->actingAs($this->customerUser)
        ->getJson('/api/v1/credit-packages?search=10er')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.name'))->toBe('10er Karte');
});

test('credit packages can be filtered by minimum credits', function () {
    CreditPackage::factory()->create(['total_credits' => 5]);
    CreditPackage::factory()->create(['total_credits' => 10]);
    CreditPackage::factory()->create(['total_credits' => 20]);

    $response = $this->actingAs($this->customerUser)
        ->getJson('/api/v1/credit-packages?minCredits=10')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(2);
});

test('can get available credit packages', function () {
    CreditPackage::factory()->count(3)->create();

    $response = $this->actingAs($this->customerUser)
        ->getJson('/api/v1/credit-packages/available/list')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(3);
});

test('can view single credit package', function () {
    $package = CreditPackage::factory()->create(['name' => 'Test Package']);

    $this->actingAs($this->customerUser)
        ->getJson('/api/v1/credit-packages/' . $package->id)
        ->assertOk()
        ->assertJsonPath('data.name', 'Test Package');
});

test('trainer can create credit package', function () {
    $data = [
        'name' => '10er Karte',
        'totalCredits' => 10,
        'price' => 150.00,
        'validityDays' => 180,
        'description' => '10 Trainingseinheiten',
    ];

    $this->actingAs($this->trainer)
        ->postJson('/api/v1/credit-packages', $data)
        ->assertCreated()
        ->assertJsonPath('data.name', '10er Karte')
        ->assertJsonPath('data.totalCredits', 10)
        ->assertJsonPath('data.price', 150);

    $this->assertDatabaseHas('credit_packages', [
        'name' => '10er Karte',
        'total_credits' => 10,
        'price' => 150.00,
    ]);
});

test('admin can create credit package', function () {
    $data = [
        'name' => '5er Karte',
        'totalCredits' => 5,
        'price' => 80.00,
    ];

    $this->actingAs($this->admin)
        ->postJson('/api/v1/credit-packages', $data)
        ->assertCreated()
        ->assertJsonPath('data.name', '5er Karte');
});

test('customer cannot create credit package', function () {
    $data = [
        'name' => 'Test Package',
        'totalCredits' => 10,
        'price' => 100.00,
    ];

    $this->actingAs($this->customerUser)
        ->postJson('/api/v1/credit-packages', $data)
        ->assertForbidden();
});

test('credit package creation validates required fields', function () {
    $this->actingAs($this->trainer)
        ->postJson('/api/v1/credit-packages', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'totalCredits', 'price']);
});

test('credit package price must be positive', function () {
    $data = [
        'name' => 'Test',
        'totalCredits' => 10,
        'price' => -50.00,
    ];

    $this->actingAs($this->trainer)
        ->postJson('/api/v1/credit-packages', $data)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['price']);
});

test('credit package credits must be at least 1', function () {
    $data = [
        'name' => 'Test',
        'totalCredits' => 0,
        'price' => 50.00,
    ];

    $this->actingAs($this->trainer)
        ->postJson('/api/v1/credit-packages', $data)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['totalCredits']);
});

test('trainer can update credit package', function () {
    $package = CreditPackage::factory()->create();

    $this->actingAs($this->trainer)
        ->putJson('/api/v1/credit-packages/' . $package->id, [
            'name' => 'Updated Package',
            'price' => 200.00,
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Updated Package')
        ->assertJsonPath('data.price', 200);

    $this->assertDatabaseHas('credit_packages', [
        'id' => $package->id,
        'name' => 'Updated Package',
    ]);
});

test('customer cannot update credit package', function () {
    $package = CreditPackage::factory()->create();

    $this->actingAs($this->customerUser)
        ->putJson('/api/v1/credit-packages/' . $package->id, [
            'name' => 'Hacked',
        ])
        ->assertForbidden();
});

test('admin can delete unused credit package', function () {
    $package = CreditPackage::factory()->create();

    $this->actingAs($this->admin)
        ->deleteJson('/api/v1/credit-packages/' . $package->id)
        ->assertNoContent();

    expect(CreditPackage::find($package->id))->toBeNull();
});

test('cannot delete credit package with purchases', function () {
    $package = CreditPackage::factory()->create();
    CustomerCredit::factory()->create(['credit_package_id' => $package->id]);

    $this->actingAs($this->admin)
        ->deleteJson('/api/v1/credit-packages/' . $package->id)
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Paket kann nicht gelöscht werden, da es bereits von Kunden erworben wurde.');

    expect(CreditPackage::find($package->id))->not->toBeNull();
});

test('trainer cannot delete credit package', function () {
    $package = CreditPackage::factory()->create();

    $this->actingAs($this->trainer)
        ->deleteJson('/api/v1/credit-packages/' . $package->id)
        ->assertForbidden();
});
