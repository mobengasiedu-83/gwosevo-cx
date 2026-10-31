<?php

namespace App\Services;

use App\Models\User;

class Navigation
{
    public static function portals(User $user): array
    {
        return array_values(array_filter(['admin', 'supervisor', 'agent'], fn ($portal) => self::allowed($user, $portal)));
    }

    public static function allowed(User $user, string $portal): bool
    {
        return $user->active && $user->tenant_id && ($user->can('portal.'.$portal) || ($portal === 'admin' && $user->can('contact-center.view')));
    }

    public static function resourceAllowed(User $user, string $resource): bool
    {
        return match ($resource) {
            'accounts' => $user->can('users.view') && ! $user->restrict_site_access,
            'roles' => $user->can('roles.view') && ! $user->restrict_site_access,
            'settings' => $user->can('settings.view'),
            'audits' => $user->can('audit-logs.view') && ! $user->restrict_site_access,
            '@security' => true,
            '@horizon' => $user->hasRole('Super Admin'),
            default => $user->can('contact-center.view') || $user->can($resource.'.view'),
        };
    }

    public static function resourceEnabled(string $resource): bool
    {
        $group = match ($resource) {
            'extensions' => 'extensions',
            'agents', 'queues', 'skills', 'routing-profiles' => 'contact-center',
            'accounts', 'roles', 'audits' => 'security',
            '@horizon' => 'developer',
            '@security' => null,
            default => 'administration',
        };

        return ! $group || ! in_array($group, config('navigation.disabled_modules'), true);
    }

    public static function catalogue(User $user, string $portal): array
    {
        abort_unless(self::allowed($user, $portal), 403);
        $groups = [];
        foreach (config('navigation.catalogue.'.$portal, []) as $group) {
            if (in_array($group['id'], config('navigation.disabled_modules'), true)) {
                continue;
            }
            $items = [];
            foreach ($group['items'] as $item) {
                if (isset($item['resource'])) {
                    if (! self::resourceEnabled($item['resource']) || ! self::resourceAllowed($user, $item['resource'])) {
                        continue;
                    }
                } elseif ($portal === 'admin' && ! $user->can('contact-center.manage')) {
                    // Do not expose future administration capabilities to read-only roles.
                    continue;
                }
                $item['status'] = isset($item['resource']) ? 'available' : 'planned';
                $item['url'] = match ($item['resource'] ?? null) {
                    '@security' => url('/security'), '@horizon' => url('/horizon'),
                    null => url('/portal/'.$portal.'/'.$group['id'].'/'.$item['id']),
                    default => url('/admin').'?module='.urlencode($item['resource']),
                };
                $items[] = $item;
            }
            if ($items) {
                $group['items'] = $items;
                $groups[] = $group;
            }
        }

        return $groups;
    }

    /**
     * The first portal the account may enter, or null when no workspace is
     * assigned. Kept separate from home() so shared page props can stay null
     * instead of aborting on pages that do not require a workspace.
     */
    public static function homeFor(User $user): ?string
    {
        foreach (['admin', 'supervisor', 'agent'] as $portal) {
            if (self::allowed($user, $portal)) {
                return '/'.$portal;
            }
        }

        return null;
    }

    public static function home(User $user): string
    {
        return self::homeFor($user) ?? abort(403, 'No workspace is assigned to this account.');
    }
}
