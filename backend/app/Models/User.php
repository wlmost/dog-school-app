<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * User Model
 *
 * Represents a user in the system with role-based access control.
 * Roles: admin, trainer, customer
 *
 * @property int $id
 * @property string $email
 * @property string $role
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $phone
 * @property string $password
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'role',
        'first_name',
        'last_name',
        'phone',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Check if user is an admin.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Check if user is a trainer.
     */
    public function isTrainer(): bool
    {
        return $this->role === 'trainer';
    }

    /**
     * Check if user is a customer.
     */
    public function isCustomer(): bool
    {
        return $this->role === 'customer';
    }

    /**
     * Get the user's full name.
     */
    public function getFullNameAttribute(): ?string
    {
        if (!$this->first_name && !$this->last_name) {
            return null;
        }

        return trim("{$this->first_name} {$this->last_name}");
    }

    /**
     * Get the customer profile for this user (if customer role).
     */
    public function customer(): HasOne
    {
        return $this->hasOne(Customer::class);
    }

    /**
     * Get all courses taught by this trainer (if trainer role).
     */
    public function courses(): HasMany
    {
        return $this->hasMany(Course::class, 'trainer_id');
    }

    /**
     * Get all training sessions led by this trainer (if trainer role).
     */
    public function trainingSessions(): HasMany
    {
        return $this->hasMany(TrainingSession::class, 'trainer_id');
    }

    /**
     * Get all anamnesis templates created by this trainer (if trainer role).
     */
    public function anamnesisTemplates(): HasMany
    {
        return $this->hasMany(AnamnesisTemplate::class, 'trainer_id');
    }

    /**
     * Get all training logs created by this trainer (if trainer role).
     */
    public function trainingLogs(): HasMany
    {
        return $this->hasMany(TrainingLog::class, 'trainer_id');
    }
}
