<?php

declare(strict_types=1);

use App\Models\AnamnesisAnswer;
use App\Models\AnamnesisQuestion;
use App\Models\AnamnesisResponse;
use App\Models\AnamnesisTemplate;
use App\Models\Customer;
use App\Models\Dog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
uses()->group('api', 'anamnesis');

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->trainer = User::factory()->create(['role' => 'trainer']);
    $this->customer = User::factory()->hasCustomer()->create(['role' => 'customer']);
    $this->anotherCustomer = User::factory()->hasCustomer()->create(['role' => 'customer']);
});

test('can list anamnesis responses', function () {
    AnamnesisResponse::factory()->count(3)->create();

    $response = $this->actingAs($this->admin)->getJson('/api/v1/anamnesis-responses');

    $response->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'dogId', 'templateId', 'completedAt', 'completedBy', 'createdAt']
            ]
        ]);
});

test('customer only sees own dogs responses', function () {
    $dog = Dog::factory()->create(['customer_id' => $this->customer->customer->id]);
    $otherDog = Dog::factory()->create(['customer_id' => $this->anotherCustomer->customer->id]);

    AnamnesisResponse::factory()->create(['dog_id' => $dog->id]);
    AnamnesisResponse::factory()->create(['dog_id' => $otherDog->id]);

    $response = $this->actingAs($this->customer)
        ->getJson('/api/v1/anamnesis-responses');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.dogId', $dog->id);
});

test('can filter responses by dog', function () {
    $dog = Dog::factory()->create();
    $otherDog = Dog::factory()->create();

    AnamnesisResponse::factory()->create(['dog_id' => $dog->id]);
    AnamnesisResponse::factory()->create(['dog_id' => $otherDog->id]);

    $response = $this->actingAs($this->admin)
        ->getJson("/api/v1/anamnesis-responses?dogId={$dog->id}");

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.dogId', $dog->id);
});

test('can filter responses by template', function () {
    $template1 = AnamnesisTemplate::factory()->create();
    $template2 = AnamnesisTemplate::factory()->create();

    AnamnesisResponse::factory()->create(['template_id' => $template1->id]);
    AnamnesisResponse::factory()->create(['template_id' => $template2->id]);

    $response = $this->actingAs($this->admin)
        ->getJson("/api/v1/anamnesis-responses?templateId={$template1->id}");

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.templateId', $template1->id);
});

test('can filter completed responses', function () {
    AnamnesisResponse::factory()->create(['completed_at' => now()]);
    AnamnesisResponse::factory()->create(['completed_at' => null]);

    $response = $this->actingAs($this->admin)
        ->getJson('/api/v1/anamnesis-responses?completed=1');

    $response->assertOk()
        ->assertJsonCount(1, 'data');

    expect($response->json('data.0.completedAt'))->not->toBeNull();
});

test('can filter incomplete responses', function () {
    AnamnesisResponse::factory()->create(['completed_at' => now()]);
    AnamnesisResponse::factory()->create(['completed_at' => null]);

    $response = $this->actingAs($this->admin)
        ->getJson('/api/v1/anamnesis-responses?completed=0');

    $response->assertOk()
        ->assertJsonCount(1, 'data');

    expect($response->json('data.0.completedAt'))->toBeNull();
});

test('can filter responses by customer', function () {
    $customer1 = Customer::factory()->create();
    $customer2 = Customer::factory()->create();
    $dog1 = Dog::factory()->create(['customer_id' => $customer1->id]);
    $dog2 = Dog::factory()->create(['customer_id' => $customer2->id]);

    AnamnesisResponse::factory()->create(['dog_id' => $dog1->id]);
    AnamnesisResponse::factory()->create(['dog_id' => $dog2->id]);

    $response = $this->actingAs($this->admin)
        ->getJson("/api/v1/anamnesis-responses?customerId={$customer1->id}");

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});

test('can create response without answers', function () {
    $dog = Dog::factory()->create(['customer_id' => $this->customer->customer->id]);
    $template = AnamnesisTemplate::factory()->create();

    $data = [
        'dogId' => $dog->id,
        'templateId' => $template->id,
    ];

    $response = $this->actingAs($this->customer)
        ->postJson('/api/v1/anamnesis-responses', $data);

    $response->assertCreated()
        ->assertJsonPath('data.dogId', $dog->id)
        ->assertJsonPath('data.templateId', $template->id)
        ->assertJsonPath('data.completedBy', $this->customer->id);

    $this->assertDatabaseHas('anamnesis_responses', [
        'dog_id' => $dog->id,
        'template_id' => $template->id,
        'completed_by' => $this->customer->id,
    ]);
});

test('can create response with answers', function () {
    $dog = Dog::factory()->create(['customer_id' => $this->customer->customer->id]);
    $template = AnamnesisTemplate::factory()->create();
    $question1 = AnamnesisQuestion::factory()->create(['template_id' => $template->id]);
    $question2 = AnamnesisQuestion::factory()->create(['template_id' => $template->id]);

    $data = [
        'dogId' => $dog->id,
        'templateId' => $template->id,
        'answers' => [
            [
                'questionId' => $question1->id,
                'answerValue' => '3 years',
            ],
            [
                'questionId' => $question2->id,
                'answerValue' => 'No',
            ],
        ],
    ];

    $response = $this->actingAs($this->customer)
        ->postJson('/api/v1/anamnesis-responses', $data);

    $response->assertCreated()
        ->assertJsonPath('data.dogId', $dog->id);

    $anamnesisResponse = AnamnesisResponse::where('dog_id', $dog->id)->first();
    expect($anamnesisResponse->answers)->toHaveCount(2);
    expect($anamnesisResponse->answers->first()->answer_value)->toBe('3 years');
});

test('cannot create response for dog not owned', function () {
    $otherDog = Dog::factory()->create(['customer_id' => $this->anotherCustomer->customer->id]);
    $template = AnamnesisTemplate::factory()->create();

    $data = [
        'dogId' => $otherDog->id,
        'templateId' => $template->id,
    ];

    $response = $this->actingAs($this->customer)
        ->postJson('/api/v1/anamnesis-responses', $data);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['dogId']);
});

test('can view response details', function () {
    $dog = Dog::factory()->create(['customer_id' => $this->customer->customer->id]);
    $template = AnamnesisTemplate::factory()->create();
    AnamnesisQuestion::factory()->count(2)->create(['template_id' => $template->id]);
    $anamnesisResponse = AnamnesisResponse::factory()->create([
        'dog_id' => $dog->id,
        'template_id' => $template->id,
    ]);
    AnamnesisAnswer::factory()->count(2)->create(['response_id' => $anamnesisResponse->id]);

    $response = $this->actingAs($this->customer)
        ->getJson("/api/v1/anamnesis-responses/{$anamnesisResponse->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $anamnesisResponse->id)
        ->assertJsonStructure([
            'data' => [
                'id',
                'dogId',
                'templateId',
                'dog',
                'template',
                'answers' => [
                    '*' => ['id', 'questionId', 'answerValue', 'question']
                ]
            ]
        ]);
});

test('customer cannot view other customers response', function () {
    $otherDog = Dog::factory()->create(['customer_id' => $this->anotherCustomer->customer->id]);
    $anamnesisResponse = AnamnesisResponse::factory()->create(['dog_id' => $otherDog->id]);

    $response = $this->actingAs($this->customer)
        ->getJson("/api/v1/anamnesis-responses/{$anamnesisResponse->id}");

    $response->assertForbidden();
});

test('trainer can view any response', function () {
    $anamnesisResponse = AnamnesisResponse::factory()->create();

    $response = $this->actingAs($this->trainer)
        ->getJson("/api/v1/anamnesis-responses/{$anamnesisResponse->id}");

    $response->assertOk();
});

test('can update response answers', function () {
    $dog = Dog::factory()->create(['customer_id' => $this->customer->customer->id]);
    $template = AnamnesisTemplate::factory()->create();
    $question = AnamnesisQuestion::factory()->create(['template_id' => $template->id]);
    $anamnesisResponse = AnamnesisResponse::factory()->create([
        'dog_id' => $dog->id,
        'template_id' => $template->id,
    ]);

    $data = [
        'answers' => [
            [
                'questionId' => $question->id,
                'answerValue' => 'Updated answer',
            ],
        ],
    ];

    $response = $this->actingAs($this->customer)
        ->putJson("/api/v1/anamnesis-responses/{$anamnesisResponse->id}", $data);

    $response->assertOk();

    $this->assertDatabaseHas('anamnesis_answers', [
        'response_id' => $anamnesisResponse->id,
        'question_id' => $question->id,
        'answer_value' => 'Updated answer',
    ]);
});

test('customer cannot update other customers response', function () {
    $otherDog = Dog::factory()->create(['customer_id' => $this->anotherCustomer->customer->id]);
    $anamnesisResponse = AnamnesisResponse::factory()->create(['dog_id' => $otherDog->id]);

    $data = ['answers' => []];

    $response = $this->actingAs($this->customer)
        ->putJson("/api/v1/anamnesis-responses/{$anamnesisResponse->id}", $data);

    $response->assertForbidden();
});

test('trainer can update any response', function () {
    $anamnesisResponse = AnamnesisResponse::factory()->create();
    $question = AnamnesisQuestion::factory()->create(['template_id' => $anamnesisResponse->template_id]);

    $data = [
        'answers' => [
            [
                'questionId' => $question->id,
                'answerValue' => 'Trainer update',
            ],
        ],
    ];

    $response = $this->actingAs($this->trainer)
        ->putJson("/api/v1/anamnesis-responses/{$anamnesisResponse->id}", $data);

    $response->assertOk();
});

test('admin can delete response', function () {
    $anamnesisResponse = AnamnesisResponse::factory()->create();

    $response = $this->actingAs($this->admin)
        ->deleteJson("/api/v1/anamnesis-responses/{$anamnesisResponse->id}");

    $response->assertNoContent();

    $this->assertDatabaseMissing('anamnesis_responses', ['id' => $anamnesisResponse->id]);
});

test('customer cannot delete response', function () {
    $dog = Dog::factory()->create(['customer_id' => $this->customer->customer->id]);
    $anamnesisResponse = AnamnesisResponse::factory()->create(['dog_id' => $dog->id]);

    $response = $this->actingAs($this->customer)
        ->deleteJson("/api/v1/anamnesis-responses/{$anamnesisResponse->id}");

    $response->assertForbidden();
});

test('trainer cannot delete response', function () {
    $anamnesisResponse = AnamnesisResponse::factory()->create();

    $response = $this->actingAs($this->trainer)
        ->deleteJson("/api/v1/anamnesis-responses/{$anamnesisResponse->id}");

    $response->assertForbidden();
});

test('can complete response', function () {
    $dog = Dog::factory()->create(['customer_id' => $this->customer->customer->id]);
    $anamnesisResponse = AnamnesisResponse::factory()->create([
        'dog_id' => $dog->id,
        'completed_at' => null,
    ]);

    $response = $this->actingAs($this->customer)
        ->postJson("/api/v1/anamnesis-responses/{$anamnesisResponse->id}/complete");

    $response->assertOk()
        ->assertJsonPath('data.id', $anamnesisResponse->id);

    expect($response->json('data.completedAt'))->not->toBeNull();

    $this->assertDatabaseHas('anamnesis_responses', [
        'id' => $anamnesisResponse->id,
    ]);

    $anamnesisResponse->refresh();
    expect($anamnesisResponse->completed_at)->not->toBeNull();
});

test('validates required fields when creating response', function () {
    $response = $this->actingAs($this->customer)
        ->postJson('/api/v1/anamnesis-responses', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['dogId', 'templateId']);
});

test('validates answer structure', function () {
    $dog = Dog::factory()->create(['customer_id' => $this->customer->customer->id]);
    $template = AnamnesisTemplate::factory()->create();

    $data = [
        'dogId' => $dog->id,
        'templateId' => $template->id,
        'answers' => [
            [
                'answerValue' => 'Missing question ID',
            ],
        ],
    ];

    $response = $this->actingAs($this->customer)
        ->postJson('/api/v1/anamnesis-responses', $data);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['answers.0.questionId']);
});
