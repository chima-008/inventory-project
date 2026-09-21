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

    public function sendPasswordResetEmail(
    string $recipientEmail,
    string $recipientName,
    string $resetUrl
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
            'subject' => 'Reset your Inventory Dashboard password',
            'htmlContent' => $this->passwordResetHtml(
                $recipientName,
                $resetUrl
            ),
            'textContent' => $this->passwordResetText(
                $recipientName,
                $resetUrl
            ),
        ]);

    if ($response->failed()) {
        throw new RuntimeException(
            'Brevo API request failed: ' . $response->body()
        );
    }
}

private function passwordResetHtml(
    string $recipientName,
    string $resetUrl
): string {
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset your password</title>
</head>
<body style="margin:0; padding:0; background:#f8fafc; font-family:Arial, Helvetica, sans-serif; color:#0f172a;">
    <div style="max-width:600px; margin:0 auto; padding:40px 20px;">
        <div style="background:#0f172a; padding:24px; border-radius:12px 12px 0 0; text-align:center;">
            <div style="display:inline-block; background:#2563eb; color:#ffffff; width:44px; height:44px; line-height:44px; border-radius:10px; font-weight:bold; font-size:20px;">
                I
            </div>
            <div style="margin-top:12px; color:#ffffff; font-size:20px; font-weight:bold;">
                Inventory Dashboard
            </div>
        </div>

        <div style="background:#ffffff; padding:36px 32px; border:1px solid #e2e8f0; border-top:0; border-radius:0 0 12px 12px;">
            <h1 style="margin:0 0 16px; font-size:24px; color:#0f172a;">
                Reset your password
            </h1>

            <p style="font-size:15px; line-height:1.7; color:#475569;">
                Hello {$recipientName},
            </p>

            <p style="font-size:15px; line-height:1.7; color:#475569;">
                We received a request to reset the password for your Inventory Dashboard account.
            </p>

            <div style="margin:28px 0; text-align:center;">
                <a
                    href="{$resetUrl}"
                    style="display:inline-block; background:#2563eb; color:#ffffff; text-decoration:none; padding:13px 24px; border-radius:8px; font-size:14px; font-weight:bold;"
                >
                    Reset password
                </a>
            </div>

            <p style="font-size:14px; line-height:1.7; color:#64748b;">
                This password reset link will expire after 60 minutes.
            </p>

            <p style="font-size:14px; line-height:1.7; color:#64748b;">
                If you did not request a password reset, you can safely ignore this email.
            </p>

            <p style="margin-top:28px; font-size:13px; line-height:1.6; color:#94a3b8;">
                If the button above does not work, copy and paste this link into your browser:
            </p>

            <p style="word-break:break-all; font-size:12px; line-height:1.6; color:#64748b;">
                {$resetUrl}
            </p>
        </div>
    </div>
</body>
</html>
HTML;
}

private function passwordResetText(
    string $recipientName,
    string $resetUrl
): string {
    return <<<TEXT
Hello {$recipientName},

We received a request to reset the password for your Inventory Dashboard account.

Reset your password using this link:

{$resetUrl}

This password reset link will expire after 60 minutes.

If you did not request a password reset, you can safely ignore this email.

Inventory Dashboard
TEXT;
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