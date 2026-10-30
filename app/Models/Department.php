<?php

namespace App\Models;

class Department extends DomainModel
{
    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function teams()
    {
        return $this->hasMany(Team::class);
    }
}
