<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AnamnesisAnswer Model
 *
 * Represents a single answer to an anamnesis question.
 *
 * @property int $id
 * @property int $response_id
 * @property int $question_id
 * @property string|null $answer_value
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read AnamnesisResponse $response
 * @property-read AnamnesisQuestion $question
 */
class AnamnesisAnswer extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'response_id',
        'question_id',
        'answer_value',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the response this answer belongs to.
     */
    public function response(): BelongsTo
    {
        return $this->belongsTo(AnamnesisResponse::class, 'response_id');
    }

    /**
     * Get the question this answer is for.
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(AnamnesisQuestion::class, 'question_id');
    }

    /**
     * Check if the answer has a value.
     */
    public function hasValue(): bool
    {
        return $this->answer_value !== null && $this->answer_value !== '';
    }
}
