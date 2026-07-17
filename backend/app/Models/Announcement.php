<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Announcement Model
 *
 * @property int $id
 * @property string $title
 * @property string $body
 * @property string|null $image_path
 * @property int $display_days
 * @property Carbon $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Announcement extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'body',
        'image_path',
        'display_days',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'display_days' => 'integer',
            'expires_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Recompute expires_at from the original publish date whenever
     * display_days changes (create, or admin extends/shortens the
     * display duration on an existing announcement). Uses the existing
     * created_at as the base on update so editing an announcement does
     * not silently reset its display window to "now".
     */
    protected static function booted(): void
    {
        static::saving(function (Announcement $announcement): void {
            if (! $announcement->exists || $announcement->isDirty('display_days')) {
                $base = ($announcement->exists && $announcement->created_at)
                    ? $announcement->created_at
                    : now();

                $announcement->expires_at = $base->copy()->addDays((int) $announcement->display_days);
            }
        });
    }

    /**
     * Whether the announcement is currently within its display window.
     */
    public function isActive(): bool
    {
        return $this->expires_at->isFuture();
    }

    /**
     * Scope a query to only include currently active announcements.
     */
    public function scopeActive($query)
    {
        return $query->where('expires_at', '>', now());
    }
}
