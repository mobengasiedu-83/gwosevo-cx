<?php

namespace App\Models;

class Queue extends DomainModel
{
    public function members()
    {
        return $this->belongsToMany(Agent::class, 'queue_members')->withPivot('priority')->withTimestamps();
    }

    public function skills()
    {
        return $this->belongsToMany(Skill::class, 'queue_skills')->withPivot('minimum_proficiency')->withTimestamps();
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function routingProfile()
    {
        return $this->belongsTo(RoutingProfile::class);
    }
}
