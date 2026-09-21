<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Services\BrevoMailService;
use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Auth\Passwords\CanResetPassword as CanResetPasswordTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail, CanResetPassword
{
    use HasApiTokens,
        HasFactory,
        Notifiable,
        MustVerifyEmailTrait,
        CanResetPasswordTrait;

    protected $fillable = [
        'business_id',
        'name',
        'email',
        'password',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function sendEmailVerificationNotification(): void
    {
        $verificationUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $this->getKey(),
                'hash' => sha1($this->getEmailForVerification()),
            ]
        );

        app(BrevoMailService::class)->sendVerificationEmail(
            $this->email,
            $this->name,
            $verificationUrl
        );
    }

    public function sendPasswordResetNotification($token): void
    {
        $frontendUrl = rtrim(
            env('FRONTEND_URL', 'http://localhost:3000'),
            '/'
        );

        $resetUrl = $frontendUrl
            . '/reset-password?token='
            . urlencode($token)
            . '&email='
            . urlencode($this->email);

        app(BrevoMailService::class)->sendPasswordResetEmail(
            $this->email,
            $this->name,
            $resetUrl
        );
    }
}