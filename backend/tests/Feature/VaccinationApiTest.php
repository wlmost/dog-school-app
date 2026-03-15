<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Dog;
use App\Models\User;
use App\Models\Vaccination;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->trainer = User::factory()->create(['role' => 'trainer']);
    $this->customerUser = User::factory()->create(['role' => 'customer']);
    $this->customer = Customer::factory()->create(['user_id' => $this->customerUser->id]);
    $this->dog = Dog::factory()->create(['customer_id' => $this->customer->id]);
});

test('admin can list all vaccinations', function () {
    Vaccination::factory()->count(5)->create();

    $this->actingAs($this->admin)
        ->getJson('/api/v1/vaccinations')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'dog',
                    'vaccinationType',
                    'vaccinationDate',
                    'nextDueDate',
                    'veterinarian',
                ],
            ],
        ]);
});

test('customer can list their dogs vaccinations', function () {
    Vaccination::factory()->count(2)->create(['dog_id' => $this->dog->id]);
    Vaccination::factory()->count(3)->create(); // Other vaccinations

    $response = $this->actingAs($this->customerUser)
        ->getJson('/api/v1/vaccinations?dogId=' . $this->dog->id)
        ->assertOk();

    expect($response->json('data'))->toHaveCount(2);
});

test('vaccinations can be filtered by type', function () {
    Vaccination::factory()->count(2)->create(['vaccination_type' => 'Rabies']);
    Vaccination::factory()->count(3)->create(['vaccination_type' => 'Distemper']);

    $response = $this->actingAs($this->admin)
        ->getJson('/api/v1/vaccinations?vaccinationType=Rabies')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(2);
});

test('can get upcoming vaccinations due soon', function () {
    // Vaccinations due within 30 days
    Vaccination::factory()->count(2)->create([
        'next_due_date' => now()->addDays(15),
    ]);
    
    // Vaccinations not due soon
    Vaccination::factory()->count(3)->create([
        'next_due_date' => now()->addDays(60),
    ]);

    $response = $this->actingAs($this->admin)
        ->getJson('/api/v1/vaccinations/upcoming/list')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(2);
});

test('can get overdue vaccinations', function () {
    // Overdue vaccinations
    Vaccination::factory()->count(3)->create([
        'next_due_date' => now()->subDays(10),
    ]);
    
    // Not overdue
    Vaccination::factory()->count(2)->create([
        'next_due_date' => now()->addDays(30),
    ]);

    $response = $this->actingAs($this->admin)
        ->getJson('/api/v1/vaccinations/overdue/list')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(3);
});

test('trainer can view any vaccination', function () {
    $vaccination = Vaccination::factory()->create();

    $this->actingAs($this->trainer)
        ->getJson('/api/v1/vaccinations/' . $vaccination->id)
        ->assertOk()
        ->assertJsonPath('data.id', $vaccination->id);
});

test('customer can view their own dogs vaccination', function () {
    $vaccination = Vaccination::factory()->create(['dog_id' => $this->dog->id]);

    $this->actingAs($this->customerUser)
        ->getJson('/api/v1/vaccinations/' . $vaccination->id)
        ->assertOk()
        ->assertJsonPath('data.id', $vaccination->id);
});

test('customer cannot view other dogs vaccination', function () {
    $otherVaccination = Vaccination::factory()->create();

    $this->actingAs($this->customerUser)
        ->getJson('/api/v1/vaccinations/' . $otherVaccination->id)
        ->assertForbidden();
});

test('trainer can create vaccination', function () {
    $data = [
        'dogId' => $this->dog->id,
        'vaccinationType' => 'Rabies',
        'vaccinationDate' => now()->format('Y-m-d'),
        'nextDueDate' => now()->addYear()->format('Y-m-d'),
        'veterinarian' => 'Dr. Smith',
    ];

    $this->actingAs($this->trainer)
        ->postJson('/api/v1/vaccinations', $data)
        ->assertCreated()
        ->assertJsonPath('data.vaccinationType', 'Rabies')
        ->assertJsonPath('data.veterinarian', 'Dr. Smith');

    $this->assertDatabaseHas('vaccinations', [
        'dog_id' => $this->dog->id,
        'vaccination_type' => 'Rabies',
        'veterinarian' => 'Dr. Smith',
    ]);
});

test('customer cannot create vaccination', function () {
    $data = [
        'dogId' => $this->dog->id,
        'vaccinationType' => 'Rabies',
        'vaccinationDate' => now()->format('Y-m-d'),
    ];

    $this->actingAs($this->customerUser)
        ->postJson('/api/v1/vaccinations', $data)
        ->assertForbidden();
});

test('vaccination creation validates required fields', function () {
    $this->actingAs($this->trainer)
        ->postJson('/api/v1/vaccinations', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['dogId', 'vaccinationType', 'vaccinationDate']);
});

test('vaccination date cannot be in future', function () {
    $data = [
        'dogId' => $this->dog->id,
        'vaccinationType' => 'Rabies',
        'vaccinationDate' => now()->addDays(5)->format('Y-m-d'),
    ];

    $this->actingAs($this->trainer)
        ->postJson('/api/v1/vaccinations', $data)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['vaccinationDate']);
});

test('next due date must be after vaccination date', function () {
    $data = [
        'dogId' => $this->dog->id,
        'vaccinationType' => 'Rabies',
        'vaccinationDate' => now()->format('Y-m-d'),
        'nextDueDate' => now()->subDays(5)->format('Y-m-d'),
    ];

    $this->actingAs($this->trainer)
        ->postJson('/api/v1/vaccinations', $data)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['nextDueDate']);
});

test('admin can update vaccination', function () {
    $vaccination = Vaccination::factory()->create();

    $this->actingAs($this->admin)
        ->putJson('/api/v1/vaccinations/' . $vaccination->id, [
            'veterinarian' => 'Dr. Johnson',
            'nextDueDate' => now()->addMonths(6)->format('Y-m-d'),
        ])
        ->assertOk()
        ->assertJsonPath('data.veterinarian', 'Dr. Johnson');

    $this->assertDatabaseHas('vaccinations', [
        'id' => $vaccination->id,
        'veterinarian' => 'Dr. Johnson',
    ]);
});

test('trainer can update vaccination', function () {
    $vaccination = Vaccination::factory()->create();

    $this->actingAs($this->trainer)
        ->putJson('/api/v1/vaccinations/' . $vaccination->id, [
            'vaccinationType' => 'Updated Type',
        ])
        ->assertOk()
        ->assertJsonPath('data.vaccinationType', 'Updated Type');
});

test('customer cannot update vaccination', function () {
    $vaccination = Vaccination::factory()->create(['dog_id' => $this->dog->id]);

    $this->actingAs($this->customerUser)
        ->putJson('/api/v1/vaccinations/' . $vaccination->id, [
            'veterinarian' => 'Dr. New',
        ])
        ->assertForbidden();
});

test('admin can delete vaccination', function () {
    $vaccination = Vaccination::factory()->create();

    $this->actingAs($this->admin)
        ->deleteJson('/api/v1/vaccinations/' . $vaccination->id)
        ->assertNoContent();

    expect(Vaccination::find($vaccination->id))->toBeNull();
});

test('trainer cannot delete vaccination', function () {
    $vaccination = Vaccination::factory()->create();

    $this->actingAs($this->trainer)
        ->deleteJson('/api/v1/vaccinations/' . $vaccination->id)
        ->assertForbidden();
});

test('customer cannot delete vaccination', function () {
    $vaccination = Vaccination::factory()->create(['dog_id' => $this->dog->id]);

    $this->actingAs($this->customerUser)
        ->deleteJson('/api/v1/vaccinations/' . $vaccination->id)
        ->assertForbidden();
});
