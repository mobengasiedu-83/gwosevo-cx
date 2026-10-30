<?php

namespace App\Models;

class Agent extends DomainModel
{
    public function skills()
    {
        return $this->belongsToMany(Skill::class, 'agent_skills')->withPivot('proficiency')->withTimestamps();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function queues()
    {
        return $this->belongsToMany(Queue::class, 'queue_members')->withPivot('priority')->withTimestamps();
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function extension()
    {
        return $this->belongsTo(Extension::class);
    }
}
