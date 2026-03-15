<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Dog;
use App\Models\TrainingLog;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->trainer = User::factory()->trainer()->create();
    $this->customer = User::factory()->customer()->create();
    $this->otherCustomer = User::factory()->customer()->create();
    
    // Create customers for the users
    $customerRecord = Customer::factory()->create(['user_id' => $this->customer->id]);
    $otherCustomerRecord = Customer::factory()->create(['user_id' => $this->otherCustomer->id]);
    
    $this->dog = Dog::factory()->create(['customer_id' => $customerRecord->id]);
    $this->otherDog = Dog::factory()->create(['customer_id' => $otherCustomerRecord->id]);
});

// ============================================================================
// INDEX Tests
// ============================================================================

test('admin can list all training logs', function () {
    TrainingLog::factory()->count(5)->create();
    
    $response = $this->actingAs($this->admin)
        ->getJson('/api/v1/training-logs');
    
    $response->assertOk()
        ->assertJsonCount(5, 'data');
});

test('trainer can list all training logs', function () {
    TrainingLog::factory()->count(5)->create();
    
    $response = $this->actingAs($this->trainer)
        ->getJson('/api/v1/training-logs');
    
    $response->assertOk()
        ->assertJsonCount(5, 'data');
});

test('customer can only list training logs for their own dogs', function () {
    TrainingLog::factory()->count(3)->create(['dog_id' => $this->dog->id]);
    TrainingLog::factory()->count(2)->create(['dog_id' => $this->otherDog->id]);
    
    $response = $this->actingAs($this->customer)
        ->getJson('/api/v1/training-logs');
    
    $response->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.dogId', $this->dog->id);
});

test('can filter training logs by dog', function () {
    TrainingLog::factory()->count(3)->create(['dog_id' => $this->dog->id]);
    TrainingLog::factory()->count(2)->create(['dog_id' => $this->otherDog->id]);
    
    $response = $this->actingAs($this->admin)
        ->getJson('/api/v1/training-logs?dogId=' . $this->dog->id);
    
    $response->assertOk()
        ->assertJsonCount(3, 'data');
});

test('can filter training logs by trainer', function () {
    $otherTrainer = User::factory()->trainer()->create();
    
    TrainingLog::factory()->count(3)->create(['trainer_id' => $this->trainer->id]);
    TrainingLog::factory()->count(2)->create(['trainer_id' => $otherTrainer->id]);
    
    $response = $this->actingAs($this->admin)
        ->getJson('/api/v1/training-logs?trainerId=' . $this->trainer->id);
    
    $response->assertOk()
        ->assertJsonCount(3, 'data');
});

test('can filter training logs by training session', function () {
    $session = TrainingSession::factory()->create();
    
    TrainingLog::factory()->count(3)->create(['training_session_id' => $session->id]);
    TrainingLog::factory()->count(2)->create(['training_session_id' => null]);
    
    $response = $this->actingAs($this->admin)
        ->getJson('/api/v1/training-logs?trainingSessionId=' . $session->id);
    
    $response->assertOk()
        ->assertJsonCount(3, 'data');
});

test('can filter training logs by date range', function () {
    $oldLog = TrainingLog::factory()->create(['created_at' => now()->subDays(10)]);
    $recentLog = TrainingLog::factory()->create(['created_at' => now()->subDays(2)]);
    
    $response = $this->actingAs($this->admin)
        ->getJson('/api/v1/training-logs?startDate=' . now()->subDays(5)->toDateString());
    
    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $recentLog->id);
});

test('training logs are paginated', function () {
    TrainingLog::factory()->count(20)->create();
    
    $response = $this->actingAs($this->admin)
        ->getJson('/api/v1/training-logs');
    
    $response->assertOk()
        ->assertJsonCount(15, 'data')
        ->assertJsonStructure(['data', 'links', 'meta']);
});

test('training logs include relationships when loaded', function () {
    $log = TrainingLog::factory()->create();
    
    $response = $this->actingAs($this->admin)
        ->getJson('/api/v1/training-logs');
    
    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'dogId',
                    'trainingSessionId',
                    'trainerId',
                    'progressNotes',
                    'behaviorNotes',
                    'homework',
                    'createdAt',
                    'updatedAt',
                    'dog',
                    'trainingSession',
                    'trainer',
                    'attachments',
                ],
            ],
        ]);
});

// ============================================================================
// SHOW Tests
// ============================================================================

test('admin can view any training log', function () {
    $log = TrainingLog::factory()->create();
    
    $response = $this->actingAs($this->admin)
        ->getJson('/api/v1/training-logs/' . $log->id);
    
    $response->assertOk()
        ->assertJsonPath('data.id', $log->id);
});

test('trainer can view any training log', function () {
    $log = TrainingLog::factory()->create();
    
    $response = $this->actingAs($this->trainer)
        ->getJson('/api/v1/training-logs/' . $log->id);
    
    $response->assertOk()
        ->assertJsonPath('data.id', $log->id);
});

test('customer can view training logs for their own dogs', function () {
    $log = TrainingLog::factory()->create(['dog_id' => $this->dog->id]);
    
    $response = $this->actingAs($this->customer)
        ->getJson('/api/v1/training-logs/' . $log->id);
    
    $response->assertOk()
        ->assertJsonPath('data.dogId', $this->dog->id);
});

test('customer cannot view training logs for other customers dogs', function () {
    $log = TrainingLog::factory()->create(['dog_id' => $this->otherDog->id]);
    
    $response = $this->actingAs($this->customer)
        ->getJson('/api/v1/training-logs/' . $log->id);
    
    $response->assertForbidden();
});

test('show includes all relationships', function () {
    $log = TrainingLog::factory()->create();
    
    $response = $this->actingAs($this->admin)
        ->getJson('/api/v1/training-logs/' . $log->id);
    
    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id',
                'dog',
                'trainer',
                'attachments',
            ],
        ]);
});

// ============================================================================
// STORE Tests
// ============================================================================

test('trainer can create training log', function () {
    $data = [
        'dogId' => $this->dog->id,
        'trainerId' => $this->trainer->id,
        'progressNotes' => 'Good progress today',
        'behaviorNotes' => 'Responded well to commands',
        'homework' => 'Practice sit and stay',
    ];
    
    $response = $this->actingAs($this->trainer)
        ->postJson('/api/v1/training-logs', $data);
    
    $response->assertCreated()
        ->assertJsonPath('data.progressNotes', 'Good progress today')
        ->assertJsonPath('data.behaviorNotes', 'Responded well to commands')
        ->assertJsonPath('data.homework', 'Practice sit and stay');
    
    $this->assertDatabaseHas('training_logs', [
        'dog_id' => $this->dog->id,
        'trainer_id' => $this->trainer->id,
        'progress_notes' => 'Good progress today',
    ]);
});

test('admin can create training log', function () {
    $data = [
        'dogId' => $this->dog->id,
        'trainerId' => $this->trainer->id,
        'progressNotes' => 'Test notes',
    ];
    
    $response = $this->actingAs($this->admin)
        ->postJson('/api/v1/training-logs', $data);
    
    $response->assertCreated();
});

test('customer cannot create training log', function () {
    $data = [
        'dogId' => $this->dog->id,
        'trainerId' => $this->trainer->id,
        'progressNotes' => 'Test notes',
    ];
    
    $response = $this->actingAs($this->customer)
        ->postJson('/api/v1/training-logs', $data);
    
    $response->assertForbidden();
});

test('can create training log with training session', function () {
    $session = TrainingSession::factory()->create();
    
    $data = [
        'dogId' => $this->dog->id,
        'trainerId' => $this->trainer->id,
        'trainingSessionId' => $session->id,
        'progressNotes' => 'Session notes',
    ];
    
    $response = $this->actingAs($this->trainer)
        ->postJson('/api/v1/training-logs', $data);
    
    $response->assertCreated()
        ->assertJsonPath('data.trainingSessionId', $session->id);
});

test('can create training log without optional fields', function () {
    $data = [
        'dogId' => $this->dog->id,
        'trainerId' => $this->trainer->id,
    ];
    
    $response = $this->actingAs($this->trainer)
        ->postJson('/api/v1/training-logs', $data);
    
    $response->assertCreated()
        ->assertJsonPath('data.progressNotes', null)
        ->assertJsonPath('data.behaviorNotes', null)
        ->assertJsonPath('data.homework', null);
});

test('dog is required when creating training log', function () {
    $data = [
        'trainerId' => $this->trainer->id,
        'progressNotes' => 'Test notes',
    ];
    
    $response = $this->actingAs($this->trainer)
        ->postJson('/api/v1/training-logs', $data);
    
    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['dogId']);
});

test('trainer is required when creating training log', function () {
    $data = [
        'dogId' => $this->dog->id,
        'progressNotes' => 'Test notes',
    ];
    
    $response = $this->actingAs($this->trainer)
        ->postJson('/api/v1/training-logs', $data);
    
    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['trainerId']);
});

test('dog must exist when creating training log', function () {
    $data = [
        'dogId' => 99999,
        'trainerId' => $this->trainer->id,
        'progressNotes' => 'Test notes',
    ];
    
    $response = $this->actingAs($this->trainer)
        ->postJson('/api/v1/training-logs', $data);
    
    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['dogId']);
});

test('trainer must exist when creating training log', function () {
    $data = [
        'dogId' => $this->dog->id,
        'trainerId' => 99999,
        'progressNotes' => 'Test notes',
    ];
    
    $response = $this->actingAs($this->trainer)
        ->postJson('/api/v1/training-logs', $data);
    
    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['trainerId']);
});

// ============================================================================
// UPDATE Tests
// ============================================================================

test('trainer can update their own training log', function () {
    $log = TrainingLog::factory()->create(['trainer_id' => $this->trainer->id]);
    
    $data = [
        'progressNotes' => 'Updated progress notes',
        'behaviorNotes' => 'Updated behavior notes',
    ];
    
    $response = $this->actingAs($this->trainer)
        ->putJson('/api/v1/training-logs/' . $log->id, $data);
    
    $response->assertOk()
        ->assertJsonPath('data.progressNotes', 'Updated progress notes')
        ->assertJsonPath('data.behaviorNotes', 'Updated behavior notes');
    
    $this->assertDatabaseHas('training_logs', [
        'id' => $log->id,
        'progress_notes' => 'Updated progress notes',
    ]);
});

test('trainer cannot update other trainers training logs', function () {
    $otherTrainer = User::factory()->trainer()->create();
    $log = TrainingLog::factory()->create(['trainer_id' => $otherTrainer->id]);
    
    $data = ['progressNotes' => 'Updated notes'];
    
    $response = $this->actingAs($this->trainer)
        ->putJson('/api/v1/training-logs/' . $log->id, $data);
    
    $response->assertForbidden();
});

test('admin can update any training log', function () {
    $log = TrainingLog::factory()->create(['trainer_id' => $this->trainer->id]);
    
    $data = ['progressNotes' => 'Admin updated notes'];
    
    $response = $this->actingAs($this->admin)
        ->putJson('/api/v1/training-logs/' . $log->id, $data);
    
    $response->assertOk()
        ->assertJsonPath('data.progressNotes', 'Admin updated notes');
});

test('customer cannot update training log', function () {
    $log = TrainingLog::factory()->create(['dog_id' => $this->dog->id]);
    
    $data = ['progressNotes' => 'Customer update'];
    
    $response = $this->actingAs($this->customer)
        ->putJson('/api/v1/training-logs/' . $log->id, $data);
    
    $response->assertForbidden();
});

test('can update training log with partial data', function () {
    $log = TrainingLog::factory()->create([
        'trainer_id' => $this->trainer->id,
        'progress_notes' => 'Original progress',
        'behavior_notes' => 'Original behavior',
    ]);
    
    $data = ['progressNotes' => 'Updated progress only'];
    
    $response = $this->actingAs($this->trainer)
        ->putJson('/api/v1/training-logs/' . $log->id, $data);
    
    $response->assertOk()
        ->assertJsonPath('data.progressNotes', 'Updated progress only')
        ->assertJsonPath('data.behaviorNotes', 'Original behavior');
});

test('dog must exist when updating training log', function () {
    $log = TrainingLog::factory()->create(['trainer_id' => $this->trainer->id]);
    
    $data = ['dogId' => 99999];
    
    $response = $this->actingAs($this->trainer)
        ->putJson('/api/v1/training-logs/' . $log->id, $data);
    
    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['dogId']);
});

// ============================================================================
// DELETE Tests
// ============================================================================

test('admin can delete training log', function () {
    $log = TrainingLog::factory()->create();
    
    $response = $this->actingAs($this->admin)
        ->deleteJson('/api/v1/training-logs/' . $log->id);
    
    $response->assertNoContent();
    
    $this->assertDatabaseMissing('training_logs', ['id' => $log->id]);
});

test('trainer cannot delete training log', function () {
    $log = TrainingLog::factory()->create(['trainer_id' => $this->trainer->id]);
    
    $response = $this->actingAs($this->trainer)
        ->deleteJson('/api/v1/training-logs/' . $log->id);
    
    $response->assertForbidden();
});

test('customer cannot delete training log', function () {
    $log = TrainingLog::factory()->create(['dog_id' => $this->dog->id]);
    
    $response = $this->actingAs($this->customer)
        ->deleteJson('/api/v1/training-logs/' . $log->id);
    
    $response->assertForbidden();
});

// ============================================================================
// AUTHORIZATION Tests
// ============================================================================

test('unauthenticated user cannot access training logs', function () {
    $this->getJson('/api/v1/training-logs')->assertUnauthorized();
    $this->postJson('/api/v1/training-logs', [])->assertUnauthorized();
    
    $log = TrainingLog::factory()->create();
    $this->getJson('/api/v1/training-logs/' . $log->id)->assertUnauthorized();
    $this->putJson('/api/v1/training-logs/' . $log->id, [])->assertUnauthorized();
    $this->deleteJson('/api/v1/training-logs/' . $log->id)->assertUnauthorized();
});
