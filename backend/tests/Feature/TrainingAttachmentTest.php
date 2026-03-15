<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Dog;
use App\Models\TrainingAttachment;
use App\Models\TrainingLog;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    
    $this->admin = User::factory()->admin()->create();
    $this->trainer = User::factory()->trainer()->create();
    $this->customer = User::factory()->customer()->create();
    
    $customerRecord = Customer::factory()->create(['user_id' => $this->customer->id]);
    $this->dog = Dog::factory()->create(['customer_id' => $customerRecord->id]);
    
    $session = TrainingSession::factory()->create(['trainer_id' => $this->trainer->id]);
    
    $this->trainingLog = TrainingLog::factory()->create([
        'dog_id' => $this->dog->id,
        'training_session_id' => $session->id,
        'trainer_id' => $this->trainer->id,
    ]);
});

// ============================================================================
// File Upload Tests
// ============================================================================

test('admin can upload image attachment', function () {
    $file = UploadedFile::fake()->image('training-photo.jpg');
    
    $response = $this->actingAs($this->admin)
        ->postJson('/api/v1/training-attachments', [
            'trainingLogId' => $this->trainingLog->id,
            'file' => $file,
        ]);
    
    $response->assertCreated()
        ->assertJsonStructure([
            'data' => [
                'id',
                'trainingLogId',
                'fileType',
                'filePath',
                'fileName',
                'downloadUrl',
                'uploadedAt',
            ],
        ])
        ->assertJson([
            'data' => [
                'trainingLogId' => $this->trainingLog->id,
                'fileType' => 'image',
                'fileName' => 'training-photo.jpg',
            ],
        ]);
    
    $this->assertDatabaseHas('training_attachments', [
        'training_log_id' => $this->trainingLog->id,
        'file_type' => 'image',
        'file_name' => 'training-photo.jpg',
    ]);
    
    $attachment = TrainingAttachment::first();
    Storage::disk('public')->assertExists($attachment->file_path);
});

test('trainer can upload video attachment', function () {
    $file = UploadedFile::fake()->create('training-video.mp4', 5000, 'video/mp4');
    
    $response = $this->actingAs($this->trainer)
        ->postJson('/api/v1/training-attachments', [
            'trainingLogId' => $this->trainingLog->id,
            'file' => $file,
        ]);
    
    $response->assertCreated()
        ->assertJson([
            'data' => [
                'fileType' => 'video',
                'fileName' => 'training-video.mp4',
            ],
        ]);
});

test('trainer can upload document attachment', function () {
    $file = UploadedFile::fake()->create('training-report.pdf', 1000, 'application/pdf');
    
    $response = $this->actingAs($this->trainer)
        ->postJson('/api/v1/training-attachments', [
            'trainingLogId' => $this->trainingLog->id,
            'file' => $file,
        ]);
    
    $response->assertCreated()
        ->assertJson([
            'data' => [
                'fileType' => 'document',
                'fileName' => 'training-report.pdf',
            ],
        ]);
});

test('customer cannot upload attachments', function () {
    $file = UploadedFile::fake()->image('photo.jpg');
    
    $this->actingAs($this->customer)
        ->postJson('/api/v1/training-attachments', [
            'trainingLogId' => $this->trainingLog->id,
            'file' => $file,
        ])
        ->assertForbidden();
});

test('unauthenticated user cannot upload attachments', function () {
    $file = UploadedFile::fake()->image('photo.jpg');
    
    $this->postJson('/api/v1/training-attachments', [
        'trainingLogId' => $this->trainingLog->id,
        'file' => $file,
    ])
        ->assertUnauthorized();
});

// ============================================================================
// File Validation Tests
// ============================================================================

test('upload requires file', function () {
    $this->actingAs($this->admin)
        ->postJson('/api/v1/training-attachments', [
            'trainingLogId' => $this->trainingLog->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['file']);
});

test('upload requires valid training log id', function () {
    $file = UploadedFile::fake()->image('photo.jpg');
    
    $this->actingAs($this->admin)
        ->postJson('/api/v1/training-attachments', [
            'trainingLogId' => 99999,
            'file' => $file,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['training_log_id']);
});

test('upload rejects files larger than 50MB', function () {
    $file = UploadedFile::fake()->create('large-file.mp4', 51201, 'video/mp4'); // 50MB + 1KB
    
    $this->actingAs($this->admin)
        ->postJson('/api/v1/training-attachments', [
            'trainingLogId' => $this->trainingLog->id,
            'file' => $file,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['file']);
});

test('upload rejects invalid file types', function () {
    $file = UploadedFile::fake()->create('script.exe', 100, 'application/x-msdownload');
    
    $this->actingAs($this->admin)
        ->postJson('/api/v1/training-attachments', [
            'trainingLogId' => $this->trainingLog->id,
            'file' => $file,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['file']);
});

test('upload accepts all valid image formats', function () {
    $formats = [
        ['ext' => 'jpg', 'mime' => 'image/jpeg'],
        ['ext' => 'jpeg', 'mime' => 'image/jpeg'],
        ['ext' => 'png', 'mime' => 'image/png'],
    ];
    
    foreach ($formats as $format) {
        Storage::fake('public');
        $file = UploadedFile::fake()->create("image.{$format['ext']}", 100, $format['mime']);
        
        $this->actingAs($this->admin)
            ->postJson('/api/v1/training-attachments', [
                'trainingLogId' => $this->trainingLog->id,
                'file' => $file,
            ])
            ->assertCreated();
    }
});

// ============================================================================
// File Download Tests
// ============================================================================

test('admin can download attachment', function () {
    $file = UploadedFile::fake()->image('test.jpg');
    
    $attachment = TrainingAttachment::factory()->create([
        'training_log_id' => $this->trainingLog->id,
        'file_type' => 'image',
        'file_path' => $file->store('training-attachments', 'public'),
        'file_name' => 'test.jpg',
    ]);
    
    $response = $this->actingAs($this->admin)
        ->get('/api/v1/training-attachments/' . $attachment->id . '/download');
    
    $response->assertOk();
    $response->assertHeader('content-type', 'image/jpeg');
    $response->assertHeader('content-disposition', 'attachment; filename=test.jpg');
});

test('trainer can download attachment', function () {
    $file = UploadedFile::fake()->image('test.jpg');
    
    $attachment = TrainingAttachment::factory()->create([
        'training_log_id' => $this->trainingLog->id,
        'file_type' => 'image',
        'file_path' => $file->store('training-attachments', 'public'),
        'file_name' => 'test.jpg',
    ]);
    
    $response = $this->actingAs($this->trainer)
        ->get('/api/v1/training-attachments/' . $attachment->id . '/download');
    
    $response->assertOk();
});

test('customer can download their own dogs attachment', function () {
    $file = UploadedFile::fake()->image('test.jpg');
    
    $attachment = TrainingAttachment::factory()->create([
        'training_log_id' => $this->trainingLog->id,
        'file_type' => 'image',
        'file_path' => $file->store('training-attachments', 'public'),
        'file_name' => 'test.jpg',
    ]);
    
    $response = $this->actingAs($this->customer)
        ->get('/api/v1/training-attachments/' . $attachment->id . '/download');
    
    $response->assertOk();
});

test('customer cannot download other customers attachments', function () {
    $otherCustomer = User::factory()->customer()->create();
    $otherCustomerRecord = Customer::factory()->create(['user_id' => $otherCustomer->id]);
    $otherDog = Dog::factory()->create(['customer_id' => $otherCustomerRecord->id]);
    
    $session = TrainingSession::factory()->create(['trainer_id' => $this->trainer->id]);
    $otherLog = TrainingLog::factory()->create([
        'dog_id' => $otherDog->id,
        'training_session_id' => $session->id,
        'trainer_id' => $this->trainer->id,
    ]);
    
    $file = UploadedFile::fake()->image('test.jpg');
    $attachment = TrainingAttachment::factory()->create([
        'training_log_id' => $otherLog->id,
        'file_type' => 'image',
        'file_path' => $file->store('training-attachments', 'public'),
        'file_name' => 'test.jpg',
    ]);
    
    $this->actingAs($this->customer)
        ->get('/api/v1/training-attachments/' . $attachment->id . '/download')
        ->assertForbidden();
});

test('returns 404 for non-existent attachment download', function () {
    $this->actingAs($this->admin)
        ->get('/api/v1/training-attachments/99999/download')
        ->assertNotFound();
});

// ============================================================================
// File Deletion Tests
// ============================================================================

test('admin can delete any attachment', function () {
    $file = UploadedFile::fake()->image('test.jpg');
    
    $attachment = TrainingAttachment::factory()->create([
        'training_log_id' => $this->trainingLog->id,
        'file_type' => 'image',
        'file_path' => $file->store('training-attachments', 'public'),
        'file_name' => 'test.jpg',
    ]);
    
    $filePath = $attachment->file_path;
    Storage::disk('public')->assertExists($filePath);
    
    $this->actingAs($this->admin)
        ->deleteJson('/api/v1/training-attachments/' . $attachment->id)
        ->assertNoContent();
    
    $this->assertDatabaseMissing('training_attachments', ['id' => $attachment->id]);
    Storage::disk('public')->assertMissing($filePath);
});

test('trainer can delete their own uploaded attachments', function () {
    $file = UploadedFile::fake()->image('test.jpg');
    
    $attachment = TrainingAttachment::factory()->create([
        'training_log_id' => $this->trainingLog->id,
        'file_type' => 'image',
        'file_path' => $file->store('training-attachments', 'public'),
        'file_name' => 'test.jpg',
    ]);
    
    $this->actingAs($this->trainer)
        ->deleteJson('/api/v1/training-attachments/' . $attachment->id)
        ->assertNoContent();
});

test('trainer cannot delete other trainers attachments', function () {
    $otherTrainer = User::factory()->trainer()->create();
    $session = TrainingSession::factory()->create(['trainer_id' => $otherTrainer->id]);
    $log = TrainingLog::factory()->create([
        'dog_id' => $this->dog->id,
        'training_session_id' => $session->id,
        'trainer_id' => $otherTrainer->id,
    ]);
    
    $file = UploadedFile::fake()->image('test.jpg');
    $attachment = TrainingAttachment::factory()->create([
        'training_log_id' => $log->id,
        'file_type' => 'image',
        'file_path' => $file->store('training-attachments', 'public'),
        'file_name' => 'test.jpg',
    ]);
    
    $this->actingAs($this->trainer)
        ->deleteJson('/api/v1/training-attachments/' . $attachment->id)
        ->assertForbidden();
});

test('customer cannot delete attachments', function () {
    $file = UploadedFile::fake()->image('test.jpg');
    
    $attachment = TrainingAttachment::factory()->create([
        'training_log_id' => $this->trainingLog->id,
        'file_type' => 'image',
        'file_path' => $file->store('training-attachments', 'public'),
        'file_name' => 'test.jpg',
    ]);
    
    $this->actingAs($this->customer)
        ->deleteJson('/api/v1/training-attachments/' . $attachment->id)
        ->assertForbidden();
});

// ============================================================================
// List/Index Tests
// ============================================================================

test('admin can list all attachments', function () {
    TrainingAttachment::factory()->count(3)->create([
        'training_log_id' => $this->trainingLog->id,
    ]);
    
    $response = $this->actingAs($this->admin)
        ->getJson('/api/v1/training-attachments');
    
    $response->assertOk()
        ->assertJsonCount(3, 'data');
});

test('can filter attachments by training log', function () {
    TrainingAttachment::factory()->count(2)->create([
        'training_log_id' => $this->trainingLog->id,
    ]);
    
    $otherLog = TrainingLog::factory()->create([
        'dog_id' => $this->dog->id,
        'training_session_id' => $this->trainingLog->training_session_id,
        'trainer_id' => $this->trainer->id,
    ]);
    
    TrainingAttachment::factory()->count(1)->create([
        'training_log_id' => $otherLog->id,
    ]);
    
    $response = $this->actingAs($this->admin)
        ->getJson('/api/v1/training-attachments?trainingLogId=' . $this->trainingLog->id);
    
    $response->assertOk()
        ->assertJsonCount(2, 'data');
});

test('can filter attachments by file type', function () {
    TrainingAttachment::factory()->count(2)->create([
        'training_log_id' => $this->trainingLog->id,
        'file_type' => 'image',
    ]);
    
    TrainingAttachment::factory()->count(1)->create([
        'training_log_id' => $this->trainingLog->id,
        'file_type' => 'video',
    ]);
    
    $response = $this->actingAs($this->admin)
        ->getJson('/api/v1/training-attachments?fileType=image');
    
    $response->assertOk()
        ->assertJsonCount(2, 'data');
});

test('customer can only list their own dogs attachments', function () {
    // Create attachments for this customer's dog
    TrainingAttachment::factory()->count(2)->create([
        'training_log_id' => $this->trainingLog->id,
    ]);
    
    // Create attachments for another customer's dog
    $otherCustomer = User::factory()->customer()->create();
    $otherCustomerRecord = Customer::factory()->create(['user_id' => $otherCustomer->id]);
    $otherDog = Dog::factory()->create(['customer_id' => $otherCustomerRecord->id]);
    $session = TrainingSession::factory()->create(['trainer_id' => $this->trainer->id]);
    $otherLog = TrainingLog::factory()->create([
        'dog_id' => $otherDog->id,
        'training_session_id' => $session->id,
        'trainer_id' => $this->trainer->id,
    ]);
    
    TrainingAttachment::factory()->count(3)->create([
        'training_log_id' => $otherLog->id,
    ]);
    
    $response = $this->actingAs($this->customer)
        ->getJson('/api/v1/training-attachments');
    
    $response->assertOk()
        ->assertJsonCount(2, 'data');
});

// ============================================================================
// Show Attachment Tests
// ============================================================================

test('can retrieve single attachment', function () {
    $attachment = TrainingAttachment::factory()->create([
        'training_log_id' => $this->trainingLog->id,
        'file_name' => 'test-file.jpg',
    ]);
    
    $response = $this->actingAs($this->admin)
        ->getJson('/api/v1/training-attachments/' . $attachment->id);
    
    $response->assertOk()
        ->assertJson([
            'data' => [
                'id' => $attachment->id,
                'fileName' => 'test-file.jpg',
            ],
        ]);
});

test('returns 404 for non-existent attachment', function () {
    $this->actingAs($this->admin)
        ->getJson('/api/v1/training-attachments/99999')
        ->assertNotFound();
});

// ============================================================================
// File Storage Tests
// ============================================================================

test('uploaded file is stored in correct directory', function () {
    $file = UploadedFile::fake()->image('photo.jpg');
    
    $response = $this->actingAs($this->admin)
        ->postJson('/api/v1/training-attachments', [
            'trainingLogId' => $this->trainingLog->id,
            'file' => $file,
        ]);
    
    $response->assertCreated();
    
    $attachment = TrainingAttachment::first();
    expect($attachment->file_path)->toContain('training-attachments/' . $this->trainingLog->id);
    Storage::disk('public')->assertExists($attachment->file_path);
});

test('file names are sanitized', function () {
    $file = UploadedFile::fake()->create('test file with spaces!@#.jpg', 100, 'image/jpeg');
    
    $response = $this->actingAs($this->admin)
        ->postJson('/api/v1/training-attachments', [
            'trainingLogId' => $this->trainingLog->id,
            'file' => $file,
        ]);
    
    $response->assertCreated();
    
    $attachment = TrainingAttachment::first();
    expect($attachment->file_path)->not->toContain(' ')
        ->and($attachment->file_path)->not->toContain('!')
        ->and($attachment->file_path)->not->toContain('@')
        ->and($attachment->file_path)->not->toContain('#');
});
