<?php

return [
    // Comma-separated administration group IDs, e.g. ai,campaigns.
    'disabled_modules' => array_values(array_filter(array_map('trim', explode(',', (string) env('DISABLED_MODULES', ''))))),
    'catalogue' => json_decode(file_get_contents(resource_path('navigation.json')), true, flags: JSON_THROW_ON_ERROR),
];
