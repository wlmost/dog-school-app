<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSettingsRequest;
use App\Http\Resources\SettingsResource;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

/**
 * Settings Controller
 *
 * Manages application settings.
 */
class SettingsController extends Controller
{
    /**
     * Get all settings.
     */
    public function index(): JsonResponse
    {
        $this->authorize('admin');

        $settings = Setting::all()->groupBy('group');

        return response()->json([
            'data' => SettingsResource::collection($settings->flatten())->collection->groupBy('group'),
        ]);
    }

    /**
     * Update settings.
     */
    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        $this->authorize('admin');

        $validated = $request->validated();

        foreach ($validated as $key => $value) {
            // Handle file uploads
            if ($request->hasFile($key)) {
                $file = $request->file($key);
                $path = $file->store('settings', 'public');
                
                // Delete old file if exists
                $oldValue = Setting::get($key);
                if ($oldValue && Storage::disk('public')->exists($oldValue)) {
                    Storage::disk('public')->delete($oldValue);
                }
                
                $value = $path;
            }

            // Determine type and group based on key
            [$type, $group] = $this->determineTypeAndGroup($key, $value);

            Setting::set($key, $value, $type, null, $group);
        }

        Setting::clearCache();

        $settings = Setting::all()->groupBy('group');

        return response()->json([
            'data' => SettingsResource::collection($settings->flatten())->collection->groupBy('group'),
            'message' => 'Einstellungen erfolgreich aktualisiert.',
        ]);
    }

    /**
     * Determine the type and group for a setting key.
     *
     * @param string $key
     * @param mixed $value
     * @return array{0: string, 1: string}
     */
    private function determineTypeAndGroup(string $key, $value): array
    {
        // Determine type
        $type = match (true) {
            is_bool($value) => 'boolean',
            is_int($value) => 'integer',
            is_array($value) => 'json',
            str_ends_with($key, '_logo') || str_ends_with($key, '_favicon') => 'file',
            default => 'string',
        };

        // Determine group
        $group = match (true) {
            str_starts_with($key, 'company_') => 'company',
            str_starts_with($key, 'email_') => 'email',
            str_starts_with($key, 'smtp_') => 'email',
            default => 'general',
        };

        return [$type, $group];
    }
}
