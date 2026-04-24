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

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->trainer = User::factory()->trainer()->create();
    $this->customer = User::factory()->customer()->create();
    
    $customerRecord = Customer::factory()->create(['user_id' => $this->customer->id]);
    $this->dog = Dog::factory()->create(['customer_id' => $customerRecord->id]);
    
    // Create an anamnesis template with questions
    $this->template = AnamnesisTemplate::factory()->create([
        'trainer_id' => $this->trainer->id,
        'name' => 'Standard Hunde-Anamnese',
        'description' => 'Grundlegende Verhaltens- und Gesundheitsanamnese',
    ]);
    
    // Create various question types
    $this->questions = [
        AnamnesisQuestion::factory()->create([
            'template_id' => $this->template->id,
            'question_text' => 'Wie alt ist Ihr Hund?',
            'question_type' => 'text',
            'is_required' => true,
            'order' => 1,
        ]),
        AnamnesisQuestion::factory()->create([
            'template_id' => $this->template->id,
            'question_text' => 'Beschreiben Sie das Verhalten Ihres Hundes',
            'question_type' => 'textarea',
            'is_required' => true,
            'order' => 2,
        ]),
        AnamnesisQuestion::factory()->create([
            'template_id' => $this->template->id,
            'question_text' => 'Hat Ihr Hund Angst vor anderen Hunden?',
            'question_type' => 'radio',
            'options' => ['Ja', 'Nein', 'Manchmal'],
            'is_required' => true,
            'order' => 3,
        ]),
        AnamnesisQuestion::factory()->create([
            'template_id' => $this->template->id,
            'question_text' => 'Welche Kommandos beherrscht Ihr Hund?',
            'question_type' => 'checkbox',
            'options' => ['Sitz', 'Platz', 'Bleib', 'Hier', 'Fuß'],
            'is_required' => false,
            'order' => 4,
        ]),
        AnamnesisQuestion::factory()->create([
            'template_id' => $this->template->id,
            'question_text' => 'Wie bewerten Sie das Sozialverhalten? (1-10)',
            'question_type' => 'text',
            'is_required' => false,
            'order' => 5,
        ]),
    ];
    
    // Create anamnesis response
    $this->response = AnamnesisResponse::factory()->create([
        'dog_id' => $this->dog->id,
        'template_id' => $this->template->id,
        'completed_at' => now(),
        'completed_by' => $this->trainer->id,
    ]);
    
    // Create answers
    AnamnesisAnswer::factory()->create([
        'response_id' => $this->response->id,
        'question_id' => $this->questions[0]->id,
        'answer_value' => '3 Jahre',
    ]);
    
    AnamnesisAnswer::factory()->create([
        'response_id' => $this->response->id,
        'question_id' => $this->questions[1]->id,
        'answer_value' => 'Der Hund ist sehr freundlich und aufgeschlossen. Zeigt gelegentlich Angstverhalten bei lauten Geräuschen.',
    ]);
    
    AnamnesisAnswer::factory()->create([
        'response_id' => $this->response->id,
        'question_id' => $this->questions[2]->id,
        'answer_value' => 'Manchmal',
    ]);
    
    AnamnesisAnswer::factory()->create([
        'response_id' => $this->response->id,
        'question_id' => $this->questions[3]->id,
        'answer_value' => json_encode(['Sitz', 'Platz', 'Hier']),
    ]);
    
    AnamnesisAnswer::factory()->create([
        'response_id' => $this->response->id,
        'question_id' => $this->questions[4]->id,
        'answer_value' => '8',
    ]);
});

// ============================================================================
// PDF Download Authorization Tests
// ============================================================================

test('admin can download anamnesis response as PDF', function () {
    $response = $this->actingAs($this->admin)
        ->getJson('/api/v1/anamnesis-responses/' . $this->response->id . '/pdf');
    
    $response->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('trainer can download anamnesis response as PDF', function () {
    $response = $this->actingAs($this->trainer)
        ->getJson('/api/v1/anamnesis-responses/' . $this->response->id . '/pdf');
    
    $response->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('customer can download their own dogs anamnesis response as PDF', function () {
    $response = $this->actingAs($this->customer)
        ->getJson('/api/v1/anamnesis-responses/' . $this->response->id . '/pdf');
    
    $response->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('customer cannot download other customers dogs anamnesis response PDF', function () {
    $otherCustomer = User::factory()->customer()->create();
    $otherCustomerRecord = Customer::factory()->create(['user_id' => $otherCustomer->id]);
    $otherDog = Dog::factory()->create(['customer_id' => $otherCustomerRecord->id]);
    
    $otherResponse = AnamnesisResponse::factory()->create([
        'dog_id' => $otherDog->id,
        'template_id' => $this->template->id,
    ]);
    
    $this->actingAs($this->customer)
        ->getJson('/api/v1/anamnesis-responses/' . $otherResponse->id . '/pdf')
        ->assertForbidden();
});

test('unauthenticated user cannot download anamnesis response PDF', function () {
    $this->getJson('/api/v1/anamnesis-responses/' . $this->response->id . '/pdf')
        ->assertUnauthorized();
});

// ============================================================================
// PDF Content Validation Tests
// ============================================================================

test('PDF includes dog information', function () {
    $response = $this->actingAs($this->admin)
        ->get('/api/v1/anamnesis-responses/' . $this->response->id . '/pdf');
    
    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
    expect($response->getContent())->not()->toBeEmpty();
});

test('PDF includes template information', function () {
    $response = $this->actingAs($this->admin)
        ->get('/api/v1/anamnesis-responses/' . $this->response->id . '/pdf');
    
    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
    expect($response->getContent())->not()->toBeEmpty();
});

test('PDF includes customer information', function () {
    $response = $this->actingAs($this->admin)
        ->get('/api/v1/anamnesis-responses/' . $this->response->id . '/pdf');
    
    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
    expect($response->getContent())->not()->toBeEmpty();
});

test('PDF includes all questions', function () {
    $response = $this->actingAs($this->admin)
        ->get('/api/v1/anamnesis-responses/' . $this->response->id . '/pdf');
    
    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
    expect($response->getContent())->not()->toBeEmpty();
});

test('PDF includes all answers', function () {
    $response = $this->actingAs($this->admin)
        ->get('/api/v1/anamnesis-responses/' . $this->response->id . '/pdf');
    
    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
    expect($response->getContent())->not()->toBeEmpty();
});

test('PDF shows completed status correctly', function () {
    $response = $this->actingAs($this->admin)
        ->get('/api/v1/anamnesis-responses/' . $this->response->id . '/pdf');
    
    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
    expect($response->getContent())->not()->toBeEmpty();
});

test('PDF shows incomplete status correctly', function () {
    $this->response->update(['completed_at' => null, 'completed_by' => null]);
    
    $response = $this->actingAs($this->admin)
        ->get('/api/v1/anamnesis-responses/' . $this->response->id . '/pdf');
    
    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
    expect($response->getContent())->not()->toBeEmpty();
});

test('PDF includes completion date when completed', function () {
    $response = $this->actingAs($this->admin)
        ->get('/api/v1/anamnesis-responses/' . $this->response->id . '/pdf');
    
    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
    expect($response->getContent())->not()->toBeEmpty();
});

test('PDF includes completedBy user when completed', function () {
    $response = $this->actingAs($this->admin)
        ->get('/api/v1/anamnesis-responses/' . $this->response->id . '/pdf');
    
    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
    expect($response->getContent())->not()->toBeEmpty();
});

test('PDF handles text answers correctly', function () {
    $response = $this->actingAs($this->admin)
        ->get('/api/v1/anamnesis-responses/' . $this->response->id . '/pdf');
    
    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
    expect($response->getContent())->not()->toBeEmpty();
});

test('PDF handles textarea answers correctly', function () {
    $response = $this->actingAs($this->admin)
        ->get('/api/v1/anamnesis-responses/' . $this->response->id . '/pdf');
    
    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
    expect($response->getContent())->not()->toBeEmpty();
});

test('PDF handles radio answers correctly', function () {
    $response = $this->actingAs($this->admin)
        ->get('/api/v1/anamnesis-responses/' . $this->response->id . '/pdf');
    
    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
    expect($response->getContent())->not()->toBeEmpty();
});

test('PDF handles checkbox answers correctly', function () {
    $response = $this->actingAs($this->admin)
        ->get('/api/v1/anamnesis-responses/' . $this->response->id . '/pdf');
    
    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
    expect($response->getContent())->not()->toBeEmpty();
});

test('PDF handles rating answers correctly', function () {
    $response = $this->actingAs($this->admin)
        ->get('/api/v1/anamnesis-responses/' . $this->response->id . '/pdf');
    
    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
    expect($response->getContent())->not()->toBeEmpty();
});

test('PDF shows unanswered questions appropriately', function () {
    // Create a question without an answer
    $unansweredQuestion = AnamnesisQuestion::factory()->create([
        'template_id' => $this->template->id,
        'question_text' => 'Diese Frage wurde nicht beantwortet',
        'question_type' => 'text',
        'is_required' => false,
        'order' => 6,
    ]);
    
    $response = $this->actingAs($this->admin)
        ->get('/api/v1/anamnesis-responses/' . $this->response->id . '/pdf');
    
    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
    expect($response->getContent())->not()->toBeEmpty();
});

// ============================================================================
// PDF Technical Tests
// ============================================================================

test('PDF filename includes dog name and response ID', function () {
    $response = $this->actingAs($this->admin)
        ->getJson('/api/v1/anamnesis-responses/' . $this->response->id . '/pdf');
    
    $expectedFilename = 'anamnesis-' . $this->dog->name . '-' . $this->response->id . '.pdf';
    
    $response->assertHeader('content-disposition');
    $disposition = $response->headers->get('content-disposition');
    expect($disposition)->toContain($expectedFilename);
});

test('returns 404 for non-existent anamnesis response PDF', function () {
    $this->actingAs($this->admin)
        ->getJson('/api/v1/anamnesis-responses/99999/pdf')
        ->assertNotFound();
});

// ============================================================================
// PDF Edge Cases Tests
// ============================================================================

test('PDF generation works with response without answers', function () {
    AnamnesisAnswer::query()->where('response_id', $this->response->id)->delete();
    
    $response = $this->actingAs($this->admin)
        ->get('/api/v1/anamnesis-responses/' . $this->response->id . '/pdf');
    
    $response->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('PDF generation works with minimal dog data', function () {
    $this->dog->update([
        'breed' => null,
        'date_of_birth' => null,
        'gender' => null,
        'is_neutered' => null,
        'chip_number' => null,
    ]);
    
    $response = $this->actingAs($this->admin)
        ->get('/api/v1/anamnesis-responses/' . $this->response->id . '/pdf');
    
    $response->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('PDF generation works with minimal customer data', function () {
    $this->dog->customer->update([
        'phone' => null,
        'address_line1' => null,
        'address_line2' => null,
        'postal_code' => null,
        'city' => null,
        'country' => null,
    ]);
    
    $response = $this->actingAs($this->admin)
        ->get('/api/v1/anamnesis-responses/' . $this->response->id . '/pdf');
    
    $response->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('PDF generation works with template without description', function () {
    $this->template->update(['description' => null]);
    
    $response = $this->actingAs($this->admin)
        ->get('/api/v1/anamnesis-responses/' . $this->response->id . '/pdf');
    
    $response->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('PDF generation works with long text answers', function () {
    $longText = str_repeat('Dies ist ein sehr langer Text. ', 100);
    
    AnamnesisAnswer::query()
        ->where('response_id', $this->response->id)
        ->where('question_id', $this->questions[1]->id)
        ->update(['answer_value' => $longText]);
    
    $response = $this->actingAs($this->admin)
        ->get('/api/v1/anamnesis-responses/' . $this->response->id . '/pdf');
    
    $response->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('PDF generation works with special characters in answers', function () {
    AnamnesisAnswer::query()
        ->where('response_id', $this->response->id)
        ->where('question_id', $this->questions[0]->id)
        ->update(['answer_value' => 'Spezial: äöü ÄÖÜ ß € <>&"\'']);
    
    $response = $this->actingAs($this->admin)
        ->get('/api/v1/anamnesis-responses/' . $this->response->id . '/pdf');
    
    $response->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});
