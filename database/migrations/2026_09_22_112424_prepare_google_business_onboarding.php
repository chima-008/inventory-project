<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oauth_login_codes', function (Blueprint $table) {
            $table->foreignId('user_id')
                ->nullable()
                ->change();

            $table->string('google_email')
                ->nullable()
                ->after('user_id');

            $table->string('google_name')
                ->nullable()
                ->after('google_email');
        });
    }

    public function down(): void
    {
        Schema::table('oauth_login_codes', function (Blueprint $table) {
            $table->dropColumn([
                'google_email',
                'google_name',
            ]);

            $table->foreignId('user_id')
                ->nullable(false)
                ->change();
        });
    }
};