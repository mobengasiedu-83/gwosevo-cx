<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->boolean('restrict_site_access')->default(false);
            $table->boolean('active')->default(true);
            $table->unsignedInteger('auth_version')->default(1);
        });
        Schema::table('roles', function (Blueprint $table) {
            $table->foreignUlid('tenant_id')->nullable()->constrained()->restrictOnDelete();
        });
        Schema::create('user_sites', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('site_id')->constrained()->cascadeOnDelete();
            $table->primary(['user_id', 'site_id']);
        });
        Schema::create('tenant_settings', function (Blueprint $table) {
            $table->foreignUlid('tenant_id')->primary()->constrained()->cascadeOnDelete();
            $table->string('display_name');
            $table->string('timezone')->default('Africa/Accra');
            $table->string('contact_email')->nullable();
            $table->unsignedInteger('default_wrapup_seconds')->default(30);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_settings');
        Schema::dropIfExists('user_sites');
        Schema::table('roles', fn (Blueprint $table) => $table->dropConstrainedForeignId('tenant_id'));
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at', 'restrict_site_access', 'active', 'auth_version']);
        });
    }
};
