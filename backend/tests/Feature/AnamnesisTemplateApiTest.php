<?php

declare(strict_types=1);

use App\Models\AnamnesisQuestion;
use App\Models\AnamnesisResponse;
use App\Models\AnamnesisTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
uses()->group('api', 'anamnesis');

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->trainer = User::factory()->create(['role' => 'trainer']);
    $this->anotherTrainer = User::factory()->create(['role' => 'trainer']);
    $this->customer = User::factory()->hasCustomer()->create(['role' => 'customer']);
});

test('can list anamnesis templates', function () {
    $templates = AnamnesisTemplate::factory()->count(3)->create();

    $response = $this->actingAs($this->admin)->getJson('/api/v1/anamnesis-templates');

    $response->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'trainerId', 'name', 'description', 'isDefault', 'createdAt', 'updatedAt']
            ]
        ]);
});

test('can filter templates by trainer', function () {
    AnamnesisTemplate::factory()->create(['trainer_id' => $this->trainer->id]);
    AnamnesisTemplate::factory()->create(['trainer_id' => $this->anotherTrainer->id]);

    $response = $this->actingAs($this->admin)
        ->getJson('/api/v1/anamnesis-templates?trainerId=' . $this->trainer->id);

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.trainerId', $this->trainer->id);
});

test('can filter default templates', function () {
    AnamnesisTemplate::factory()->create(['is_default' => true]);
    AnamnesisTemplate::factory()->create(['is_default' => false]);

    $response = $this->actingAs($this->admin)
        ->getJson('/api/v1/anamnesis-templates?isDefault=1');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.isDefault', true);
});

test('can search templates by name', function () {
    AnamnesisTemplate::factory()->create(['name' => 'Puppy Assessment']);
    AnamnesisTemplate::factory()->create(['name' => 'Adult Dog Evaluation']);

    $response = $this->actingAs($this->admin)
        ->getJson('/api/v1/anamnesis-templates?search=Puppy');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Puppy Assessment');
});

test('trainer can create template without questions', function () {
    $data = [
        'name' => 'Basic Health Check',
        'description' => 'Standard health assessment',
        'isDefault' => false,
    ];

    $response = $this->actingAs($this->trainer)
        ->postJson('/api/v1/anamnesis-templates', $data);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Basic Health Check')
        ->assertJsonPath('data.trainerId', $this->trainer->id);

    $this->assertDatabaseHas('anamnesis_templates', [
        'name' => 'Basic Health Check',
        'trainer_id' => $this->trainer->id,
    ]);
});

test('trainer can create template with questions', function () {
    $data = [
        'name' => 'Complete Assessment',
        'description' => 'Full dog assessment',
        'isDefault' => false,
        'questions' => [
            [
                'questionText' => 'What is your dog\'s age?',
                'questionType' => 'text',
                'isRequired' => true,
                'order' => 0,
            ],
            [
                'questionText' => 'Does your dog have any allergies?',
                'questionType' => 'radio',
                'options' => ['Yes', 'No'],
                'isRequired' => true,
                'order' => 1,
            ],
        ],
    ];

    $response = $this->actingAs($this->trainer)
        ->postJson('/api/v1/anamnesis-templates', $data);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Complete Assessment');

    $template = AnamnesisTemplate::where('name', 'Complete Assessment')->first();
    expect($template->questions)->toHaveCount(2);
    expect($template->questions->first()->question_text)->toBe('What is your dog\'s age?');
});

test('customer cannot create template', function () {
    $data = [
        'name' => 'Unauthorized Template',
        'description' => 'Should not be created',
    ];

    $response = $this->actingAs($this->customer)
        ->postJson('/api/v1/anamnesis-templates', $data);

    $response->assertForbidden();
});

test('can view template details with questions', function () {
    $template = AnamnesisTemplate::factory()->create();
    AnamnesisQuestion::factory()->count(3)->create(['template_id' => $template->id]);

    $response = $this->actingAs($this->admin)
        ->getJson("/api/v1/anamnesis-templates/{$template->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $template->id)
        ->assertJsonStructure([
            'data' => [
                'id',
                'trainerId',
                'name',
                'questions' => [
                    '*' => ['id', 'questionText', 'questionType', 'isRequired', 'order']
                ]
            ]
        ]);
});

test('trainer can update own template', function () {
    $template = AnamnesisTemplate::factory()->create(['trainer_id' => $this->trainer->id]);

    $data = [
        'name' => 'Updated Template Name',
        'description' => 'Updated description',
    ];

    $response = $this->actingAs($this->trainer)
        ->putJson("/api/v1/anamnesis-templates/{$template->id}", $data);

    $response->assertOk()
        ->assertJsonPath('data.name', 'Updated Template Name');

    $this->assertDatabaseHas('anamnesis_templates', [
        'id' => $template->id,
        'name' => 'Updated Template Name',
    ]);
});

test('trainer cannot update another trainers template', function () {
    $template = AnamnesisTemplate::factory()->create(['trainer_id' => $this->trainer->id]);

    $data = ['name' => 'Unauthorized Update'];

    $response = $this->actingAs($this->anotherTrainer)
        ->putJson("/api/v1/anamnesis-templates/{$template->id}", $data);

    $response->assertForbidden();
});

test('admin can update any template', function () {
    $template = AnamnesisTemplate::factory()->create(['trainer_id' => $this->trainer->id]);

    $data = ['name' => 'Admin Updated'];

    $response = $this->actingAs($this->admin)
        ->putJson("/api/v1/anamnesis-templates/{$template->id}", $data);

    $response->assertOk()
        ->assertJsonPath('data.name', 'Admin Updated');
});

test('admin can delete template without responses', function () {
    $template = AnamnesisTemplate::factory()->create();

    $response = $this->actingAs($this->admin)
        ->deleteJson("/api/v1/anamnesis-templates/{$template->id}");

    $response->assertNoContent();

    $this->assertDatabaseMissing('anamnesis_templates', ['id' => $template->id]);
});

test('cannot delete template with responses', function () {
    $template = AnamnesisTemplate::factory()->create();
    AnamnesisResponse::factory()->create(['template_id' => $template->id]);

    $response = $this->actingAs($this->admin)
        ->deleteJson("/api/v1/anamnesis-templates/{$template->id}");

    $response->assertStatus(422)
        ->assertJsonPath('message', 'Cannot delete template with existing responses.');

    $this->assertDatabaseHas('anamnesis_templates', ['id' => $template->id]);
});

test('trainer cannot delete template', function () {
    $template = AnamnesisTemplate::factory()->create(['trainer_id' => $this->trainer->id]);

    $response = $this->actingAs($this->trainer)
        ->deleteJson("/api/v1/anamnesis-templates/{$template->id}");

    $response->assertForbidden();
});

test('can get template questions ordered', function () {
    $template = AnamnesisTemplate::factory()->create();
    AnamnesisQuestion::factory()->create(['template_id' => $template->id, 'order' => 2]);
    AnamnesisQuestion::factory()->create(['template_id' => $template->id, 'order' => 0]);
    AnamnesisQuestion::factory()->create(['template_id' => $template->id, 'order' => 1]);

    $response = $this->actingAs($this->admin)
        ->getJson("/api/v1/anamnesis-templates/{$template->id}/questions");

    $response->assertOk()
        ->assertJsonCount(3, 'data');

    $orders = collect($response->json('data'))->pluck('order')->toArray();
    expect($orders)->toBe([0, 1, 2]);
});

test('validates required fields when creating template', function () {
    $response = $this->actingAs($this->trainer)
        ->postJson('/api/v1/anamnesis-templates', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

test('validates question types', function () {
    $data = [
        'name' => 'Test Template',
        'questions' => [
            [
                'questionText' => 'Test Question',
                'questionType' => 'invalid-type',
                'isRequired' => true,
                'order' => 0,
            ],
        ],
    ];

    $response = $this->actingAs($this->trainer)
        ->postJson('/api/v1/anamnesis-templates', $data);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['questions.0.questionType']);
});
