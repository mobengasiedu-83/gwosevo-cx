<?php

namespace App\Models;

class Extension extends DomainModel
{
    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function agent()
    {
        return $this->hasOne(Agent::class);
    }
}
