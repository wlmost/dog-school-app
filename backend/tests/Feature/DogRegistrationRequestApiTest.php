<?php

declare(strict_types=1);

use App\Mail\DogRegistrationApproved;
use App\Mail\DogRegistrationReceived;
use App\Models\Customer;
use App\Models\Dog;
use App\Models\DogRegistrationRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Setup
// ---------------------------------------------------------------------------

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);

    $this->customerUser = User::factory()->create(['role' => 'customer']);
    $this->customer     = Customer::factory()->create(['user_id' => $this->customerUser->id]);

    $this->otherCustomerUser = User::factory()->create(['role' => 'customer']);
    $this->otherCustomer     = Customer::factory()->create(['user_id' => $this->otherCustomerUser->id]);

    Mail::fake();
});

// ---------------------------------------------------------------------------
// index
// ---------------------------------------------------------------------------

test('admin can list all dog registration requests', function () {
    DogRegistrationRequest::factory()->count(3)->create(['customer_id' => $this->customer->id]);
    DogRegistrationRequest::factory()->count(2)->create(['customer_id' => $this->otherCustomer->id]);

    $this->actingAs($this->admin)
        ->getJson('/api/v1/dog-registration-requests')
        ->assertOk()
        ->assertJsonCount(5, 'data');
});

test('admin can filter requests by status', function () {
    DogRegistrationRequest::factory()->count(3)->create([
        'customer_id' => $this->customer->id,
        'status'      => 'pending',
    ]);
    DogRegistrationRequest::factory()->approved()->create(['customer_id' => $this->customer->id]);

    $response = $this->actingAs($this->admin)
        ->getJson('/api/v1/dog-registration-requests?status=pending')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(3);
});

test('customer sees only their own requests', function () {
    DogRegistrationRequest::factory()->count(2)->create(['customer_id' => $this->customer->id]);
    DogRegistrationRequest::factory()->count(3)->create(['customer_id' => $this->otherCustomer->id]);

    $response = $this->actingAs($this->customerUser)
        ->getJson('/api/v1/dog-registration-requests')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(2);
});

test('unauthenticated user cannot list requests', function () {
    $this->getJson('/api/v1/dog-registration-requests')
        ->assertUnauthorized();
});

// ---------------------------------------------------------------------------
// store
// ---------------------------------------------------------------------------

test('customer can submit a dog registration request', function () {
    $payload = [
        'name'        => 'Buddy',
        'breed'       => 'Golden Retriever',
        'gender'      => 'male',
        'dateOfBirth' => '2021-05-10',
        'neutered'    => false,
        'chipNumber'  => '123456789',
        'notes'       => 'Sehr verspielt',
    ];

    $response = $this->actingAs($this->customerUser)
        ->postJson('/api/v1/dog-registration-requests', $payload)
        ->assertCreated()
        ->assertJsonPath('data.name', 'Buddy')
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.customerId', $this->customer->id);

    $this->assertDatabaseHas('dog_registration_requests', [
        'customer_id' => $this->customer->id,
        'name'        => 'Buddy',
        'status'      => 'pending',
    ]);
});

test('submitting a request sends email to all admins', function () {
    $admin2 = User::factory()->create(['role' => 'admin']);

    $this->actingAs($this->customerUser)
        ->postJson('/api/v1/dog-registration-requests', ['name' => 'Rex'])
        ->assertCreated();

    Mail::assertSent(DogRegistrationReceived::class, 2); // one per admin
});

test('admin cannot submit a dog registration request', function () {
    $this->actingAs($this->admin)
        ->postJson('/api/v1/dog-registration-requests', ['name' => 'Rex'])
        ->assertForbidden();
});

test('store request validates required name', function () {
    $this->actingAs($this->customerUser)
        ->postJson('/api/v1/dog-registration-requests', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

test('store request validates gender enum', function () {
    $this->actingAs($this->customerUser)
        ->postJson('/api/v1/dog-registration-requests', [
            'name'   => 'Rex',
            'gender' => 'unknown',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['gender']);
});

test('store request validates dateOfBirth is not in the future', function () {
    $this->actingAs($this->customerUser)
        ->postJson('/api/v1/dog-registration-requests', [
            'name'        => 'Rex',
            'dateOfBirth' => now()->addDay()->toDateString(),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['dateOfBirth']);
});

it('erstellt eine anfrage mit den drei herkunfts-/übernahmefeldern', function () {
    $payload = [
        'name'              => 'Buddy',
        'breed'             => 'Golden Retriever',
        'gender'            => 'male',
        'dateOfBirth'       => '2021-05-10',
        'ownerSince'        => '2023-03-01',
        'ageAtAcquisition'  => 'ca. 1 Jahr',
        'origin'            => 'private',
    ];

    $response = $this->actingAs($this->customerUser)
        ->postJson('/api/v1/dog-registration-requests', $payload)
        ->assertCreated()
        ->assertJsonPath('data.ownerSince', '2023-03-01')
        ->assertJsonPath('data.ageAtAcquisition', 'ca. 1 Jahr')
        ->assertJsonPath('data.origin', 'private');

    $this->assertDatabaseHas('dog_registration_requests', [
        'customer_id'        => $this->customer->id,
        'name'               => 'Buddy',
        'age_at_acquisition' => 'ca. 1 Jahr',
        'origin'             => 'private',
    ]);

    $createdRequest = DogRegistrationRequest::where('name', 'Buddy')->firstOrFail();
    expect($createdRequest->owner_since->toDateString())->toBe('2023-03-01');
});

it('weist eine anfrage mit ownerSince in der zukunft mit 422 zurück', function () {
    $this->actingAs($this->customerUser)
        ->postJson('/api/v1/dog-registration-requests', [
            'name'        => 'Rex',
            'ownerSince'  => now()->addDay()->toDateString(),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['ownerSince']);
});

it('behandelt einen leeren string als origin bei einer anfrage wie null (globale ConvertEmptyStringsToNull-Middleware)', function () {
    $this->actingAs($this->customerUser)
        ->postJson('/api/v1/dog-registration-requests', [
            'name'   => 'Rex',
            'origin' => '',
        ])
        ->assertCreated()
        ->assertJsonPath('data.origin', null);

    $this->assertDatabaseHas('dog_registration_requests', [
        'name'   => 'Rex',
        'origin' => null,
    ]);
});

// ---------------------------------------------------------------------------
// show
// ---------------------------------------------------------------------------

test('admin can view any registration request', function () {
    $req = DogRegistrationRequest::factory()->create(['customer_id' => $this->customer->id]);

    $this->actingAs($this->admin)
        ->getJson("/api/v1/dog-registration-requests/{$req->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $req->id);
});

it('liefert die drei herkunfts-/übernahmefelder beim anzeigen und auflisten von anfragen', function () {
    $req = DogRegistrationRequest::factory()->create([
        'customer_id'        => $this->customer->id,
        'owner_since'        => '2021-07-01',
        'age_at_acquisition' => 'ca. 5 Jahre',
        'origin'             => 'unknown',
    ]);

    $this->actingAs($this->admin)
        ->getJson("/api/v1/dog-registration-requests/{$req->id}")
        ->assertOk()
        ->assertJsonPath('data.ownerSince', '2021-07-01')
        ->assertJsonPath('data.ageAtAcquisition', 'ca. 5 Jahre')
        ->assertJsonPath('data.origin', 'unknown');

    $this->actingAs($this->admin)
        ->getJson('/api/v1/dog-registration-requests')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'ownerSince', 'ageAtAcquisition', 'origin'],
            ],
        ]);
});

test('customer can view their own request', function () {
    $req = DogRegistrationRequest::factory()->create(['customer_id' => $this->customer->id]);

    $this->actingAs($this->customerUser)
        ->getJson("/api/v1/dog-registration-requests/{$req->id}")
        ->assertOk();
});

test('customer cannot view another customer request', function () {
    $req = DogRegistrationRequest::factory()->create(['customer_id' => $this->otherCustomer->id]);

    $this->actingAs($this->customerUser)
        ->getJson("/api/v1/dog-registration-requests/{$req->id}")
        ->assertForbidden();
});

// ---------------------------------------------------------------------------
// approve
// ---------------------------------------------------------------------------

test('admin can approve a pending request and a dog is created', function () {
    $req = DogRegistrationRequest::factory()->create([
        'customer_id' => $this->customer->id,
        'name'        => 'Bella',
        'breed'       => 'Labrador',
        'gender'      => 'female',
        'neutered'    => true,
    ]);

    $response = $this->actingAs($this->admin)
        ->postJson("/api/v1/dog-registration-requests/{$req->id}/approve")
        ->assertOk()
        ->assertJsonPath('data.name', 'Bella');

    // Dog was created
    $this->assertDatabaseHas('dogs', [
        'customer_id' => $this->customer->id,
        'name'        => 'Bella',
        'is_active'   => true,
    ]);

    // Request is marked approved
    $this->assertDatabaseHas('dog_registration_requests', [
        'id'          => $req->id,
        'status'      => 'approved',
        'reviewed_by' => $this->admin->id,
    ]);
});

test('approving a request sends confirmation email to customer', function () {
    $req = DogRegistrationRequest::factory()->create(['customer_id' => $this->customer->id]);

    $this->actingAs($this->admin)
        ->postJson("/api/v1/dog-registration-requests/{$req->id}/approve")
        ->assertOk();

    Mail::assertSent(DogRegistrationApproved::class, function (DogRegistrationApproved $mail) {
        return $mail->hasTo($this->customerUser->email);
    });
});

test('cannot approve an already approved request', function () {
    $req = DogRegistrationRequest::factory()->approved()->create(['customer_id' => $this->customer->id]);

    $this->actingAs($this->admin)
        ->postJson("/api/v1/dog-registration-requests/{$req->id}/approve")
        ->assertUnprocessable();
});

test('cannot approve a rejected request', function () {
    $req = DogRegistrationRequest::factory()->rejected()->create(['customer_id' => $this->customer->id]);

    $this->actingAs($this->admin)
        ->postJson("/api/v1/dog-registration-requests/{$req->id}/approve")
        ->assertUnprocessable();
});

test('customer cannot approve a request', function () {
    $req = DogRegistrationRequest::factory()->create(['customer_id' => $this->customer->id]);

    $this->actingAs($this->customerUser)
        ->postJson("/api/v1/dog-registration-requests/{$req->id}/approve")
        ->assertForbidden();
});

it('übernimmt die drei herkunfts-/übernahmefelder beim genehmigen in den neuen hund', function () {
    $req = DogRegistrationRequest::factory()->create([
        'customer_id'        => $this->customer->id,
        'name'               => 'Bella',
        'breed'              => 'Labrador',
        'gender'             => 'female',
        'owner_since'        => '2022-11-20',
        'age_at_acquisition' => 'ca. 3 Jahre',
        'origin'             => 'shelter',
    ]);

    $this->actingAs($this->admin)
        ->postJson("/api/v1/dog-registration-requests/{$req->id}/approve")
        ->assertOk()
        ->assertJsonPath('data.name', 'Bella');

    $this->assertDatabaseHas('dogs', [
        'customer_id'        => $this->customer->id,
        'name'               => 'Bella',
        'age_at_acquisition' => 'ca. 3 Jahre',
        'origin'             => 'shelter',
    ]);

    $createdDog = Dog::where('name', 'Bella')->firstOrFail();
    expect($createdDog->owner_since->toDateString())->toBe('2022-11-20');
});

it('erzeugt beim genehmigen einen hund mit null in allen drei herkunfts-/übernahmefeldern wenn die anfrage sie nicht gesetzt hat', function () {
    $req = DogRegistrationRequest::factory()->create([
        'customer_id'        => $this->customer->id,
        'name'               => 'Bella',
        'breed'              => 'Labrador',
        'gender'             => 'female',
        'owner_since'        => null,
        'age_at_acquisition' => null,
        'origin'             => null,
    ]);

    $this->actingAs($this->admin)
        ->postJson("/api/v1/dog-registration-requests/{$req->id}/approve")
        ->assertOk()
        ->assertJsonPath('data.name', 'Bella');

    $createdDog = Dog::where('name', 'Bella')->firstOrFail();
    expect($createdDog->owner_since)->toBeNull();
    expect($createdDog->age_at_acquisition)->toBeNull();
    expect($createdDog->origin)->toBeNull();
});

// ---------------------------------------------------------------------------
// reject
// ---------------------------------------------------------------------------

test('admin can reject a pending request', function () {
    $req = DogRegistrationRequest::factory()->create(['customer_id' => $this->customer->id]);

    $this->actingAs($this->admin)
        ->postJson("/api/v1/dog-registration-requests/{$req->id}/reject")
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected');

    $this->assertDatabaseHas('dog_registration_requests', [
        'id'          => $req->id,
        'status'      => 'rejected',
        'reviewed_by' => $this->admin->id,
    ]);

    // No dog was created
    $this->assertDatabaseMissing('dogs', ['customer_id' => $this->customer->id]);
});

test('cannot reject an already approved request', function () {
    $req = DogRegistrationRequest::factory()->approved()->create(['customer_id' => $this->customer->id]);

    $this->actingAs($this->admin)
        ->postJson("/api/v1/dog-registration-requests/{$req->id}/reject")
        ->assertUnprocessable();
});

test('customer cannot reject a request', function () {
    $req = DogRegistrationRequest::factory()->create(['customer_id' => $this->customer->id]);

    $this->actingAs($this->customerUser)
        ->postJson("/api/v1/dog-registration-requests/{$req->id}/reject")
        ->assertForbidden();
});

// ---------------------------------------------------------------------------
// Model helpers
// ---------------------------------------------------------------------------

test('DogRegistrationRequest isPending returns true for pending status', function () {
    $req = new DogRegistrationRequest(['status' => 'pending']);
    expect($req->isPending())->toBeTrue();
    expect($req->isApproved())->toBeFalse();
    expect($req->isRejected())->toBeFalse();
});

test('DogRegistrationRequest isApproved returns true for approved status', function () {
    $req = new DogRegistrationRequest(['status' => 'approved']);
    expect($req->isApproved())->toBeTrue();
    expect($req->isPending())->toBeFalse();
});

test('DogRegistrationRequest isRejected returns true for rejected status', function () {
    $req = new DogRegistrationRequest(['status' => 'rejected']);
    expect($req->isRejected())->toBeTrue();
    expect($req->isPending())->toBeFalse();
});
