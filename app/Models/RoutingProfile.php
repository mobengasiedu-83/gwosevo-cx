<?php

namespace App\Models;

class RoutingProfile extends DomainModel
{
    public function businessHours()
    {
        return $this->belongsTo(BusinessHours::class);
    }

    public function queues()
    {
        return $this->hasMany(Queue::class);
    }
}
