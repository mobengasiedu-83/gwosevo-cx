<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\BusinessHours;
use App\Models\Department;
use App\Models\Extension;
use App\Models\Queue;
use App\Models\RoutingProfile;
use App\Models\Site;
use App\Models\Skill;
use App\Models\Team;
use App\Models\User;

/**
 * Cross-resource lookup for the portal directory. Only implemented resources
 * whose menu module is enabled and whose view permission is held by the
 * signed-in account are searched; Access::scope applies tenant and site
 * isolation. Results are capped so the directory stays a quick jump list.
 */
class UnifiedSearch
{
    public const LIMIT_PER_SOURCE = 5;

    public const MAX_RESULTS = 20;

    /** @var array<string, array{model: class-string, label: string, columns: list<string>}> */
    public const SOURCES = [
        'sites' => ['model' => Site::class, 'label' => 'Site', 'columns' => ['name']],
        'departments' => ['model' => Department::class, 'label' => 'Department', 'columns' => ['name']],
        'teams' => ['model' => Team::class, 'label' => 'Team', 'columns' => ['name']],
        'skills' => ['model' => Skill::class, 'label' => 'Skill', 'columns' => ['name']],
        'extensions' => ['model' => Extension::class, 'label' => 'Extension', 'columns' => ['name', 'number']],
        'business-hours' => ['model' => BusinessHours::class, 'label' => 'Business hours', 'columns' => ['name']],
        'routing-profiles' => ['model' => RoutingProfile::class, 'label' => 'Routing profile', 'columns' => ['name']],
        'agents' => ['model' => Agent::class, 'label' => 'Agent', 'columns' => ['name']],
        'queues' => ['model' => Queue::class, 'label' => 'Queue', 'columns' => ['name']],
    ];

    public static function records(User $user, string $term): array
    {
        $records = [];
        $like = '%'.$term.'%';

        foreach (self::SOURCES as $resource => $source) {
            if (! Navigation::resourceEnabled($resource) || ! Navigation::resourceAllowed($user, $resource)) {
                continue;
            }

            $query = Access::scope($source['model']::query(), $user)
                ->where(function ($query) use ($source, $like) {
                    foreach ($source['columns'] as $column) {
                        $query->orWhere($column, 'like', $like);
                    }
                })
                ->orderBy('name')
                ->limit(self::LIMIT_PER_SOURCE);

            if ($resource === 'agents') {
                $query->with('user:id,name,email');
            }

            foreach ($query->get() as $record) {
                $records[] = [
                    'type' => $resource,
                    'label' => $source['label'],
                    'id' => $record->id,
                    'title' => $record->name,
                    'subtitle' => match ($resource) {
                        'extensions' => $record->number,
                        'agents' => $record->user?->email,
                        'sites' => $record->timezone,
                        default => null,
                    },
                    'url' => url('/admin').'?module='.urlencode($resource),
                ];
            }
        }

        if (Navigation::resourceEnabled('accounts') && Navigation::resourceAllowed($user, 'accounts')) {
            $accounts = User::query()
                ->where('tenant_id', $user->tenant_id)
                ->where(fn ($query) => $query
                    ->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like))
                ->orderBy('name')
                ->limit(self::LIMIT_PER_SOURCE)
                ->get(['id', 'name', 'email']);
            foreach ($accounts as $account) {
                $records[] = [
                    'type' => 'accounts',
                    'label' => 'User',
                    'id' => $account->id,
                    'title' => $account->name,
                    'subtitle' => $account->email,
                    'url' => url('/admin').'?module=accounts',
                ];
            }
        }

        usort($records, fn ($a, $b) => strcasecmp($a['title'], $b['title']));

        return array_slice($records, 0, self::MAX_RESULTS);
    }
}
