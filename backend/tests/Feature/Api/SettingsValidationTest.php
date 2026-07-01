<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);
uses()->group('api', 'setting');

beforeEach(function () {
    Storage::fake('public');
    $this->admin = User::factory()->admin()->create();
});

// ======== company_favicon — ICO akzeptiert ========

it('akzeptiert eine ico-datei als company_favicon', function () {
    $favicon = UploadedFile::fake()->create('favicon.ico', 100, 'image/x-icon');

    $this->actingAs($this->admin)
        ->putJson('/api/v1/settings', [
            'company_favicon' => $favicon,
        ])
        ->assertOk();
});

it('akzeptiert eine ico-datei mit mime-typ image/vnd.microsoft.icon als company_favicon', function () {
    $favicon = UploadedFile::fake()->create('favicon.ico', 100, 'image/vnd.microsoft.icon');

    $this->actingAs($this->admin)
        ->putJson('/api/v1/settings', [
            'company_favicon' => $favicon,
        ])
        ->assertOk();
});

// ======== company_favicon — PNG akzeptiert ========

it('akzeptiert eine png-datei als company_favicon', function () {
    $favicon = UploadedFile::fake()->image('favicon.png');

    $this->actingAs($this->admin)
        ->putJson('/api/v1/settings', [
            'company_favicon' => $favicon,
        ])
        ->assertOk();
});

// ======== company_favicon — unerlaubter MIME-Typ abgelehnt ========

it('weist eine exe-datei als company_favicon zurück', function () {
    $file = UploadedFile::fake()->create('favicon.exe', 100, 'application/x-msdownload');

    $this->actingAs($this->admin)
        ->putJson('/api/v1/settings', [
            'company_favicon' => $file,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['company_favicon']);
});

// ======== company_favicon — Datei über 512 KB abgelehnt ========

it('weist eine datei über 512 kb als company_favicon zurück', function () {
    $oversized = UploadedFile::fake()->create('big.ico', 513, 'image/x-icon');

    $this->actingAs($this->admin)
        ->putJson('/api/v1/settings', [
            'company_favicon' => $oversized,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['company_favicon']);
});

// ======== company_favicon — fehlendes Feld löst keinen Fehler aus ========

it('verursacht keinen validierungsfehler wenn company_favicon nicht gesendet wird', function () {
    $this->actingAs($this->admin)
        ->putJson('/api/v1/settings', [
            'company_name' => 'Musterhundeschule',
        ])
        ->assertOk();
});

// ======== company_logo — image-Regel bleibt unberührt ========

it('akzeptiert weiterhin eine png-datei als company_logo', function () {
    $logo = UploadedFile::fake()->image('logo.png');

    $this->actingAs($this->admin)
        ->putJson('/api/v1/settings', [
            'company_logo' => $logo,
        ])
        ->assertOk();
});
