<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * AnamnesisTemplate Model
 *
 * Represents a template for anamnesis forms that trainers can create and customize.
 *
 * @property int $id
 * @property int $trainer_id
 * @property string $name
 * @property string|null $description
 * @property bool $is_default
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read User $trainer
 * @property-read \Illuminate\Database\Eloquent\Collection<int, AnamnesisQuestion> $questions
 * @property-read \Illuminate\Database\Eloquent\Collection<int, AnamnesisResponse> $responses
 */
class AnamnesisTemplate extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'trainer_id',
        'name',
        'description',
        'is_default',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the trainer who created this template.
     */
    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }

    /**
     * Get all questions in this template.
     */
    public function questions(): HasMany
    {
        return $this->hasMany(AnamnesisQuestion::class, 'template_id')->orderBy('order');
    }

    /**
     * Get all responses using this template.
     */
    public function responses(): HasMany
    {
        return $this->hasMany(AnamnesisResponse::class, 'template_id');
    }

    /**
     * Scope a query to only include default templates.
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }
}
