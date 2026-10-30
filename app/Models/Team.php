<?php

namespace App\Models;

class Team extends DomainModel
{
    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function agents()
    {
        return $this->hasMany(Agent::class);
    }
}
