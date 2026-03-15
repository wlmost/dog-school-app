<?php

declare(strict_types=1);

use App\Models\CreditPackage;
use App\Models\Customer;
use App\Models\CustomerCredit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->trainer = User::factory()->create(['role' => 'trainer']);
    $this->customerUser = User::factory()->create(['role' => 'customer']);
    $this->customer = Customer::factory()->create(['user_id' => $this->customerUser->id]);
    $this->package = CreditPackage::factory()->create([
        'total_credits' => 10,
        'price' => 150.00,
    ]);
});

test('admin can list all customer credits', function () {
    CustomerCredit::factory()->count(5)->create();

    $this->actingAs($this->admin)
        ->getJson('/api/v1/customer-credits')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'customer',
                    'package',
                    'totalCredits',
                    'remainingCredits',
                    'purchaseDate',
                    'expirationDate',
                    'status',
                ],
            ],
        ]);
});

test('customer can list their own credits', function () {
    CustomerCredit::factory()->count(2)->create(['customer_id' => $this->customer->id]);
    CustomerCredit::factory()->count(3)->create(); // Other credits

    $response = $this->actingAs($this->customerUser)
        ->getJson('/api/v1/customer-credits?customerId=' . $this->customer->id)
        ->assertOk();

    expect($response->json('data'))->toHaveCount(2);
});

test('credits can be filtered by status', function () {
    CustomerCredit::factory()->count(2)->create(['status' => 'active']);
    CustomerCredit::factory()->count(3)->create(['status' => 'used']);

    $response = $this->actingAs($this->admin)
        ->getJson('/api/v1/customer-credits?status=active')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(2);
});

test('can get active credits for customer', function () {
    // Active credits
    CustomerCredit::factory()->count(2)->create([
        'customer_id' => $this->customer->id,
        'status' => 'active',
        'remaining_credits' => 5,
    ]);
    
    // Used credits
    CustomerCredit::factory()->create([
        'customer_id' => $this->customer->id,
        'status' => 'used',
        'remaining_credits' => 0,
    ]);

    $response = $this->actingAs($this->customerUser)
        ->getJson('/api/v1/customer-credits/active/list?customerId=' . $this->customer->id)
        ->assertOk();

    expect($response->json('data'))->toHaveCount(2);
});

test('trainer can view any customer credit', function () {
    $credit = CustomerCredit::factory()->create();

    $this->actingAs($this->trainer)
        ->getJson('/api/v1/customer-credits/' . $credit->id)
        ->assertOk()
        ->assertJsonPath('data.id', $credit->id);
});

test('customer can view their own credit', function () {
    $credit = CustomerCredit::factory()->create(['customer_id' => $this->customer->id]);

    $this->actingAs($this->customerUser)
        ->getJson('/api/v1/customer-credits/' . $credit->id)
        ->assertOk()
        ->assertJsonPath('data.id', $credit->id);
});

test('customer cannot view other customers credit', function () {
    $otherCredit = CustomerCredit::factory()->create();

    $this->actingAs($this->customerUser)
        ->getJson('/api/v1/customer-credits/' . $otherCredit->id)
        ->assertForbidden();
});

test('trainer can create customer credit purchase', function () {
    $data = [
        'customerId' => $this->customer->id,
        'creditPackageId' => $this->package->id,
        'totalCredits' => 10,
        'remainingCredits' => 10,
        'purchaseDate' => now()->format('Y-m-d'),
        'expirationDate' => now()->addDays(180)->format('Y-m-d'),
    ];

    $this->actingAs($this->trainer)
        ->postJson('/api/v1/customer-credits', $data)
        ->assertCreated()
        ->assertJsonPath('data.totalCredits', 10)
        ->assertJsonPath('data.remainingCredits', 10)
        ->assertJsonPath('data.status', 'active');

    $this->assertDatabaseHas('customer_credits', [
        'customer_id' => $this->customer->id,
        'credit_package_id' => $this->package->id,
        'total_credits' => 10,
        'remaining_credits' => 10,
        'status' => 'active',
    ]);
});

test('customer cannot create credit purchase', function () {
    $data = [
        'customerId' => $this->customer->id,
        'creditPackageId' => $this->package->id,
        'totalCredits' => 10,
        'remainingCredits' => 10,
        'purchaseDate' => now()->format('Y-m-d'),
    ];

    $this->actingAs($this->customerUser)
        ->postJson('/api/v1/customer-credits', $data)
        ->assertForbidden();
});

test('credit creation validates required fields', function () {
    $this->actingAs($this->trainer)
        ->postJson('/api/v1/customer-credits', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['customerId', 'creditPackageId', 'totalCredits', 'remainingCredits', 'purchaseDate']);
});

test('remaining credits cannot exceed total credits', function () {
    $data = [
        'customerId' => $this->customer->id,
        'creditPackageId' => $this->package->id,
        'totalCredits' => 10,
        'remainingCredits' => 15,
        'purchaseDate' => now()->format('Y-m-d'),
    ];

    $this->actingAs($this->trainer)
        ->postJson('/api/v1/customer-credits', $data)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['remainingCredits']);
});

test('expiration date must be after purchase date', function () {
    $data = [
        'customerId' => $this->customer->id,
        'creditPackageId' => $this->package->id,
        'totalCredits' => 10,
        'remainingCredits' => 10,
        'purchaseDate' => now()->format('Y-m-d'),
        'expirationDate' => now()->subDays(5)->format('Y-m-d'),
    ];

    $this->actingAs($this->trainer)
        ->postJson('/api/v1/customer-credits', $data)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['expirationDate']);
});

test('trainer can update customer credit', function () {
    $credit = CustomerCredit::factory()->create();

    $this->actingAs($this->trainer)
        ->putJson('/api/v1/customer-credits/' . $credit->id, [
            'remainingCredits' => 5,
        ])
        ->assertOk()
        ->assertJsonPath('data.remainingCredits', 5);

    $this->assertDatabaseHas('customer_credits', [
        'id' => $credit->id,
        'remaining_credits' => 5,
    ]);
});

test('customer cannot update credit', function () {
    $credit = CustomerCredit::factory()->create(['customer_id' => $this->customer->id]);

    $this->actingAs($this->customerUser)
        ->putJson('/api/v1/customer-credits/' . $credit->id, [
            'remainingCredits' => 100,
        ])
        ->assertForbidden();
});

test('can use credit from active package', function () {
    $credit = CustomerCredit::factory()->create([
        'customer_id' => $this->customer->id,
        'remaining_credits' => 10,
        'status' => 'active',
    ]);

    $this->actingAs($this->trainer)
        ->postJson('/api/v1/customer-credits/' . $credit->id . '/use')
        ->assertOk()
        ->assertJsonPath('data.remainingCredits', 9);

    $this->assertDatabaseHas('customer_credits', [
        'id' => $credit->id,
        'remaining_credits' => 9,
        'status' => 'active',
    ]);
});

test('credit status changes to used when depleted', function () {
    $credit = CustomerCredit::factory()->create([
        'total_credits' => 1,
        'remaining_credits' => 1,
        'status' => 'active',
    ]);

    $this->actingAs($this->trainer)
        ->postJson('/api/v1/customer-credits/' . $credit->id . '/use')
        ->assertOk()
        ->assertJsonPath('data.remainingCredits', 0)
        ->assertJsonPath('data.status', 'used');

    $this->assertDatabaseHas('customer_credits', [
        'id' => $credit->id,
        'remaining_credits' => 0,
        'status' => 'used',
    ]);
});

test('cannot use credit when no credits remaining', function () {
    $credit = CustomerCredit::factory()->create([
        'remaining_credits' => 0,
        'status' => 'used',
    ]);

    $this->actingAs($this->trainer)
        ->postJson('/api/v1/customer-credits/' . $credit->id . '/use')
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Keine Einheiten mehr verfügbar.');
});

test('admin can delete unused credit', function () {
    $credit = CustomerCredit::factory()->create([
        'total_credits' => 10,
        'remaining_credits' => 10,
    ]);

    $this->actingAs($this->admin)
        ->deleteJson('/api/v1/customer-credits/' . $credit->id)
        ->assertNoContent();

    expect(CustomerCredit::find($credit->id))->toBeNull();
});

test('cannot delete partially used credit', function () {
    $credit = CustomerCredit::factory()->create([
        'total_credits' => 10,
        'remaining_credits' => 5,
    ]);

    $this->actingAs($this->admin)
        ->deleteJson('/api/v1/customer-credits/' . $credit->id)
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Guthaben kann nicht gelöscht werden, da bereits Einheiten verwendet wurden.');
});

test('trainer cannot delete credit', function () {
    $credit = CustomerCredit::factory()->create();

    $this->actingAs($this->trainer)
        ->deleteJson('/api/v1/customer-credits/' . $credit->id)
        ->assertForbidden();
});
