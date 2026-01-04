<?php

declare(strict_types=1);

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Dog;
use App\Models\TrainingLog;
use App\Models\User;
use App\Models\Vaccination;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Dog API - Index', function () {
    test('admin can list all dogs', function () {
        $admin = User::factory()->admin()->create();
        Dog::factory()->count(3)->create();

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/dogs');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'customerId',
                        'name',
                        'breed',
                        'dateOfBirth',
                        'gender',
                        'customer',
                    ],
                ],
                'links',
                'meta',
            ]);
    });

    test('trainer can list all dogs', function () {
        $trainer = User::factory()->trainer()->create();
        
        // Create customer assigned to this trainer with dogs
        $assignedCustomer = Customer::factory()->create(['trainer_id' => $trainer->id]);
        Dog::factory()->count(2)->create(['customer_id' => $assignedCustomer->id]);

        $response = $this->actingAs($trainer)
            ->getJson('/api/v1/dogs');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    });

    test('customer cannot list all dogs', function () {
        $customerUser = User::factory()->customer()->create();
        $customer = Customer::factory()->for($customerUser, 'user')->create();
        
        // Create own dogs
        Dog::factory()->count(2)->create(['customer_id' => $customer->id]);
        
        // Create other dogs
        Dog::factory()->count(3)->create();

        $response = $this->actingAs($customerUser)
            ->getJson('/api/v1/dogs');

        // Customer can list but only sees their own dogs (2)
        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    });

    test('unauthenticated user cannot list dogs', function () {
        $response = $this->getJson('/api/v1/dogs');

        $response->assertStatus(401);
    });

    test('can filter dogs by customer', function () {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->create();
        Dog::factory()->count(2)->for($customer)->create();
        Dog::factory()->count(3)->create(); // Other dogs

        $response = $this->actingAs($admin)
            ->getJson("/api/v1/dogs?customerId={$customer->id}");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    });

    test('can filter dogs by search term', function () {
        $admin = User::factory()->admin()->create();
        Dog::factory()->create(['name' => 'Max', 'breed' => 'Labrador']);
        Dog::factory()->create(['name' => 'Bella', 'breed' => 'German Shepherd']);
        Dog::factory()->create(['name' => 'Charlie', 'breed' => 'Beagle']);

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/dogs?search=Max');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Max');
    });

    test('can filter dogs by active status', function () {
        $admin = User::factory()->admin()->create();
        Dog::factory()->count(2)->create(['is_active' => true]);
        Dog::factory()->create(['is_active' => false]);

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/dogs?isActive=1');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    });

    test('can filter dogs by breed', function () {
        $admin = User::factory()->admin()->create();
        Dog::factory()->create(['breed' => 'Labrador']);
        Dog::factory()->create(['breed' => 'Golden Retriever']);
        Dog::factory()->create(['breed' => 'Beagle']);

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/dogs?breed=Labrador');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    });

    test('trainer can only see dogs from assigned customers', function () {
        $trainer = User::factory()->trainer()->create();
        
        // Create customers assigned to this trainer
        $assignedCustomer1 = Customer::factory()->create(['trainer_id' => $trainer->id]);
        $assignedCustomer2 = Customer::factory()->create(['trainer_id' => $trainer->id]);
        
        // Create dogs for assigned customers
        Dog::factory()->count(2)->create(['customer_id' => $assignedCustomer1->id]);
        Dog::factory()->count(1)->create(['customer_id' => $assignedCustomer2->id]);
        
        // Create dogs for other customers (different trainer)
        $otherTrainer = User::factory()->trainer()->create();
        $otherCustomer = Customer::factory()->create(['trainer_id' => $otherTrainer->id]);
        Dog::factory()->count(3)->create(['customer_id' => $otherCustomer->id]);

        $response = $this->actingAs($trainer)
            ->getJson('/api/v1/dogs');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data'); // Only see 3 dogs from assigned customers
    });

    test('customer can only see their own dogs', function () {
        $customerUser = User::factory()->customer()->create();
        $customer = Customer::factory()->for($customerUser, 'user')->create();
        
        // Create dogs for this customer
        Dog::factory()->count(2)->create(['customer_id' => $customer->id]);
        
        // Create dogs for other customers
        Dog::factory()->count(5)->create();

        $response = $this->actingAs($customerUser)
            ->getJson('/api/v1/dogs');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data'); // Only see own 2 dogs
        
        // Verify all dogs belong to this customer
        foreach ($response->json('data') as $dog) {
            expect($dog['customerId'])->toBe($customer->id);
        }
    });

    test('customer without customer record sees no dogs', function () {
        $customerUserWithoutRecord = User::factory()->customer()->create();
        
        // Create some dogs for other customers
        Dog::factory()->count(5)->create();

        $response = $this->actingAs($customerUserWithoutRecord)
            ->getJson('/api/v1/dogs');

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data');
    });

    test('admin can see all dogs regardless of trainer assignment', function () {
        $admin = User::factory()->admin()->create();
        
        // Create dogs for different trainers
        $trainer1 = User::factory()->trainer()->create();
        $trainer2 = User::factory()->trainer()->create();
        
        $customer1 = Customer::factory()->create(['trainer_id' => $trainer1->id]);
        $customer2 = Customer::factory()->create(['trainer_id' => $trainer2->id]);
        
        Dog::factory()->count(3)->create(['customer_id' => $customer1->id]);
        Dog::factory()->count(2)->create(['customer_id' => $customer2->id]);

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/dogs');

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data'); // Admin sees all dogs
    });
});

describe('Dog API - Show', function () {
    test('admin can view any dog', function () {
        $admin = User::factory()->admin()->create();
        $dog = Dog::factory()->create();

        $response = $this->actingAs($admin)
            ->getJson("/api/v1/dogs/{$dog->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $dog->id)
            ->assertJsonPath('data.name', $dog->name);
    });

    test('trainer can view any dog', function () {
        $trainer = User::factory()->trainer()->create();
        $dog = Dog::factory()->create();

        $response = $this->actingAs($trainer)
            ->getJson("/api/v1/dogs/{$dog->id}");

        $response->assertStatus(200);
    });

    test('customer can view own dog', function () {
        $customerUser = User::factory()->customer()->create();
        $customer = Customer::factory()->for($customerUser, 'user')->create();
        $dog = Dog::factory()->for($customer)->create();

        $response = $this->actingAs($customerUser)
            ->getJson("/api/v1/dogs/{$dog->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $dog->id);
    });

    test('customer cannot view other customers dogs', function () {
        $customerUser = User::factory()->customer()->create();
        Customer::factory()->for($customerUser, 'user')->create();
        $otherDog = Dog::factory()->create();

        $response = $this->actingAs($customerUser)
            ->getJson("/api/v1/dogs/{$otherDog->id}");

        $response->assertStatus(403);
    });
});

describe('Dog API - Store', function () {
    test('admin can create dog', function () {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->create();

        $data = [
            'customerId' => $customer->id,
            'name' => 'Max',
            'breed' => 'Labrador',
            'dateOfBirth' => '2020-05-15',
            'gender' => 'male',
            'chipNumber' => 'ABC123456789',
            'weight' => 30.5,
            'color' => 'Golden',
            'isActive' => true,
        ];

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/dogs', $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Max')
            ->assertJsonPath('data.breed', 'Labrador');

        $this->assertDatabaseHas('dogs', [
            'name' => 'Max',
            'chip_number' => 'ABC123456789',
        ]);
    });

    test('trainer can create dog', function () {
        $trainer = User::factory()->trainer()->create();
        $customer = Customer::factory()->create();

        $data = [
            'customerId' => $customer->id,
            'name' => 'Bella',
            'breed' => 'German Shepherd',
            'dateOfBirth' => '2019-08-20',
            'gender' => 'female',
        ];

        $response = $this->actingAs($trainer)
            ->postJson('/api/v1/dogs', $data);

        $response->assertStatus(201);
    });

    test('customer cannot create dog', function () {
        $customerUser = User::factory()->customer()->create();
        $customer = Customer::factory()->for($customerUser, 'user')->create();

        $data = [
            'customerId' => $customer->id,
            'name' => 'Charlie',
            'breed' => 'Beagle',
            'dateOfBirth' => '2021-03-10',
            'gender' => 'male',
        ];

        $response = $this->actingAs($customerUser)
            ->postJson('/api/v1/dogs', $data);

        $response->assertStatus(403);
    });

    test('validates required fields', function () {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/dogs', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['customerId', 'name', 'breed', 'dateOfBirth', 'gender']);
    });

    test('validates chip number uniqueness', function () {
        $admin = User::factory()->admin()->create();
        Dog::factory()->create(['chip_number' => 'UNIQUE123']);

        $data = [
            'customerId' => Customer::factory()->create()->id,
            'name' => 'Test',
            'breed' => 'Test Breed',
            'dateOfBirth' => '2020-01-01',
            'gender' => 'male',
            'chipNumber' => 'UNIQUE123',
        ];

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/dogs', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['chipNumber']);
    });

    test('validates date of birth is in the past', function () {
        $admin = User::factory()->admin()->create();

        $data = [
            'customerId' => Customer::factory()->create()->id,
            'name' => 'Future Dog',
            'breed' => 'Test',
            'dateOfBirth' => now()->addDays(1)->toDateString(),
            'gender' => 'male',
        ];

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/dogs', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['dateOfBirth']);
    });
});

describe('Dog API - Update', function () {
    test('admin can update any dog', function () {
        $admin = User::factory()->admin()->create();
        $dog = Dog::factory()->create(['name' => 'Old Name']);

        $response = $this->actingAs($admin)
            ->patchJson("/api/v1/dogs/{$dog->id}", [
                'name' => 'New Name',
                'weight' => 35.0,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'New Name');

        $this->assertDatabaseHas('dogs', [
            'id' => $dog->id,
            'name' => 'New Name',
        ]);
    });

    test('trainer can update any dog', function () {
        $trainer = User::factory()->trainer()->create();
        $dog = Dog::factory()->create();

        $response = $this->actingAs($trainer)
            ->patchJson("/api/v1/dogs/{$dog->id}", [
                'color' => 'Black',
            ]);

        $response->assertStatus(200);
    });

    test('customer cannot update dogs', function () {
        $customerUser = User::factory()->customer()->create();
        $customer = Customer::factory()->for($customerUser, 'user')->create();
        $dog = Dog::factory()->for($customer)->create();

        $response = $this->actingAs($customerUser)
            ->patchJson("/api/v1/dogs/{$dog->id}", [
                'name' => 'New Name',
            ]);

        $response->assertStatus(403);
    });
});

describe('Dog API - Delete', function () {
    test('admin can delete dog without active bookings', function () {
        $admin = User::factory()->admin()->create();
        $dog = Dog::factory()->create();

        $response = $this->actingAs($admin)
            ->deleteJson("/api/v1/dogs/{$dog->id}");

        $response->assertStatus(204);
        $this->assertSoftDeleted('dogs', ['id' => $dog->id]);
    });

    test('cannot delete dog with active bookings', function () {
        $admin = User::factory()->admin()->create();
        $dog = Dog::factory()->create();
        Booking::factory()->for($dog)->create(['status' => 'confirmed']);

        $response = $this->actingAs($admin)
            ->deleteJson("/api/v1/dogs/{$dog->id}");

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Cannot delete dog with active bookings.',
            ]);
    });

    test('trainer cannot delete dog', function () {
        $trainer = User::factory()->trainer()->create();
        $dog = Dog::factory()->create();

        $response = $this->actingAs($trainer)
            ->deleteJson("/api/v1/dogs/{$dog->id}");

        $response->assertStatus(403);
    });

    test('customer cannot delete dog', function () {
        $customerUser = User::factory()->customer()->create();
        $customer = Customer::factory()->for($customerUser, 'user')->create();
        $dog = Dog::factory()->for($customer)->create();

        $response = $this->actingAs($customerUser)
            ->deleteJson("/api/v1/dogs/{$dog->id}");

        $response->assertStatus(403);
    });
});

describe('Dog API - Related Resources', function () {
    test('can get dog vaccinations', function () {
        $admin = User::factory()->admin()->create();
        $dog = Dog::factory()->create();
        Vaccination::factory()->count(3)->for($dog)->create();

        $response = $this->actingAs($admin)
            ->getJson("/api/v1/dogs/{$dog->id}/vaccinations");

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    });

    test('can get dog training logs', function () {
        $admin = User::factory()->admin()->create();
        $dog = Dog::factory()->create();
        TrainingLog::factory()->count(2)->for($dog)->create();

        $response = $this->actingAs($admin)
            ->getJson("/api/v1/dogs/{$dog->id}/training-logs");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    });

    test('can get dog bookings', function () {
        $admin = User::factory()->admin()->create();
        $dog = Dog::factory()->create();
        Booking::factory()->count(4)->for($dog)->create();

        $response = $this->actingAs($admin)
            ->getJson("/api/v1/dogs/{$dog->id}/bookings");

        $response->assertStatus(200)
            ->assertJsonCount(4, 'data');
    });

    test('customer can view own dog vaccinations', function () {
        $customerUser = User::factory()->customer()->create();
        $customer = Customer::factory()->for($customerUser, 'user')->create();
        $dog = Dog::factory()->for($customer)->create();
        Vaccination::factory()->count(2)->for($dog)->create();

        $response = $this->actingAs($customerUser)
            ->getJson("/api/v1/dogs/{$dog->id}/vaccinations");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    });
});
