<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class BrevoMailService
{
    public function sendVerificationEmail(
        string $recipientEmail,
        string $recipientName,
        string $verificationUrl
    ): void {
        $apiKey = config('services.brevo.api_key');

        if (! $apiKey) {
            throw new RuntimeException('Brevo API key is not configured.');
        }

        $response = Http::timeout(15)
            ->acceptJson()
            ->withHeaders([
                'api-key' => $apiKey,
            ])
            ->post('https://api.brevo.com/v3/smtp/email', [
                'sender' => [
                    'name' => config(
                        'services.brevo.sender_name',
                        'Inventory Dashboard'
                    ),
                    'email' => config('services.brevo.sender_email'),
                ],
                'to' => [
                    [
                        'email' => $recipientEmail,
                        'name' => $recipientName,
                    ],
                ],
                'subject' => 'Verify your Inventory Dashboard account',
                'htmlContent' => $this->verificationHtml(
                    $recipientName,
                    $verificationUrl
                ),
                'textContent' => $this->verificationText(
                    $recipientName,
                    $verificationUrl
                ),
            ]);

        if ($response->failed()) {
            throw new RuntimeException(
                'Brevo API request failed: ' . $response->body()
            );
        }
    }

    private function verificationHtml(
        string $recipientName,
        string $verificationUrl
    ): string {
        $safeName = e($recipientName);
        $safeUrl = e($verificationUrl);

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify your email</title>
</head>
<body style="margin:0; padding:0; background:#f8fafc; font-family:Arial, sans-serif;">
    <div style="max-width:600px; margin:40px auto; padding:32px; background:#ffffff; border:1px solid #e2e8f0; border-radius:12px;">
        <h1 style="margin:0 0 20px; color:#0f172a;">
            Verify your email
        </h1>

        <p style="color:#475569; line-height:1.6;">
            Hello {$safeName},
        </p>

        <p style="color:#475569; line-height:1.6;">
            Thank you for creating your Inventory Dashboard account.
            Please verify your email address by clicking the button below.
        </p>

        <p style="margin:30px 0;">
            <a
                href="{$safeUrl}"
                style="display:inline-block; padding:12px 20px; background:#2563eb; color:#ffffff; text-decoration:none; border-radius:8px; font-weight:600;"
            >
                Verify Email Address
            </a>
        </p>

        <p style="color:#64748b; font-size:14px; line-height:1.6;">
            If the button does not work, copy and paste this URL into your browser:
        </p>

        <p style="word-break:break-all; color:#2563eb; font-size:14px;">
            {$safeUrl}
        </p>

        <p style="margin-top:30px; color:#64748b; font-size:14px;">
            If you did not create this account, you can safely ignore this email.
        </p>
    </div>
</body>
</html>
HTML;
    }

    private function verificationText(
        string $recipientName,
        string $verificationUrl
    ): string {
        return <<<TEXT
Hello {$recipientName},

Thank you for creating your Inventory Dashboard account.

Please verify your email address by opening this link:

{$verificationUrl}

If you did not create this account, you can safely ignore this email.
TEXT;
    }
}