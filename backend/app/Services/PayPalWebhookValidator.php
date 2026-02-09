<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * PayPal Webhook Validator
 *
 * Validates PayPal webhook signatures to ensure authenticity.
 */
class PayPalWebhookValidator
{
    /**
     * Validate PayPal webhook signature
     *
     * @param Request $request
     * @return bool
     */
    public function validate(Request $request): bool
    {
        // Skip validation in local environment for easier testing
        if (app()->environment('local')) {
            Log::info('PayPal webhook validation skipped in local environment');
            return true;
        }

        $webhookId = config('paypal.webhook_id');
        
        // If no webhook ID is configured, log warning and allow (for initial setup)
        if (empty($webhookId)) {
            Log::warning('PayPal webhook ID not configured - validation skipped');
            return true;
        }

        // Get required headers
        $transmissionId = $request->header('PAYPAL-TRANSMISSION-ID');
        $transmissionTime = $request->header('PAYPAL-TRANSMISSION-TIME');
        $transmissionSig = $request->header('PAYPAL-TRANSMISSION-SIG');
        $certUrl = $request->header('PAYPAL-CERT-URL');
        $authAlgo = $request->header('PAYPAL-AUTH-ALGO');

        // Validate required headers are present
        if (!$transmissionId || !$transmissionTime || !$transmissionSig || !$certUrl || !$authAlgo) {
            Log::warning('PayPal webhook missing required headers', [
                'transmission_id' => $transmissionId,
                'transmission_time' => $transmissionTime,
            ]);
            return false;
        }

        // Verify certificate URL is from PayPal
        if (!str_starts_with($certUrl, 'https://api.paypal.com/') && 
            !str_starts_with($certUrl, 'https://api.sandbox.paypal.com/')) {
            Log::error('PayPal webhook certificate URL is not from PayPal', [
                'cert_url' => $certUrl,
            ]);
            return false;
        }

        try {
            // Build the expected signature string
            $expectedSig = $this->buildSignatureString(
                $transmissionId,
                $transmissionTime,
                $webhookId,
                $request->getContent()
            );

            // Download PayPal certificate
            $cert = file_get_contents($certUrl);
            if (!$cert) {
                Log::error('Failed to download PayPal certificate', ['cert_url' => $certUrl]);
                return false;
            }

            // Verify signature
            $publicKey = openssl_pkey_get_public($cert);
            if (!$publicKey) {
                Log::error('Failed to extract public key from certificate');
                return false;
            }

            $result = openssl_verify(
                $expectedSig,
                base64_decode($transmissionSig),
                $publicKey,
                OPENSSL_ALGO_SHA256
            );

            if ($result === 1) {
                Log::info('PayPal webhook signature validated successfully');
                return true;
            } else {
                Log::error('PayPal webhook signature validation failed', [
                    'result' => $result,
                    'transmission_id' => $transmissionId,
                ]);
                return false;
            }

        } catch (\Exception $e) {
            Log::error('PayPal webhook validation error', [
                'error' => $e->getMessage(),
                'transmission_id' => $transmissionId,
            ]);
            return false;
        }
    }

    /**
     * Build the signature string for verification
     *
     * @param string $transmissionId
     * @param string $transmissionTime
     * @param string $webhookId
     * @param string $body
     * @return string
     */
    private function buildSignatureString(
        string $transmissionId,
        string $transmissionTime,
        string $webhookId,
        string $body
    ): string {
        $crc = crc32($body);
        return $transmissionId . '|' . $transmissionTime . '|' . $webhookId . '|' . $crc;
    }
}
