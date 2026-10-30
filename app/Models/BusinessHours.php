<?php

namespace App\Models;

class BusinessHours extends DomainModel
{
    protected $table = 'business_hours';

    protected function casts(): array
    {
        return ['active' => 'boolean', 'schedule' => 'array', 'holidays' => 'array'];
    }
}
