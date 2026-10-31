<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('extensions', function (Blueprint $table) {
            // General settings.
            $table->string('class_of_service', 32)->default('all');
            $table->text('features_password')->nullable();
            $table->string('email_address')->nullable();
            $table->string('internal_cid_name', 60)->nullable();
            $table->string('internal_cid_number', 32)->nullable();
            $table->string('external_cid_name', 60)->nullable();
            $table->string('external_cid_number', 32)->nullable();
            $table->string('emergency_cid_name', 60)->nullable();
            $table->string('emergency_cid_number', 32)->nullable();
            $table->string('account_code', 32)->nullable();
            $table->string('language', 8)->default('en');

            // Endpoint device. Secrets are stored encrypted by the model.
            $table->string('technology', 10)->default('none');
            $table->string('user_device', 50)->nullable();
            $table->text('device_password')->nullable();
            $table->string('device_description')->nullable();
            $table->string('profile', 100)->default('default');
            $table->unsignedTinyInteger('max_contacts')->default(5);
            $table->string('codecs')->nullable();
            $table->string('dtmf_mode', 12)->default('rfc4733');
            $table->string('device_emergency_cid_name', 60)->nullable();
            $table->string('device_emergency_cid_number', 32)->nullable();
            $table->string('dispatchable_location')->nullable();
            $table->string('deny', 100)->nullable();
            $table->string('permit', 100)->nullable();
            $table->boolean('ring_device')->default(true);
            $table->boolean('webrtc_client')->default(false);

            // Voicemail.
            $table->boolean('voicemail_enabled')->default(false);
            $table->string('voicemail_mailbox', 20)->nullable();
            $table->text('voicemail_password')->nullable();
            $table->string('voicemail_email')->nullable();
            $table->json('voicemail_options')->nullable();

            // Recording and advanced call handling.
            $table->string('recording_mode', 12)->default('disabled');
            $table->string('call_group', 50)->nullable();
            $table->string('pickup_group', 50)->nullable();
            $table->string('transport', 8)->default('udp');
            $table->unsignedSmallInteger('max_call_duration')->nullable();
            $table->boolean('call_waiting')->default(true);

            // Follow me and inbound destinations.
            $table->boolean('follow_me_enabled')->default(false);
            $table->json('follow_me')->nullable();
            $table->json('incoming_routes')->nullable();

            // Directory details.
            $table->string('contact_company')->nullable();
            $table->string('contact_position')->nullable();
            $table->string('contact_phone', 32)->nullable();
            $table->string('contact_mobile', 32)->nullable();
            $table->text('contact_notes')->nullable();

            $table->unique(['tenant_id', 'user_device']);
            $table->index(['tenant_id', 'technology']);
        });
    }

    public function down(): void
    {
        Schema::table('extensions', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'user_device']);
            $table->dropIndex(['tenant_id', 'technology']);
            $table->dropColumn([
                'class_of_service', 'features_password', 'email_address',
                'internal_cid_name', 'internal_cid_number', 'external_cid_name', 'external_cid_number',
                'emergency_cid_name', 'emergency_cid_number', 'account_code', 'language',
                'technology', 'user_device', 'device_password', 'device_description', 'profile',
                'max_contacts', 'codecs', 'dtmf_mode', 'device_emergency_cid_name',
                'device_emergency_cid_number', 'dispatchable_location', 'deny', 'permit',
                'ring_device', 'webrtc_client',
                'voicemail_enabled', 'voicemail_mailbox', 'voicemail_password', 'voicemail_email', 'voicemail_options',
                'recording_mode', 'call_group', 'pickup_group', 'transport', 'max_call_duration', 'call_waiting',
                'follow_me_enabled', 'follow_me', 'incoming_routes',
                'contact_company', 'contact_position', 'contact_phone', 'contact_mobile', 'contact_notes',
            ]);
        });
    }
};
