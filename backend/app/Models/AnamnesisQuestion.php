<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * AnamnesisQuestion Model
 *
 * Represents a single question in an anamnesis template.
 *
 * @property int $id
 * @property int $template_id
 * @property string $question_text
 * @property string $question_type
 * @property array|null $options
 * @property bool $is_required
 * @property int $order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read AnamnesisTemplate $template
 * @property-read \Illuminate\Database\Eloquent\Collection<int, AnamnesisAnswer> $answers
 */
class AnamnesisQuestion extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'template_id',
        'question_text',
        'question_type',
        'options',
        'is_required',
        'order',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_required' => 'boolean',
            'order' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the template this question belongs to.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(AnamnesisTemplate::class, 'template_id');
    }

    /**
     * Get all answers to this question.
     */
    public function answers(): HasMany
    {
        return $this->hasMany(AnamnesisAnswer::class, 'question_id');
    }

    /**
     * Check if this is a multiple choice question.
     */
    public function isMultipleChoice(): bool
    {
        return in_array($this->question_type, ['radio', 'checkbox', 'select']);
    }

    /**
     * Check if this is a text question.
     */
    public function isText(): bool
    {
        return in_array($this->question_type, ['text', 'textarea']);
    }
}
