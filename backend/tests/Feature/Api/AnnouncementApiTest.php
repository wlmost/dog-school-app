<?php

declare(strict_types=1);

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);
uses()->group('api', 'announcement');

it('liefert am öffentlichen endpunkt nur aktive ankündigungen', function () {
    $active = Announcement::factory()->create();
    $expired = Announcement::factory()->expired()->create();

    $response = $this->getJson('/api/v1/announcements');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $active->id);

    expect($response->json('data.*.id'))->not->toContain($expired->id);
});

it('liefert am admin-endpunkt auch abgelaufene ankündigungen', function () {
    $admin = User::factory()->admin()->create();
    Announcement::factory()->create();
    Announcement::factory()->expired()->create();

    $response = $this->actingAs($admin)->getJson('/api/v1/admin/announcements');

    $response->assertOk()
        ->assertJsonCount(2, 'data');
});

it('weist die listenanfrage eines nicht-admins am admin-endpunkt mit 403 zurück', function () {
    $customer = User::factory()->customer()->create();

    $response = $this->actingAs($customer)->getJson('/api/v1/admin/announcements');

    $response->assertForbidden();
});

it('weist das erstellen einer ankündigung durch einen nicht-admin mit 403 zurück', function () {
    $customer = User::factory()->customer()->create();

    $response = $this->actingAs($customer)->postJson('/api/v1/admin/announcements', [
        'title' => 'Neuigkeit',
        'body' => '<p>Hallo</p>',
        'displayDays' => 7,
    ]);

    $response->assertForbidden();
});

it('weist das aktualisieren einer ankündigung durch einen nicht-admin mit 403 zurück', function () {
    $customer = User::factory()->customer()->create();
    $announcement = Announcement::factory()->create();

    $response = $this->actingAs($customer)->putJson(
        "/api/v1/admin/announcements/{$announcement->id}",
        ['title' => 'Neuer Titel']
    );

    $response->assertForbidden();
});

it('weist das löschen einer ankündigung durch einen nicht-admin mit 403 zurück', function () {
    $customer = User::factory()->customer()->create();
    $announcement = Announcement::factory()->create();

    $response = $this->actingAs($customer)->deleteJson("/api/v1/admin/announcements/{$announcement->id}");

    $response->assertForbidden();
});

it('speichert eine ankündigung mit bild-upload und liefert eine gültige imageUrl', function () {
    Storage::fake('public');
    $admin = User::factory()->admin()->create();
    $file = UploadedFile::fake()->image('banner.jpg');

    $response = $this->actingAs($admin)->postJson('/api/v1/admin/announcements', [
        'title' => 'Sommerferien',
        'body' => '<p>Wir haben geschlossen.</p>',
        'displayDays' => 14,
        'image' => $file,
    ]);

    $response->assertCreated();

    $created = Announcement::query()->latest('id')->firstOrFail();
    expect($created->image_path)->not->toBeNull();
    Storage::disk('public')->assertExists($created->image_path);

    $expectedUrl = Storage::disk('public')->url($created->image_path);
    $response->assertJsonPath('data.imageUrl', $expectedUrl);
});

it('entfernt rohes script- und onclick-html aus dem body-feld beim speichern', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->postJson('/api/v1/admin/announcements', [
        'title' => 'Wichtiger Hinweis',
        'body' => '<p onclick="alert(1)">Hallo</p><script>alert(1)</script>',
        'displayDays' => 5,
    ]);

    $response->assertCreated();

    $this->assertDatabaseHas('announcements', [
        'title' => 'Wichtiger Hinweis',
        'body' => '<p>Hallo</p>alert(1)',
    ]);
});

it('löscht das alte bild von der public-disk wenn beim aktualisieren ein neues bild hochgeladen wird', function () {
    Storage::fake('public');
    $admin = User::factory()->admin()->create();
    $oldFile = UploadedFile::fake()->image('old.jpg');
    $announcement = Announcement::factory()->create([
        'image_path' => $oldFile->store('announcement-images', 'public'),
    ]);
    $oldPath = $announcement->image_path;
    Storage::disk('public')->assertExists($oldPath);

    $newFile = UploadedFile::fake()->image('new.jpg');

    $response = $this->actingAs($admin)->put(
        "/api/v1/admin/announcements/{$announcement->id}",
        ['image' => $newFile]
    );

    $response->assertOk();

    Storage::disk('public')->assertMissing($oldPath);

    $newPath = $announcement->fresh()->image_path;
    expect($newPath)->not->toBe($oldPath);
    Storage::disk('public')->assertExists($newPath);
});

it('löscht das zugehörige bild mit wenn eine ankündigung gelöscht wird', function () {
    Storage::fake('public');
    $admin = User::factory()->admin()->create();
    $file = UploadedFile::fake()->image('to-delete.jpg');
    $announcement = Announcement::factory()->create([
        'image_path' => $file->store('announcement-images', 'public'),
    ]);
    Storage::disk('public')->assertExists($announcement->image_path);

    $response = $this->actingAs($admin)->deleteJson("/api/v1/admin/announcements/{$announcement->id}");

    $response->assertNoContent();

    Storage::disk('public')->assertMissing($announcement->image_path);
    $this->assertDatabaseMissing('announcements', ['id' => $announcement->id]);
});

it('weist einen displayDays-wert außerhalb von 1 bis 365 mit 422 zurück', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->postJson('/api/v1/admin/announcements', [
        'title' => 'Ungültige Dauer',
        'body' => '<p>Text</p>',
        'displayDays' => 400,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['displayDays']);
});

it('akzeptiert einen displayDays-wert von genau 1 als untere grenze', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->postJson('/api/v1/admin/announcements', [
        'title' => 'Kurzfristiger Hinweis',
        'body' => '<p>Text</p>',
        'displayDays' => 1,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.displayDays', 1);
});

it('akzeptiert einen displayDays-wert von genau 365 als obere grenze', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->postJson('/api/v1/admin/announcements', [
        'title' => 'Langfristiger Hinweis',
        'body' => '<p>Text</p>',
        'displayDays' => 365,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.displayDays', 365);
});

it('weist das erstellen einer ankündigung ohne titel mit 422 zurück', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->postJson('/api/v1/admin/announcements', [
        'body' => '<p>Text</p>',
        'displayDays' => 7,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['title']);
});

it('weist das erstellen einer ankündigung mit leerem body mit 422 zurück', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->postJson('/api/v1/admin/announcements', [
        'title' => 'Titel ohne Text',
        'body' => '',
        'displayDays' => 7,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['body']);
});

it('weist das erstellen einer ankündigung ohne body-feld mit 422 zurück', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->postJson('/api/v1/admin/announcements', [
        'title' => 'Titel ohne Body-Feld',
        'displayDays' => 7,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['body']);
});

it('liefert am öffentlichen endpunkt ausschließlich die im ressourcenvertrag definierten felder', function () {
    Announcement::factory()->create();

    $response = $this->getJson('/api/v1/announcements');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                ['id', 'title', 'body', 'imageUrl', 'displayDays', 'expiresAt', 'isActive', 'createdAt', 'updatedAt'],
            ],
        ]);

    expect(array_keys($response->json('data.0')))->toEqualCanonicalizing([
        'id', 'title', 'body', 'imageUrl', 'displayDays', 'expiresAt', 'isActive', 'createdAt', 'updatedAt',
    ]);
});
