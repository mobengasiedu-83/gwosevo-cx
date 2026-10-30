<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->timestamps();
        });
        Schema::table('users', function (Blueprint $table) {
            $table->foreignUlid('tenant_id')->nullable()->constrained()->restrictOnDelete();
        });
        foreach (['sites', 'departments', 'teams', 'skills', 'extensions', 'business_hours', 'routing_profiles', 'agents', 'queues'] as $name) {
            Schema::create($name, function (Blueprint $table) use ($name) {
                $table->ulid('id')->primary();
                $table->foreignUlid('tenant_id')->constrained()->restrictOnDelete();
                $table->string('name');
                $table->boolean('active')->default(true);
                if ($name === 'sites') {
                    $table->string('timezone')->default('UTC');
                }
                if (in_array($name, ['departments', 'teams', 'extensions', 'agents', 'queues'])) {
                    $table->foreignUlid('site_id')->constrained()->restrictOnDelete();
                }
                if ($name === 'teams') {
                    $table->foreignUlid('department_id')->nullable()->constrained()->restrictOnDelete();
                }
                if ($name === 'extensions') {
                    $table->string('number', 20);
                    $table->unique(['tenant_id', 'number']);
                }
                if ($name === 'business_hours') {
                    $table->string('timezone');
                    $table->json('schedule');
                    $table->json('holidays')->nullable();
                }
                if ($name === 'routing_profiles') {
                    $table->foreignUlid('business_hours_id')->constrained('business_hours')->restrictOnDelete();
                    $table->string('strategy');
                    $table->unsignedInteger('ring_timeout')->default(30);
                    $table->unsignedInteger('max_wait')->default(300);
                    $table->string('overflow_action')->default('hangup');
                }
                if ($name === 'agents') {
                    $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
                    $table->foreignUlid('team_id')->nullable()->constrained()->restrictOnDelete();
                    $table->foreignUlid('extension_id')->nullable()->unique()->constrained()->restrictOnDelete();
                }
                if ($name === 'queues') {
                    $table->foreignUlid('routing_profile_id')->constrained()->restrictOnDelete();
                    $table->unsignedInteger('priority')->default(0);
                }
                $table->timestamps();
                $table->unique(['tenant_id', 'name']);
                $table->index(['tenant_id', 'active']);
            });
        }
        Schema::create('agent_skills', function (Blueprint $table) {
            $table->foreignUlid('agent_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('skill_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('proficiency')->default(1);
            $table->primary(['agent_id', 'skill_id']);
            $table->timestamps();
        });
        Schema::create('queue_members', function (Blueprint $table) {
            $table->foreignUlid('queue_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('agent_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('priority')->default(0);
            $table->primary(['queue_id', 'agent_id']);
            $table->timestamps();
        });
        Schema::create('queue_skills', function (Blueprint $table) {
            $table->foreignUlid('queue_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('skill_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('minimum_proficiency')->default(1);
            $table->primary(['queue_id', 'skill_id']);
            $table->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('action');
            $table->string('resource');
            $table->string('resource_id');
            $table->json('changes');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'queue_skills', 'queue_members', 'agent_skills', 'queues', 'agents', 'routing_profiles', 'business_hours', 'extensions', 'skills', 'teams', 'departments', 'sites'] as $name) {
            Schema::dropIfExists($name);
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
        });
        Schema::dropIfExists('tenants');
    }
};
