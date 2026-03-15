<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/**
 * Settings Controller
 *
 * Manages application settings grouped by category.
 */
class SettingsController extends Controller
{
    /**
     * Get all settings grouped by category.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Setting::class);

        $groups = ['company', 'email', 'general'];
        $data = [];

        foreach ($groups as $group) {
            $settings = Setting::where('group', $group)->get();
            $data[$group] = $settings->mapWithKeys(function ($setting) {
                return [$setting->key => $this->castValue($setting->value, $setting->type)];
            });
        }

        return response()->json([
            'data' => $data,
            'message' => 'Settings retrieved successfully'
        ]);
    }

    /**
     * Update settings.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function update(Request $request): JsonResponse
    {
        $this->authorize('update', Setting::class);

        $validator = Validator::make($request->all(), [
            'company_name' => 'nullable|string|max:255',
            'company_email' => 'nullable|email|max:255',
            'company_phone' => 'nullable|string|max:50',
            'company_street' => 'nullable|string|max:255',
            'company_zip' => 'nullable|string|max:20',
            'company_city' => 'nullable|string|max:255',
            'company_country' => 'nullable|string|max:255',
            'company_website' => 'nullable|url|max:255',
            'company_vat_id' => 'nullable|string|max:50',
            'company_tax_number' => 'nullable|string|max:50',
            'email_from_name' => 'nullable|string|max:255',
            'email_from_address' => 'nullable|email|max:255',
            'email_booking_subject' => 'nullable|string|max:255',
            'email_booking_message' => 'nullable|string',
            'email_invoice_subject' => 'nullable|string|max:255',
            'email_invoice_message' => 'nullable|string',
            'email_welcome_subject' => 'nullable|string|max:255',
            'email_welcome_message' => 'nullable|string',
            'email_reminder_subject' => 'nullable|string|max:255',
            'email_reminder_message' => 'nullable|string',
            'email_logo' => 'nullable|file|mimes:png,jpg,jpeg,svg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();

        // Handle file upload for logo
        if ($request->hasFile('email_logo')) {
            $file = $request->file('email_logo');
            $path = $file->store('email-templates', 'public');
            $validated['email_logo'] = $path;
        }

        // Group settings by prefix
        $settingGroups = [
            'company' => [],
            'email' => [],
            'general' => [],
        ];

        foreach ($validated as $key => $value) {
            if (str_starts_with($key, 'company_')) {
                $settingGroups['company'][$key] = $value;
            } elseif (str_starts_with($key, 'email_')) {
                $settingGroups['email'][$key] = $value;
            } else {
                $settingGroups['general'][$key] = $value;
            }
        }

        // Save settings
        foreach ($settingGroups as $group => $settings) {
            foreach ($settings as $key => $value) {
                $type = $this->getSettingType($value);
                
                Setting::set(
                    $key,
                    $value,
                    $type,
                    $this->getSettingDescription($key),
                    $group
                );
            }
        }

        // Clear cache
        Setting::clearCache();

        return response()->json([
            'message' => 'Settings updated successfully',
            'data' => $this->index($request)->getData()->data
        ]);
    }

    /**
     * Get setting type based on value.
     *
     * @param mixed $value
     * @return string
     */
    private function getSettingType($value): string
    {
        if (is_bool($value)) {
            return 'boolean';
        }
        if (is_int($value)) {
            return 'integer';
        }
        if (is_array($value)) {
            return 'json';
        }
        return 'string';
    }

    /**
     * Get setting description based on key.
     *
     * @param string $key
     * @return string
     */
    private function getSettingDescription(string $key): string
    {
        $descriptions = [
            'company_name' => 'Company name displayed on invoices and emails',
            'company_email' => 'Primary company email address',
            'company_phone' => 'Company phone number',
            'company_street' => 'Company street address',
            'company_zip' => 'Company postal code',
            'company_city' => 'Company city',
            'company_country' => 'Company country',
            'company_website' => 'Company website URL',
            'company_vat_id' => 'VAT identification number',
            'company_tax_number' => 'Tax number',
            'email_from_name' => 'Default sender name for emails',
            'email_from_address' => 'Default sender email address',
            'email_booking_subject' => 'Subject for booking confirmation emails',
            'email_booking_message' => 'Message template for booking confirmations',
            'email_invoice_subject' => 'Subject for invoice emails',
            'email_invoice_message' => 'Message template for invoices',
            'email_welcome_subject' => 'Subject for welcome emails',
            'email_welcome_message' => 'Message template for welcome emails',
            'email_reminder_subject' => 'Subject for payment reminder emails',
            'email_reminder_message' => 'Message template for payment reminders',
            'email_logo' => 'Logo displayed in email headers',
        ];

        return $descriptions[$key] ?? '';
    }

    /**
     * Cast value from string to appropriate type.
     *
     * @param string|null $value
     * @param string $type
     * @return mixed
     */
    private function castValue(?string $value, string $type)
    {
        if ($value === null) {
            return null;
        }

        return match($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $value,
            'json' => json_decode($value, true),
            default => $value,
        };
    }
}
