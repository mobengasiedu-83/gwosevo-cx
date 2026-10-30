<?php

namespace App\Models;

class Site extends DomainModel
{
    public function departments()
    {
        return $this->hasMany(Department::class);
    }

    public function teams()
    {
        return $this->hasMany(Team::class);
    }

    public function agents()
    {
        return $this->hasMany(Agent::class);
    }

    public function extensions()
    {
        return $this->hasMany(Extension::class);
    }

    public function queues()
    {
        return $this->hasMany(Queue::class);
    }
}
