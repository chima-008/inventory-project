<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('business_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('invited_by')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('email');

            $table->string('role', 50)
                ->default('manager');

            $table->string('token_hash', 64)
                ->unique();

            $table->timestamp('expires_at');

            $table->timestamp('accepted_at')
                ->nullable();

            $table->timestamps();

            $table->index([
                'business_id',
                'email',
            ]);

            $table->index([
                'email',
                'expires_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};