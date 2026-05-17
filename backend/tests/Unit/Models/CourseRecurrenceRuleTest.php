<?php

declare(strict_types=1);

use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);
uses()->group('unit', 'course');

it('speichert recurrence_rule als array und liest es korrekt zurück', function () {
    $rule = [
        'type' => 'weekly',
        'weekday' => 1,
        'startDate' => '2025-03-03',
        'startTime' => '09:00',
        'endTime' => '10:00',
        'count' => 8,
    ];

    $course = Course::factory()->create(['recurrence_rule' => $rule]);

    $fresh = $course->fresh();

    expect($fresh->recurrence_rule)->toBeArray();
    expect($fresh->recurrence_rule)->toEqual($rule);
});

it('setzt recurrence_rule auf null wenn das feld beim erstellen weggelassen wird', function () {
    $course = Course::factory()->create();

    $fresh = $course->fresh();

    expect($fresh->recurrence_rule)->toBeNull();
});

it('gibt recurrence_rule als array zurück und nicht als json-string', function () {
    $rule = ['type' => 'weekly', 'weekday' => 3, 'count' => 5];

    $course = Course::factory()->create(['recurrence_rule' => $rule]);

    $fresh = $course->fresh();

    expect($fresh->recurrence_rule)->toBeArray();
    expect($fresh->recurrence_rule)->not->toBeString();
});

it('akzeptiert eine weekly-regelstruktur mit allen feldern', function () {
    $rule = [
        'type' => 'weekly',
        'weekday' => 1,
        'startDate' => '2025-03-03',
        'startTime' => '10:00',
        'endTime' => '11:00',
        'count' => 6,
        'location' => 'Trainingsgelände A',
        'maxParticipants' => 8,
    ];

    $course = Course::factory()->create(['recurrence_rule' => $rule]);

    $fresh = $course->fresh();

    expect($fresh->recurrence_rule)->toBeArray();
    expect($fresh->recurrence_rule['type'])->toBe('weekly');
    expect($fresh->recurrence_rule['weekday'])->toBe(1);
    expect($fresh->recurrence_rule['count'])->toBe(6);
    expect($fresh->recurrence_rule['location'])->toBe('Trainingsgelände A');
});

it('akzeptiert eine monthly-regelstruktur mit allen feldern', function () {
    $rule = [
        'type' => 'monthly',
        'dayOfMonth' => 15,
        'startDate' => '2025-03-01',
        'startTime' => '14:00',
        'endTime' => '15:30',
        'count' => 12,
    ];

    $course = Course::factory()->create(['recurrence_rule' => $rule]);

    $fresh = $course->fresh();

    expect($fresh->recurrence_rule)->toBeArray();
    expect($fresh->recurrence_rule['type'])->toBe('monthly');
    expect($fresh->recurrence_rule['dayOfMonth'])->toBe(15);
    expect($fresh->recurrence_rule['count'])->toBe(12);
});

it('lässt recurrence_rule auf null setzen nach dem erstellen', function () {
    $rule = ['type' => 'weekly', 'count' => 4];

    $course = Course::factory()->create(['recurrence_rule' => $rule]);
    $course->update(['recurrence_rule' => null]);

    $fresh = $course->fresh();

    expect($fresh->recurrence_rule)->toBeNull();
});
