<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
uses()->group('feature', 'trainers');

beforeEach(function () {
    $this->admin    = User::factory()->admin()->create();
    $this->trainer  = User::factory()->trainer()->create();
    $this->customer = User::factory()->customer()->create();
});

describe('Admin', function () {
    it('listet alle trainer auf', function () {
        $this->actingAs($this->admin)
            ->getJson('/api/v1/trainers')
            ->assertOk();
    });

    it('erstellt einen neuen trainer', function () {
        $this->actingAs($this->admin)
            ->postJson('/api/v1/trainers', [
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'email' => 'max.mustermann@example.com',
                'password' => 'Password123!',
            ])
            ->assertCreated();
    });

    it('zeigt einen einzelnen trainer an', function () {
        $this->actingAs($this->admin)
            ->getJson('/api/v1/trainers/' . $this->trainer->id)
            ->assertOk();
    });

    it('aktualisiert einen trainer', function () {
        $this->actingAs($this->admin)
            ->putJson('/api/v1/trainers/' . $this->trainer->id, [
                'firstName' => 'Hans',
            ])
            ->assertOk();
    });

    it('löscht einen trainer', function () {
        $this->actingAs($this->admin)
            ->deleteJson('/api/v1/trainers/' . $this->trainer->id)
            ->assertOk();
    });
});

describe('Trainer-Rolle', function () {
    it('erhält 403 beim auflisten von trainern', function () {
        $this->actingAs($this->trainer)
            ->getJson('/api/v1/trainers')
            ->assertForbidden();
    });

    it('erhält 403 beim erstellen eines trainers', function () {
        $this->actingAs($this->trainer)
            ->postJson('/api/v1/trainers', [
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'email' => 'neu@example.com',
                'password' => 'Password123!',
            ])
            ->assertForbidden();
    });

    it('erhält 403 beim anzeigen eines trainers', function () {
        $this->actingAs($this->trainer)
            ->getJson('/api/v1/trainers/' . $this->trainer->id)
            ->assertForbidden();
    });

    it('erhält 403 beim aktualisieren eines trainers', function () {
        $this->actingAs($this->trainer)
            ->putJson('/api/v1/trainers/' . $this->trainer->id, [
                'firstName' => 'Hans',
            ])
            ->assertForbidden();
    });

    it('erhält 403 beim löschen eines trainers', function () {
        $this->actingAs($this->trainer)
            ->deleteJson('/api/v1/trainers/' . $this->trainer->id)
            ->assertForbidden();
    });
});

describe('Customer-Rolle', function () {
    it('erhält 403 beim auflisten von trainern', function () {
        $this->actingAs($this->customer)
            ->getJson('/api/v1/trainers')
            ->assertForbidden();
    });

    it('erhält 403 beim erstellen eines trainers', function () {
        $this->actingAs($this->customer)
            ->postJson('/api/v1/trainers', [
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'email' => 'neu2@example.com',
                'password' => 'Password123!',
            ])
            ->assertForbidden();
    });

    it('erhält 403 beim anzeigen eines trainers', function () {
        $this->actingAs($this->customer)
            ->getJson('/api/v1/trainers/' . $this->trainer->id)
            ->assertForbidden();
    });

    it('erhält 403 beim aktualisieren eines trainers', function () {
        $this->actingAs($this->customer)
            ->putJson('/api/v1/trainers/' . $this->trainer->id, [
                'firstName' => 'Hans',
            ])
            ->assertForbidden();
    });

    it('erhält 403 beim löschen eines trainers', function () {
        $this->actingAs($this->customer)
            ->deleteJson('/api/v1/trainers/' . $this->trainer->id)
            ->assertForbidden();
    });
});

describe('Unauthenticated', function () {
    it('erhält 401 beim auflisten von trainern', function () {
        $this->getJson('/api/v1/trainers')
            ->assertUnauthorized();
    });

    it('erhält 401 beim erstellen eines trainers', function () {
        $this->postJson('/api/v1/trainers', [
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'email' => 'neu3@example.com',
            'password' => 'Password123!',
        ])
            ->assertUnauthorized();
    });

    it('erhält 401 beim löschen eines trainers', function () {
        $this->deleteJson('/api/v1/trainers/' . $this->trainer->id)
            ->assertUnauthorized();
    });
});
