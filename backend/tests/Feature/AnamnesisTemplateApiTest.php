<?php

declare(strict_types=1);

use App\Models\AnamnesisAnswer;
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
    $templates = AnamnesisTemplate::factory()->count(3)->create(['trainer_id' => $this->trainer->id]);

    $response = $this->actingAs($this->trainer)->getJson('/api/v1/anamnesis-templates');

    $response->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'trainerId', 'name', 'description', 'isDefault', 'createdAt', 'updatedAt', 'questionsCount']
            ]
        ]);
});

it('listed templates report the correct questions count per template', function () {
    $templateWithNoQuestions = AnamnesisTemplate::factory()->create(['trainer_id' => $this->trainer->id]);
    $templateWithTwoQuestions = AnamnesisTemplate::factory()->create(['trainer_id' => $this->trainer->id]);
    $templateWithFiveQuestions = AnamnesisTemplate::factory()->create(['trainer_id' => $this->trainer->id]);

    AnamnesisQuestion::factory()->count(2)->create(['template_id' => $templateWithTwoQuestions->id]);
    AnamnesisQuestion::factory()->count(5)->create(['template_id' => $templateWithFiveQuestions->id]);

    $response = $this->actingAs($this->trainer)->getJson('/api/v1/anamnesis-templates');

    $response->assertOk();

    $countsByTemplateId = collect($response->json('data'))->pluck('questionsCount', 'id');

    expect($countsByTemplateId->get($templateWithNoQuestions->id))->toBe(0);
    expect($countsByTemplateId->get($templateWithTwoQuestions->id))->toBe(2);
    expect($countsByTemplateId->get($templateWithFiveQuestions->id))->toBe(5);
});

test('admin cannot list anamnesis templates', function () {
    $response = $this->actingAs($this->admin)->getJson('/api/v1/anamnesis-templates');

    $response->assertForbidden();
});

test('can filter templates by trainer', function () {
    AnamnesisTemplate::factory()->create(['trainer_id' => $this->trainer->id]);
    AnamnesisTemplate::factory()->create(['trainer_id' => $this->anotherTrainer->id]);

    $response = $this->actingAs($this->trainer)
        ->getJson('/api/v1/anamnesis-templates?trainerId=' . $this->trainer->id);

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.trainerId', $this->trainer->id);
});

test('can filter default templates', function () {
    AnamnesisTemplate::factory()->create(['is_default' => true]);
    AnamnesisTemplate::factory()->create(['is_default' => false, 'trainer_id' => $this->trainer->id]);

    $response = $this->actingAs($this->trainer)
        ->getJson('/api/v1/anamnesis-templates?isDefault=1');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.isDefault', true);
});

test('can search templates by name', function () {
    AnamnesisTemplate::factory()->create(['name' => 'Puppy Assessment', 'trainer_id' => $this->trainer->id]);
    AnamnesisTemplate::factory()->create(['name' => 'Adult Dog Evaluation', 'trainer_id' => $this->trainer->id]);

    $response = $this->actingAs($this->trainer)
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

    $response = $this->actingAs($this->trainer)
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

test('admin cannot view anamnesis template', function () {
    $template = AnamnesisTemplate::factory()->create();

    $response = $this->actingAs($this->admin)
        ->getJson("/api/v1/anamnesis-templates/{$template->id}");

    $response->assertForbidden();
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

it('trainer can add new questions when updating template', function () {
    $template = AnamnesisTemplate::factory()->create(['trainer_id' => $this->trainer->id]);

    $data = [
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
        ->putJson("/api/v1/anamnesis-templates/{$template->id}", $data);

    $response->assertOk();

    $template->refresh();
    expect($template->questions)->toHaveCount(2);
    $this->assertDatabaseHas('anamnesis_questions', [
        'template_id' => $template->id,
        'question_text' => 'What is your dog\'s age?',
    ]);
});

it('trainer can modify existing question via id when updating template', function () {
    $template = AnamnesisTemplate::factory()->create(['trainer_id' => $this->trainer->id]);
    $question = AnamnesisQuestion::factory()->create([
        'template_id' => $template->id,
        'question_text' => 'Original question',
        'question_type' => 'text',
        'order' => 0,
    ]);

    $data = [
        'questions' => [
            [
                'id' => $question->id,
                'questionText' => 'Updated question',
                'questionType' => 'textarea',
                'isRequired' => false,
                'order' => 0,
            ],
        ],
    ];

    $response = $this->actingAs($this->trainer)
        ->putJson("/api/v1/anamnesis-templates/{$template->id}", $data);

    $response->assertOk();

    expect($template->questions()->count())->toBe(1);
    $this->assertDatabaseHas('anamnesis_questions', [
        'id' => $question->id,
        'question_text' => 'Updated question',
        'question_type' => 'textarea',
    ]);
});

it('trainer can remove a question by omitting it from the update payload', function () {
    $template = AnamnesisTemplate::factory()->create(['trainer_id' => $this->trainer->id]);
    $keptQuestion = AnamnesisQuestion::factory()->create(['template_id' => $template->id, 'order' => 0]);
    $removedQuestion = AnamnesisQuestion::factory()->create(['template_id' => $template->id, 'order' => 1]);

    $data = [
        'questions' => [
            [
                'id' => $keptQuestion->id,
                'questionText' => $keptQuestion->question_text,
                'questionType' => $keptQuestion->question_type,
                'isRequired' => $keptQuestion->is_required,
                'order' => 0,
            ],
        ],
    ];

    $response = $this->actingAs($this->trainer)
        ->putJson("/api/v1/anamnesis-templates/{$template->id}", $data);

    $response->assertOk();

    $this->assertDatabaseHas('anamnesis_questions', ['id' => $keptQuestion->id]);
    $this->assertDatabaseMissing('anamnesis_questions', ['id' => $removedQuestion->id]);
});

it('removing a question with existing answers from the update payload does not delete the question or its answers', function () {
    $template = AnamnesisTemplate::factory()->create(['trainer_id' => $this->trainer->id]);
    $answeredQuestion = AnamnesisQuestion::factory()->create(['template_id' => $template->id, 'order' => 0]);
    $answer = AnamnesisAnswer::factory()->create(['question_id' => $answeredQuestion->id]);

    $data = [
        'questions' => [],
    ];

    $response = $this->actingAs($this->trainer)
        ->putJson("/api/v1/anamnesis-templates/{$template->id}", $data);

    $response->assertOk();

    $this->assertDatabaseHas('anamnesis_questions', ['id' => $answeredQuestion->id]);
    $this->assertDatabaseHas('anamnesis_answers', ['id' => $answer->id]);
});

it('update rejects a question id belonging to a different template', function () {
    $template = AnamnesisTemplate::factory()->create(['trainer_id' => $this->trainer->id]);
    $otherTemplate = AnamnesisTemplate::factory()->create(['trainer_id' => $this->trainer->id]);
    $foreignQuestion = AnamnesisQuestion::factory()->create(['template_id' => $otherTemplate->id]);

    $data = [
        'questions' => [
            [
                'id' => $foreignQuestion->id,
                'questionText' => 'Hijacked question',
                'questionType' => 'text',
                'isRequired' => false,
                'order' => 0,
            ],
        ],
    ];

    $response = $this->actingAs($this->trainer)
        ->putJson("/api/v1/anamnesis-templates/{$template->id}", $data);

    $response->assertStatus(422);

    $this->assertDatabaseHas('anamnesis_questions', [
        'id' => $foreignQuestion->id,
        'template_id' => $otherTemplate->id,
        'question_text' => $foreignQuestion->question_text,
    ]);
});

it('updating template without the questions key leaves existing questions untouched', function () {
    $template = AnamnesisTemplate::factory()->create(['trainer_id' => $this->trainer->id]);
    $question = AnamnesisQuestion::factory()->create(['template_id' => $template->id]);

    $data = [
        'name' => 'Renamed Template',
    ];

    $response = $this->actingAs($this->trainer)
        ->putJson("/api/v1/anamnesis-templates/{$template->id}", $data);

    $response->assertOk()
        ->assertJsonPath('data.name', 'Renamed Template');

    $this->assertDatabaseHas('anamnesis_questions', ['id' => $question->id]);
});

it('synchronisiert erstellte, geänderte und gelöschte fragen in einem einzigen realistischen frontend-payload', function () {
    // Bildet den tatsächlichen PUT-Payload nach, den
    // AnamnesisTemplateFormModal.vue::save() nach T05 erzeugt (siehe
    // frontend/src/components/anamnesis/AnamnesisTemplateFormModal.vue,
    // Funktion save(): name/description/isDefault + questions[] mit
    // optionalem id-Feld pro Frage), statt nur die Backend-Sync-Logik
    // isoliert je Testfall zu prüfen. Verifiziert Update-, Create- und
    // Delete-Semantik in einem einzigen, realitätsnahen Roundtrip.
    $template = AnamnesisTemplate::factory()->create([
        'trainer_id' => $this->trainer->id,
        'name' => 'Vollständige Aufnahme',
        'description' => 'Alte Beschreibung',
    ]);
    $questionToModify = AnamnesisQuestion::factory()->create([
        'template_id' => $template->id,
        'question_text' => 'Wie alt ist dein Hund?',
        'question_type' => 'text',
        'options' => null,
        'is_required' => true,
        'order' => 0,
    ]);
    $questionToRemove = AnamnesisQuestion::factory()->create([
        'template_id' => $template->id,
        'question_text' => 'Wird beim Speichern entfernt',
        'order' => 1,
    ]);

    $payload = [
        'name' => 'Vollständige Aufnahme',
        'description' => 'Neue Beschreibung',
        'isDefault' => false,
        'questions' => [
            [
                'id' => $questionToModify->id,
                'questionText' => 'Wie alt ist dein Hund in Jahren?',
                'questionType' => 'textarea',
                'isRequired' => false,
                'options' => null,
                'order' => 0,
            ],
            [
                'questionText' => 'Hat dein Hund Allergien?',
                'questionType' => 'radio',
                'isRequired' => true,
                'options' => ['Ja', 'Nein'],
                'order' => 1,
            ],
        ],
    ];

    $response = $this->actingAs($this->trainer)
        ->putJson("/api/v1/anamnesis-templates/{$template->id}", $payload);

    $response->assertOk()
        ->assertJsonPath('data.name', 'Vollständige Aufnahme')
        ->assertJsonPath('data.questionsCount', 2);

    $this->assertDatabaseCount('anamnesis_questions', 2);

    // Update-Semantik: bestehende Frage per id aktualisiert, keine neue Zeile
    $this->assertDatabaseHas('anamnesis_questions', [
        'id' => $questionToModify->id,
        'template_id' => $template->id,
        'question_text' => 'Wie alt ist dein Hund in Jahren?',
        'question_type' => 'textarea',
        'is_required' => false,
    ]);

    // Create-Semantik: neue Frage ohne id wurde angelegt
    $createdQuestion = AnamnesisQuestion::query()
        ->where('template_id', $template->id)
        ->where('question_text', 'Hat dein Hund Allergien?')
        ->first();
    expect($createdQuestion)->not->toBeNull();
    expect($createdQuestion->question_type)->toBe('radio');
    expect($createdQuestion->options)->toBe(['Ja', 'Nein']);

    // Delete-Semantik: ausgelassene, unbeantwortete Frage wurde gelöscht
    $this->assertDatabaseMissing('anamnesis_questions', ['id' => $questionToRemove->id]);
});

test('trainer cannot update another trainers template', function () {
    $template = AnamnesisTemplate::factory()->create(['trainer_id' => $this->trainer->id]);

    $data = ['name' => 'Unauthorized Update'];

    $response = $this->actingAs($this->anotherTrainer)
        ->putJson("/api/v1/anamnesis-templates/{$template->id}", $data);

    $response->assertForbidden();
});

test('admin cannot update template', function () {
    $template = AnamnesisTemplate::factory()->create(['trainer_id' => $this->trainer->id]);

    $data = ['name' => 'Admin Attempt'];

    $response = $this->actingAs($this->admin)
        ->putJson("/api/v1/anamnesis-templates/{$template->id}", $data);

    $response->assertForbidden();
});

test('trainer can delete own template', function () {
    $template = AnamnesisTemplate::factory()->create(['trainer_id' => $this->trainer->id]);

    $response = $this->actingAs($this->trainer)
        ->deleteJson("/api/v1/anamnesis-templates/{$template->id}");

    $response->assertNoContent();

    $this->assertDatabaseMissing('anamnesis_templates', ['id' => $template->id]);
});

test('trainer cannot delete other trainers template', function () {
    $template = AnamnesisTemplate::factory()->create(['trainer_id' => $this->anotherTrainer->id]);

    $response = $this->actingAs($this->trainer)
        ->deleteJson("/api/v1/anamnesis-templates/{$template->id}");

    $response->assertForbidden();
});

test('admin cannot delete template', function () {
    $template = AnamnesisTemplate::factory()->create();

    $response = $this->actingAs($this->admin)
        ->deleteJson("/api/v1/anamnesis-templates/{$template->id}");

    $response->assertForbidden();
});

test('cannot delete template with responses', function () {
    $template = AnamnesisTemplate::factory()->create(['trainer_id' => $this->trainer->id]);
    AnamnesisResponse::factory()->create(['template_id' => $template->id]);

    $response = $this->actingAs($this->trainer)
        ->deleteJson("/api/v1/anamnesis-templates/{$template->id}");

    $response->assertStatus(422)
        ->assertJsonPath('message', 'Cannot delete template with existing responses.');

    $this->assertDatabaseHas('anamnesis_templates', ['id' => $template->id]);
});

test('customer cannot delete template', function () {
    $template = AnamnesisTemplate::factory()->create(['trainer_id' => $this->trainer->id]);

    $response = $this->actingAs($this->customer)
        ->deleteJson("/api/v1/anamnesis-templates/{$template->id}");

    $response->assertForbidden();
});

test('admin cannot create anamnesis template', function () {
    $data = [
        'name' => 'Admin Template',
        'description' => 'Should not be created',
    ];

    $response = $this->actingAs($this->admin)
        ->postJson('/api/v1/anamnesis-templates', $data);

    $response->assertForbidden();
});

test('can get template questions ordered', function () {
    $template = AnamnesisTemplate::factory()->create();
    AnamnesisQuestion::factory()->create(['template_id' => $template->id, 'order' => 2]);
    AnamnesisQuestion::factory()->create(['template_id' => $template->id, 'order' => 0]);
    AnamnesisQuestion::factory()->create(['template_id' => $template->id, 'order' => 1]);

    $response = $this->actingAs($this->trainer)
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
