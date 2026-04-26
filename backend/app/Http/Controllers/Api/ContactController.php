<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\ContactFormMail;
use App\Services\MailConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/**
 * ContactController
 *
 * Handles public contact form submissions and forwards them as an email
 * to the company's configured contact address.
 */
class ContactController extends Controller
{
    public function __construct(
        private readonly MailConfigService $mailConfigService,
    ) {
    }

    /**
     * Send a contact form message.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'    => ['required', 'string', 'max:255'],
            'email'   => ['required', 'email', 'max:255'],
            'phone'   => ['nullable', 'string', 'max:50'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $this->mailConfigService->applyFromSettings();

        try {
            Mail::send(new ContactFormMail(
                senderName: $validated['name'],
                senderEmail: $validated['email'],
                contactSubject: $validated['subject'],
                contactMessage: $validated['message'],
                phone: $validated['phone'] ?? null,
            ));
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Die Nachricht konnte nicht gesendet werden. Bitte versuchen Sie es später erneut oder kontaktieren Sie uns telefonisch.',
            ], 503);
        }

        return response()->json([
            'message' => 'Ihre Nachricht wurde erfolgreich gesendet.',
        ]);
    }
}
