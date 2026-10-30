<?php

namespace App\Models;

class Tenant extends DomainModel
{
    public function sites()
    {
        return $this->hasMany(Site::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
