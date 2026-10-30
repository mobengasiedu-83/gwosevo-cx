<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

abstract class DomainModel extends Model
{
    use HasUlids;

    protected $guarded = ['id', 'tenant_id'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
