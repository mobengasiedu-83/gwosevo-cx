<?php

namespace App\Services;

use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class Access
{
    public static function scope(Builder $query, User $user): Builder
    {
        abort_unless($user->tenant_id && $user->active, 403, 'An active tenant account is required.');
        $query->where('tenant_id', $user->tenant_id);
        if ($user->restrict_site_access && ! $user->hasRole('Super Admin')) {
            $model = $query->getModel();
            if ($model instanceof Site) {
                $query->whereIn('id', $user->sites()->select('sites.id'));
            } elseif (in_array($model->getTable(), ['departments', 'teams', 'agents', 'extensions', 'queues'])) {
                $query->whereIn('site_id', $user->sites()->select('sites.id'));
            }
        }

        return $query;
    }

    public static function site(User $user, string $id): bool
    {
        return static::scope(Site::query(), $user)->whereKey($id)->exists();
    }
}
