<?php

namespace App\Models;

class Extension extends DomainModel
{
    public const TECHNOLOGIES = [
        'pjsip' => 'PJSIP',
        'iax2' => 'IAX2',
        'virtual' => 'Virtual',
        'none' => 'None',
    ];

    /**
     * Standard access levels until tenant-defined classes of service are
     * introduced. The telephony gateway enforces these during provisioning.
     */
    public const CLASSES_OF_SERVICE = [
        'all' => 'All Permissions',
        'internal' => 'Internal Only',
        'local' => 'Local Only',
        'national' => 'National Only',
        'international' => 'International Only',
        'emergency' => 'Emergency Only',
    ];

    public const LANGUAGES = [
        'en' => 'English (en)',
        'ar' => 'Arabic (ar)',
        'zh' => 'Chinese (zh)',
        'nl' => 'Dutch (nl)',
        'fr' => 'French (fr)',
        'de' => 'German (de)',
        'hi' => 'Hindi (hi)',
        'it' => 'Italian (it)',
        'pt' => 'Portuguese (pt)',
        'ru' => 'Russian (ru)',
        'es' => 'Spanish (es)',
        'tr' => 'Turkish (tr)',
    ];

    public const DTMF_MODES = [
        'rfc4733' => 'RFC 4733',
        'inband' => 'Inband',
        'info' => 'SIP INFO',
        'auto' => 'Auto',
    ];

    public const TRANSPORTS = [
        'udp' => 'UDP',
        'tcp' => 'TCP',
        'tls' => 'TLS',
        'ws' => 'WebSocket',
        'wss' => 'Secure WebSocket',
    ];

    public const RECORDING_MODES = [
        'disabled' => 'Disabled',
        'on_demand' => 'On demand',
        'always' => 'Always',
    ];

    public const VOICEMAIL_OPTIONS = [
        'attach' => 'Attach the recording to the email',
        'saycid' => 'Announce the caller ID',
        'envelope' => 'Include the message envelope',
        'delete' => 'Delete the message after emailing',
    ];

    protected $hidden = ['features_password', 'device_password', 'voicemail_password'];

    protected $appends = ['has_features_password', 'has_device_password', 'has_voicemail_password'];

    protected function casts(): array
    {
        return parent::casts() + [
            'features_password' => 'encrypted',
            'device_password' => 'encrypted',
            'voicemail_password' => 'encrypted',
            'voicemail_options' => 'array',
            'follow_me' => 'array',
            'incoming_routes' => 'array',
            'ring_device' => 'boolean',
            'webrtc_client' => 'boolean',
            'voicemail_enabled' => 'boolean',
            'follow_me_enabled' => 'boolean',
            'call_waiting' => 'boolean',
            'max_contacts' => 'integer',
            'max_call_duration' => 'integer',
        ];
    }

    public function getHasFeaturesPasswordAttribute(): bool
    {
        return filled($this->getRawOriginal('features_password'));
    }

    public function getHasDevicePasswordAttribute(): bool
    {
        return filled($this->getRawOriginal('device_password'));
    }

    public function getHasVoicemailPasswordAttribute(): bool
    {
        return filled($this->getRawOriginal('voicemail_password'));
    }

    public static function options(): array
    {
        return [
            'technologies' => self::TECHNOLOGIES,
            'classes' => self::CLASSES_OF_SERVICE,
            'languages' => self::LANGUAGES,
            'dtmfModes' => self::DTMF_MODES,
            'transports' => self::TRANSPORTS,
            'recordingModes' => self::RECORDING_MODES,
            'voicemailOptions' => self::VOICEMAIL_OPTIONS,
        ];
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function agent()
    {
        return $this->hasOne(Agent::class);
    }
}
