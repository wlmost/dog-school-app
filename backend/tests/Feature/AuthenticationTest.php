<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('user can login with valid credentials', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'password' => Hash::make('Password123!'),
        'role' => 'customer',
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'test@example.com',
        'password' => 'Password123!',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'message',
            'user' => [
                'id',
                'email',
                'role',
                'first_name',
                'last_name',
                'full_name',
                'phone',
                'email_verified_at',
            ],
            'token',
        ]);

    expect($response->json('user.email'))->toBe('test@example.com');
    expect($response->json('user.role'))->toBe('customer');
});

test('user cannot login with invalid credentials', function () {
    User::factory()->create([
        'email' => 'test@example.com',
        'password' => Hash::make('Password123!'),
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'test@example.com',
        'password' => 'WrongPassword',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('user cannot login with soft deleted account', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'password' => Hash::make('Password123!'),
    ]);

    $user->delete();

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'test@example.com',
        'password' => 'Password123!',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('authenticated user can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/auth/logout');

    $response->assertStatus(200)
        ->assertJson([
            'message' => 'Erfolgreich abgemeldet.',
        ]);

    expect($user->tokens()->count())->toBe(0);
});

test('admin can register new user', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/auth/register', [
            'email' => 'newuser@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'trainer',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '123456789',
        ]);

    $response->assertStatus(201)
        ->assertJson([
            'message' => 'Benutzer erfolgreich registriert.',
        ])
        ->assertJsonStructure([
            'user' => [
                'id',
                'email',
                'role',
                'first_name',
                'last_name',
                'phone',
            ],
        ]);

    $this->assertDatabaseHas('users', [
        'email' => 'newuser@example.com',
        'role' => 'trainer',
        'first_name' => 'John',
        'last_name' => 'Doe',
    ]);
});

test('customer cannot register new user', function () {
    $customer = User::factory()->customer()->create();

    $response = $this->actingAs($customer, 'sanctum')
        ->postJson('/api/v1/auth/register', [
            'email' => 'newuser@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'customer',
        ]);

    $response->assertStatus(403);

    $this->assertDatabaseMissing('users', [
        'email' => 'newuser@example.com',
    ]);
});

test('trainer can register a new customer', function () {
    $trainer = User::factory()->trainer()->create();

    $response = $this->actingAs($trainer, 'sanctum')
        ->postJson('/api/v1/auth/register', [
            'email' => 'newcustomer@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'customer',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
        ]);

    $response->assertStatus(201);

    $this->assertDatabaseHas('users', [
        'email' => 'newcustomer@example.com',
        'role' => 'customer',
        'first_name' => 'Jane',
        'last_name' => 'Doe',
    ]);
});

test('trainer cannot register a new admin', function () {
    $trainer = User::factory()->trainer()->create();

    $response = $this->actingAs($trainer, 'sanctum')
        ->postJson('/api/v1/auth/register', [
            'email' => 'newadmin@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'admin',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['role']);

    $this->assertDatabaseMissing('users', [
        'email' => 'newadmin@example.com',
    ]);
});

test('trainer cannot register a new trainer', function () {
    $trainer = User::factory()->trainer()->create();

    $response = $this->actingAs($trainer, 'sanctum')
        ->postJson('/api/v1/auth/register', [
            'email' => 'newtrainer@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'trainer',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['role']);

    $this->assertDatabaseMissing('users', [
        'email' => 'newtrainer@example.com',
    ]);
});

test('unauthenticated request cannot register new user', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'email' => 'newuser@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'role' => 'customer',
    ]);

    $response->assertStatus(401);

    $this->assertDatabaseMissing('users', [
        'email' => 'newuser@example.com',
    ]);
});

test('admin can register a new admin', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/auth/register', [
            'email' => 'newadmin@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'admin',
        ]);

    $response->assertStatus(201);

    $this->assertDatabaseHas('users', [
        'email' => 'newadmin@example.com',
        'role' => 'admin',
    ]);
});

test('admin can register a new customer', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/auth/register', [
            'email' => 'newcustomerbyadmin@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'customer',
        ]);

    $response->assertStatus(201);

    $this->assertDatabaseHas('users', [
        'email' => 'newcustomerbyadmin@example.com',
        'role' => 'customer',
    ]);
});

test('trainer cannot escalate privileges via additional forged fields', function () {
    $trainer = User::factory()->trainer()->create();

    $response = $this->actingAs($trainer, 'sanctum')
        ->postJson('/api/v1/auth/register', [
            'email' => 'forgedadmin@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'admin',
            'is_admin' => true,
            'force_role' => 'admin',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['role']);

    $this->assertDatabaseMissing('users', [
        'email' => 'forgedadmin@example.com',
    ]);
});

test('forged fields on an allowed trainer registration have no effect', function () {
    $trainer = User::factory()->trainer()->create();

    $response = $this->actingAs($trainer, 'sanctum')
        ->postJson('/api/v1/auth/register', [
            'email' => 'notreallyadmin@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'customer',
            'is_admin' => true,
            'force_role' => 'admin',
        ]);

    $response->assertStatus(201);

    $this->assertDatabaseHas('users', [
        'email' => 'notreallyadmin@example.com',
        'role' => 'customer',
    ]);

    $this->assertDatabaseMissing('users', [
        'email' => 'notreallyadmin@example.com',
        'role' => 'admin',
    ]);
});

test('registration validates email uniqueness', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    User::factory()->create(['email' => 'existing@example.com']);

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/auth/register', [
            'email' => 'existing@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'customer',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('registration validates password strength', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/auth/register', [
            'email' => 'newuser@example.com',
            'password' => 'weak',
            'password_confirmation' => 'weak',
            'role' => 'customer',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

test('registration validates role', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/auth/register', [
            'email' => 'newuser@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'invalid_role',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['role']);
});

test('authenticated user can get their profile', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'role' => 'customer',
        'first_name' => 'Jane',
        'last_name' => 'Doe',
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/auth/user');

    $response->assertStatus(200)
        ->assertJson([
            'user' => [
                'id' => $user->id,
                'email' => 'test@example.com',
                'role' => 'customer',
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'full_name' => 'Jane Doe',
            ],
        ]);
});

test('unauthenticated user cannot get profile', function () {
    $response = $this->getJson('/api/v1/auth/user');

    $response->assertStatus(401);
});
