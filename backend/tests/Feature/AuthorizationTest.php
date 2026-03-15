<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

test('admin gate allows admin users', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin);

    expect(Gate::allows('admin'))->toBeTrue();
});

test('admin gate denies trainer users', function () {
    $trainer = User::factory()->create(['role' => 'trainer']);

    $this->actingAs($trainer);

    expect(Gate::allows('admin'))->toBeFalse();
});

test('admin gate denies customer users', function () {
    $customer = User::factory()->create(['role' => 'customer']);

    $this->actingAs($customer);

    expect(Gate::allows('admin'))->toBeFalse();
});

test('trainer gate allows trainer users', function () {
    $trainer = User::factory()->create(['role' => 'trainer']);

    $this->actingAs($trainer);

    expect(Gate::allows('trainer'))->toBeTrue();
});

test('trainer gate allows admin users', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin);

    expect(Gate::allows('trainer'))->toBeTrue();
});

test('trainer gate denies customer users', function () {
    $customer = User::factory()->create(['role' => 'customer']);

    $this->actingAs($customer);

    expect(Gate::allows('trainer'))->toBeFalse();
});

test('customer gate allows customer users', function () {
    $customer = User::factory()->create(['role' => 'customer']);

    $this->actingAs($customer);

    expect(Gate::allows('customer'))->toBeTrue();
});

test('customer gate allows trainer users', function () {
    $trainer = User::factory()->create(['role' => 'trainer']);

    $this->actingAs($trainer);

    expect(Gate::allows('customer'))->toBeTrue();
});

test('customer gate allows admin users', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin);

    expect(Gate::allows('customer'))->toBeTrue();
});

test('user policy allows admin to view any users', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin);

    expect(Gate::allows('viewAny', User::class))->toBeTrue();
});

test('user policy allows trainer to view any users', function () {
    $trainer = User::factory()->create(['role' => 'trainer']);

    $this->actingAs($trainer);

    expect(Gate::allows('viewAny', User::class))->toBeTrue();
});

test('user policy denies customer to view any users', function () {
    $customer = User::factory()->create(['role' => 'customer']);

    $this->actingAs($customer);

    expect(Gate::allows('viewAny', User::class))->toBeFalse();
});

test('user policy allows users to view their own profile', function () {
    $user = User::factory()->create(['role' => 'customer']);

    $this->actingAs($user);

    expect(Gate::allows('view', $user))->toBeTrue();
});

test('user policy denies customer to view other users', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $otherUser = User::factory()->create(['role' => 'customer']);

    $this->actingAs($customer);

    expect(Gate::allows('view', $otherUser))->toBeFalse();
});

test('user policy allows admin to create users', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin);

    expect(Gate::allows('create', User::class))->toBeTrue();
});

test('user policy denies non-admin to create users', function () {
    $trainer = User::factory()->create(['role' => 'trainer']);

    $this->actingAs($trainer);

    expect(Gate::allows('create', User::class))->toBeFalse();
});

test('user policy allows users to update their own profile', function () {
    $user = User::factory()->create(['role' => 'customer']);

    $this->actingAs($user);

    expect(Gate::allows('update', $user))->toBeTrue();
});

test('user policy allows admin to update any user', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $otherUser = User::factory()->create(['role' => 'customer']);

    $this->actingAs($admin);

    expect(Gate::allows('update', $otherUser))->toBeTrue();
});

test('user policy denies customer to update other users', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $otherUser = User::factory()->create(['role' => 'customer']);

    $this->actingAs($customer);

    expect(Gate::allows('update', $otherUser))->toBeFalse();
});

test('user policy allows admin to delete users', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $userToDelete = User::factory()->create(['role' => 'customer']);

    $this->actingAs($admin);

    expect(Gate::allows('delete', $userToDelete))->toBeTrue();
});

test('user policy denies admin to delete themselves', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin);

    expect(Gate::allows('delete', $admin))->toBeFalse();
});

test('user policy denies non-admin to delete users', function () {
    $trainer = User::factory()->create(['role' => 'trainer']);
    $customer = User::factory()->create(['role' => 'customer']);

    $this->actingAs($trainer);

    expect(Gate::allows('delete', $customer))->toBeFalse();
});

test('user policy allows admin to restore users', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $deletedUser = User::factory()->create(['role' => 'customer']);
    $deletedUser->delete();

    $this->actingAs($admin);

    expect(Gate::allows('restore', $deletedUser))->toBeTrue();
});

test('user isAdmin method returns true for admin role', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    expect($admin->isAdmin())->toBeTrue();
});

test('user isAdmin method returns false for non-admin roles', function () {
    $trainer = User::factory()->create(['role' => 'trainer']);
    $customer = User::factory()->create(['role' => 'customer']);

    expect($trainer->isAdmin())->toBeFalse();
    expect($customer->isAdmin())->toBeFalse();
});

test('user isTrainer method returns true for trainer role', function () {
    $trainer = User::factory()->create(['role' => 'trainer']);

    expect($trainer->isTrainer())->toBeTrue();
});

test('user isCustomer method returns true for customer role', function () {
    $customer = User::factory()->create(['role' => 'customer']);

    expect($customer->isCustomer())->toBeTrue();
});

test('user full_name attribute combines first and last name', function () {
    $user = User::factory()->create([
        'first_name' => 'Jane',
        'last_name' => 'Doe',
    ]);

    expect($user->full_name)->toBe('Jane Doe');
});

test('user full_name attribute returns null when names are empty', function () {
    $user = User::factory()->create([
        'first_name' => null,
        'last_name' => null,
    ]);

    expect($user->full_name)->toBeNull();
});
