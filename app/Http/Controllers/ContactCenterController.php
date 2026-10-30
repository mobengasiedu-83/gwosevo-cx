<?php

namespace App\Http\Controllers;

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
use App\Services\Access;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ContactCenterController extends Controller
{
    public const RESOURCES = [
        'sites' => Site::class, 'departments' => Department::class, 'teams' => Team::class,
        'skills' => Skill::class, 'extensions' => Extension::class, 'business-hours' => BusinessHours::class,
        'routing-profiles' => RoutingProfile::class, 'agents' => Agent::class, 'queues' => Queue::class,
    ];

    private function tenant(Request $request): string
    {
        abort_unless($request->user()->tenant_id, 403, 'A tenant must be assigned to your account.');

        return $request->user()->tenant_id;
    }

    private function query(Request $request, string $resource)
    {
        abort_unless(isset(self::RESOURCES[$resource]), 404);

        return Access::scope(self::RESOURCES[$resource]::query(), $request->user());
    }

    public function index(Request $request, string $resource)
    {
        $query = $this->query($request, $resource);
        if ($resource === 'agents') {
            $query->with('skills');
        } elseif ($resource === 'queues') {
            $query->with(['members', 'skills']);
        }

        return $query->orderBy('name')->paginate(50);
    }

    public function show(Request $request, string $resource, string $id)
    {
        $record = $this->query($request, $resource)->findOrFail($id);
        if ($resource === 'agents') {
            $record->load('skills');
        } elseif ($resource === 'queues') {
            $record->load(['members', 'skills']);
        }

        return $record;
    }

    private function validated(Request $request, string $resource, $record = null): array
    {
        $tenant = $this->tenant($request);
        $table = (new (self::RESOURCES[$resource]))->getTable();
        $exists = fn (string $table) => Rule::exists($table, 'id')->where('tenant_id', $tenant);
        $unique = fn (string $column) => Rule::unique($table, $column)->where('tenant_id', $tenant)->ignore($record?->id);
        $rules = ['name' => ['required', 'string', 'max:255', $unique('name')], 'active' => ['sometimes', 'boolean']];
        if (in_array($resource, ['departments', 'teams', 'extensions', 'agents', 'queues'])) {
            $rules['site_id'] = ['required', $exists('sites')];
        }
        $rules += match ($resource) {
            'sites' => ['timezone' => ['required', 'timezone']],
            'teams' => ['department_id' => ['nullable', $exists('departments')]],
            'extensions' => ['number' => ['required', 'string', 'regex:/^[0-9]{2,20}$/', $unique('number')]],
            'business-hours' => [
                'timezone' => ['required', 'timezone'], 'schedule' => ['required', 'array', 'min:1', 'max:7'],
                'schedule.*' => ['required', 'array:day,opens,closes'],
                'schedule.*.day' => ['required', 'integer', 'between:1,7', 'distinct'],
                'schedule.*.opens' => ['required', 'date_format:H:i'],
                'schedule.*.closes' => ['required', 'date_format:H:i'],
                'holidays' => ['nullable', 'array'], 'holidays.*' => ['required', 'date_format:Y-m-d', 'distinct'],
            ],
            'routing-profiles' => [
                'business_hours_id' => ['required', $exists('business_hours')],
                'strategy' => ['required', Rule::in(['ring_all', 'round_robin', 'least_recent', 'fewest_calls', 'skill_based'])],
                'ring_timeout' => ['required', 'integer', 'between:5,120'],
                'max_wait' => ['required', 'integer', 'between:0,86400'],
                'overflow_action' => ['required', Rule::in(['hangup', 'voicemail', 'callback'])],
            ],
            'agents' => [
                'user_id' => ['required', $exists('users'), Rule::unique('agents', 'user_id')->ignore($record?->id)],
                'team_id' => ['nullable', $exists('teams')],
                'extension_id' => ['nullable', $exists('extensions'), Rule::unique('agents', 'extension_id')->ignore($record?->id)],
                'skills' => ['sometimes', 'array'], 'skills.*' => ['array:id,proficiency'],
                'skills.*.id' => ['required', 'distinct', $exists('skills')],
                'skills.*.proficiency' => ['required', 'integer', 'between:1,5'],
            ],
            'queues' => [
                'routing_profile_id' => ['required', $exists('routing_profiles')],
                'priority' => ['required', 'integer', 'between:0,1000'],
                'members' => ['sometimes', 'array'], 'members.*' => ['array:id,priority'],
                'members.*.id' => ['required', 'distinct', $exists('agents')],
                'members.*.priority' => ['required', 'integer', 'between:0,1000'],
                'skills' => ['sometimes', 'array'], 'skills.*' => ['array:id,minimum_proficiency'],
                'skills.*.id' => ['required', 'distinct', $exists('skills')],
                'skills.*.minimum_proficiency' => ['required', 'integer', 'between:1,5'],
            ],
            default => [],
        };
        $data = $request->validate($rules);
        if (isset($data['site_id']) && ! Access::site($request->user(), $data['site_id'])) {
            throw ValidationException::withMessages(['site_id' => 'Choose an accessible site.']);
        }
        if ($resource === 'sites' && $request->user()->restrict_site_access && ! $request->user()->hasRole('Super Admin')) {
            abort(403, 'Site administration requires tenant-wide access.');
        }
        if ($resource === 'agents' && $request->user()->restrict_site_access && ! $request->user()->hasRole('Super Admin')) {
            $allowed = User::whereKey($data['user_id'])->where(function ($q) use ($request, $data) {
                $q->whereKey($request->user()->id)
                    ->orWhereHas('sites', fn ($site) => $site->where('sites.id', $data['site_id']))
                    ->orWhereHas('agent', fn ($agent) => $agent->where('site_id', $data['site_id']));
            })->exists();
            if (! $allowed) {
                throw ValidationException::withMessages(['user_id' => 'Choose an account assigned to the selected site.']);
            }
        }
        foreach (['department_id' => Department::class, 'team_id' => Team::class, 'extension_id' => Extension::class] as $field => $model) {
            if (! empty($data[$field]) && $model::findOrFail($data[$field])->site_id !== $data['site_id']) {
                throw ValidationException::withMessages([$field => 'Choose a record from the selected site.']);
            }
        }
        foreach ($data['members'] ?? [] as $member) {
            if (Agent::findOrFail($member['id'])->site_id !== $data['site_id']) {
                throw ValidationException::withMessages(['members' => 'Queue members must belong to the queue site.']);
            }
        }
        foreach ($data['schedule'] ?? [] as $day) {
            if ($day['closes'] <= $day['opens']) {
                throw ValidationException::withMessages(['schedule' => 'Closing time must be after opening time. Overnight hours must be split across days.']);
            }
        }
        // Moving a parent would invalidate existing child associations.
        if ($record && isset($data['site_id']) && $record->site_id !== $data['site_id']) {
            $references = match ($resource) {
                'departments' => [[Team::class, 'department_id']],
                'teams' => [[Agent::class, 'team_id']],
                'extensions' => [[Agent::class, 'extension_id']],
                'agents' => [],
                default => [],
            };
            foreach ($references as [$model, $foreignKey]) {
                if ($model::where($foreignKey, $record->id)->exists()) {
                    throw ValidationException::withMessages(['site_id' => 'Remove dependent assignments before changing site.']);
                }
            }
            if ($resource === 'agents' && $record->queues()->exists()) {
                throw ValidationException::withMessages(['site_id' => 'Remove queue memberships before changing site.']);
            }
            if ($resource === 'queues' && ! array_key_exists('members', $data) && $record->members()->exists()) {
                throw ValidationException::withMessages(['site_id' => 'Update members when changing the queue site.']);
            }
        }

        return $data;
    }

    public function store(Request $request, string $resource)
    {
        $this->query($request, $resource);

        return $this->save($request, $resource, new (self::RESOURCES[$resource]), 201);
    }

    public function update(Request $request, string $resource, string $id)
    {
        return $this->save($request, $resource, $this->query($request, $resource)->findOrFail($id), 200);
    }

    private function save(Request $request, string $resource, $record, int $status)
    {
        $data = $this->validated($request, $resource, $record->exists ? $record : null);
        try {
            DB::transaction(function () use ($request, $resource, $record, $data, $status) {
                $before = $record->exists ? $this->snapshot($record) : null;
                $record->fill(Arr::except($data, ['skills', 'members']));
                $record->tenant_id = $this->tenant($request);
                $record->save();
                foreach (['skills', 'members'] as $relation) {
                    if (array_key_exists($relation, $data)) {
                        $pivot = collect($data[$relation])->mapWithKeys(fn ($item) => [$item['id'] => Arr::except($item, 'id')])->all();
                        $record->{$relation}()->sync($pivot);
                    }
                }
                $this->audit($request, $resource, $record->id, $status === 201 ? 'create' : 'update', ['before' => $before, 'after' => $this->snapshot($record)]);
            });
        } catch (QueryException $e) {
            if (in_array((string) $e->getCode(), ['23000', '23505', '23503'])) {
                throw ValidationException::withMessages(['record' => 'This record conflicts with an existing assignment or referenced record.']);
            }
            throw $e;
        }

        return response()->json($record->fresh(), $status);
    }

    private function snapshot($record): array
    {
        $record->unsetRelations();
        if ($record instanceof Agent) {
            $record->load('skills');
        } elseif ($record instanceof Queue) {
            $record->load(['members', 'skills']);
        }

        return $record->toArray();
    }

    private function audit(Request $request, string $resource, string $id, string $action, array $changes): void
    {
        DB::table('audit_logs')->insert([
            'id' => (string) Str::ulid(), 'tenant_id' => $this->tenant($request),
            'user_id' => $request->user()->id, 'action' => $action, 'resource' => $resource,
            'resource_id' => $id, 'changes' => json_encode($changes, JSON_THROW_ON_ERROR), 'created_at' => now(),
        ]);
    }

    public function destroy(Request $request, string $resource, string $id)
    {
        $record = $this->query($request, $resource)->findOrFail($id);
        try {
            DB::transaction(function () use ($request, $resource, $record) {
                $before = $this->snapshot($record);
                $record->delete();
                $this->audit($request, $resource, $record->id, 'delete', ['before' => $before]);
            });
        } catch (QueryException $e) {
            if (in_array((string) $e->getCode(), ['23000', '23503'])) {
                return response()->json(['message' => 'Remove dependent assignments before deleting this record.'], 409);
            }
            throw $e;
        }

        return response()->noContent();
    }

    public function users(Request $request)
    {
        $query = User::where('tenant_id', $this->tenant($request))->where('active', true);
        if ($request->user()->restrict_site_access && ! $request->user()->hasRole('Super Admin')) {
            $sites = $request->user()->sites()->pluck('sites.id');
            $query->where(fn ($q) => $q->whereKey($request->user()->id)->orWhereHas('sites', fn ($s) => $s->whereIn('sites.id', $sites))->orWhereHas('agent', fn ($a) => $a->whereIn('site_id', $sites)));
        }

        return $query->orderBy('name')->get(['id', 'name', 'email']);
    }

    public function audits(Request $request)
    {
        abort_if($request->user()->restrict_site_access && ! $request->user()->hasRole('Super Admin'), 403);

        return DB::table('audit_logs')->where('tenant_id', $this->tenant($request))->orderByDesc('id')->paginate(50);
    }
}
