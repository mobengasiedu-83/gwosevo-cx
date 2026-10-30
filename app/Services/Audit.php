<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Audit
{
    public static function record(User $user, string $action, string $resource, string $id, array $changes = []): void
    {
        if (! $user->tenant_id) {
            return;
        }
        DB::table('audit_logs')->insert([
            'id' => (string) Str::ulid(), 'tenant_id' => $user->tenant_id, 'user_id' => $user->id,
            'action' => $action, 'resource' => $resource, 'resource_id' => $id,
            'changes' => json_encode($changes, JSON_THROW_ON_ERROR), 'created_at' => now(),
        ]);
    }
}
