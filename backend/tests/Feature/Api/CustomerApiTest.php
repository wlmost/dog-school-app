<?php

declare(strict_types=1);

use App\Models\Booking;
use App\Models\Customer;
use App\Models\CustomerCredit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Customer API - Index', function () {
    test('admin can list all customers', function () {
        $admin = User::factory()->admin()->create();
        $customers = Customer::factory()->count(3)->create();

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/customers');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'userId',
                        'addressLine1',
                        'city',
                        'fullAddress',
                        'createdAt',
                        'user' => ['id', 'email', 'fullName'],
                    ],
                ],
                'links',
                'meta',
            ]);
    });

    test('trainer can list all customers', function () {
        $trainer = User::factory()->trainer()->create();
        Customer::factory()->count(2)->create();

        $response = $this->actingAs($trainer)
            ->getJson('/api/v1/customers');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    });

    test('customer cannot list all customers', function () {
        $customerUser = User::factory()->customer()->create();
        $customer = Customer::factory()->create(['user_id' => $customerUser->id]);

        $response = $this->actingAs($customerUser)
            ->getJson('/api/v1/customers');

        $response->assertStatus(403);
    });

    test('unauthenticated user cannot list customers', function () {
        $response = $this->getJson('/api/v1/customers');
        $response->assertStatus(401);
    });

    test('can filter customers by search term', function () {
        $admin = User::factory()->admin()->create();
        
        $user1 = User::factory()->create(['first_name' => 'John', 'last_name' => 'Doe']);
        $customer1 = Customer::factory()->create(['user_id' => $user1->id]);
        
        $user2 = User::factory()->create(['first_name' => 'Jane', 'last_name' => 'Smith']);
        $customer2 = Customer::factory()->create(['user_id' => $user2->id]);

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/customers?search=John');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.user.firstName', 'John');
    });

    test('can filter customers with active credits', function () {
        $admin = User::factory()->admin()->create();
        
        $customer1 = Customer::factory()->create();
        $customer2 = Customer::factory()->create();
        
        CustomerCredit::factory()->active()->create([
            'customer_id' => $customer1->id,
            'remaining_credits' => 5,
        ]);

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/customers?hasActiveCredits=true');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    });
});

describe('Customer API - Show', function () {
    test('admin can view any customer', function () {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->create();

        $response = $this->actingAs($admin)
            ->getJson("/api/v1/customers/{$customer->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $customer->id)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'userId',
                    'fullAddress',
                    'user',
                    'dogs',
                    'bookings',
                    'credits',
                    'invoices',
                ],
            ]);
    });

    test('trainer can view any customer', function () {
        $trainer = User::factory()->trainer()->create();
        $customer = Customer::factory()->create();

        $response = $this->actingAs($trainer)
            ->getJson("/api/v1/customers/{$customer->id}");

        $response->assertStatus(200);
    });

    test('customer can view own profile', function () {
        $user = User::factory()->customer()->create();
        $customer = Customer::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/customers/{$customer->id}");

        $response->assertStatus(200);
    });

    test('customer cannot view other customers', function () {
        $user = User::factory()->customer()->create();
        $myCustomer = Customer::factory()->create(['user_id' => $user->id]);
        $otherCustomer = Customer::factory()->create();

        $response = $this->actingAs($user)
            ->getJson("/api/v1/customers/{$otherCustomer->id}");

        $response->assertStatus(403);
    });
});

describe('Customer API - Store', function () {
    test('admin can create customer', function () {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->customer()->create();

        $data = [
            'userId' => $user->id,
            'addressLine1' => '123 Main St',
            'postalCode' => '12345',
            'city' => 'Berlin',
            'country' => 'Germany',
        ];

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/customers', $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.userId', $user->id)
            ->assertJsonPath('data.city', 'Berlin');

        $this->assertDatabaseHas('customers', [
            'user_id' => $user->id,
            'city' => 'Berlin',
        ]);
    });

    test('trainer can create customer', function () {
        $trainer = User::factory()->trainer()->create();
        $user = User::factory()->customer()->create();

        $data = [
            'userId' => $user->id,
            'city' => 'Munich',
        ];

        $response = $this->actingAs($trainer)
            ->postJson('/api/v1/customers', $data);

        $response->assertStatus(201);
    });

    test('customer cannot create customer', function () {
        $customerUser = User::factory()->customer()->create();
        $newUser = User::factory()->customer()->create();

        $data = [
            'userId' => $newUser->id,
        ];

        $response = $this->actingAs($customerUser)
            ->postJson('/api/v1/customers', $data);

        $response->assertStatus(403);
    });

    test('cannot create customer with duplicate user_id', function () {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->customer()->create();
        Customer::factory()->create(['user_id' => $user->id]);

        $data = [
            'userId' => $user->id,
        ];

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/customers', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['userId']);
    });

    test('validates required user_id field', function () {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/customers', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['userId']);
    });
});

describe('Customer API - Update', function () {
    test('admin can update any customer', function () {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->create(['city' => 'Berlin']);

        $data = [
            'city' => 'Munich',
            'postalCode' => '80331',
        ];

        $response = $this->actingAs($admin)
            ->putJson("/api/v1/customers/{$customer->id}", $data);

        $response->assertStatus(200)
            ->assertJsonPath('data.city', 'Munich')
            ->assertJsonPath('data.postalCode', '80331');

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'city' => 'Munich',
        ]);
    });

    test('trainer can update any customer', function () {
        $trainer = User::factory()->trainer()->create();
        $customer = Customer::factory()->create();

        $response = $this->actingAs($trainer)
            ->putJson("/api/v1/customers/{$customer->id}", ['city' => 'Hamburg']);

        $response->assertStatus(200);
    });

    test('customer can update own profile', function () {
        $user = User::factory()->customer()->create();
        $customer = Customer::factory()->create(['user_id' => $user->id, 'city' => 'Berlin']);

        $response = $this->actingAs($user)
            ->putJson("/api/v1/customers/{$customer->id}", ['city' => 'Frankfurt']);

        $response->assertStatus(200)
            ->assertJsonPath('data.city', 'Frankfurt');
    });

    test('customer cannot update other customers', function () {
        $user = User::factory()->customer()->create();
        $myCustomer = Customer::factory()->create(['user_id' => $user->id]);
        $otherCustomer = Customer::factory()->create();

        $response = $this->actingAs($user)
            ->putJson("/api/v1/customers/{$otherCustomer->id}", ['city' => 'Cologne']);

        $response->assertStatus(403);
    });
});

describe('Customer API - Delete', function () {
    test('admin can delete customer without active bookings or credits', function () {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->create();

        $response = $this->actingAs($admin)
            ->deleteJson("/api/v1/customers/{$customer->id}");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Kunde erfolgreich gelöscht.']);

        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
    });

    test('cannot delete customer with active bookings', function () {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->create();
        
        Booking::factory()->confirmed()->create(['customer_id' => $customer->id]);

        $response = $this->actingAs($admin)
            ->deleteJson("/api/v1/customers/{$customer->id}");

        $response->assertStatus(422)
            ->assertJson(['message' => 'Kunde kann nicht gelöscht werden, da aktive Buchungen vorhanden sind.']);

        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
    });

    test('cannot delete customer with active credits', function () {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->create();
        
        CustomerCredit::factory()->active()->create([
            'customer_id' => $customer->id,
            'remaining_credits' => 5,
        ]);

        $response = $this->actingAs($admin)
            ->deleteJson("/api/v1/customers/{$customer->id}");

        $response->assertStatus(422)
            ->assertJson(['message' => 'Kunde kann nicht gelöscht werden, da aktive Guthaben vorhanden sind.']);
    });

    test('trainer cannot delete customer', function () {
        $trainer = User::factory()->trainer()->create();
        $customer = Customer::factory()->create();

        $response = $this->actingAs($trainer)
            ->deleteJson("/api/v1/customers/{$customer->id}");

        $response->assertStatus(403);
    });
});

describe('Customer API - Related Resources', function () {
    test('can get customer dogs', function () {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->hasDogs(3)->create();

        $response = $this->actingAs($admin)
            ->getJson("/api/v1/customers/{$customer->id}/dogs");

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    });

    test('can get customer bookings', function () {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->hasBookings(2)->create();

        $response = $this->actingAs($admin)
            ->getJson("/api/v1/customers/{$customer->id}/bookings");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    });

    test('can get customer invoices', function () {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->hasInvoices(2)->create();

        $response = $this->actingAs($admin)
            ->getJson("/api/v1/customers/{$customer->id}/invoices");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    });

    test('can get customer credits', function () {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->hasCredits(2)->create();

        $response = $this->actingAs($admin)
            ->getJson("/api/v1/customers/{$customer->id}/credits");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    });
});
