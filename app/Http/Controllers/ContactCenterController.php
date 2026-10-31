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
        if ($resource === 'extensions') {
            if ($term = trim((string) $request->query('q'))) {
                $query->where(fn ($match) => $match->where('number', 'like', "%{$term}%")->orWhere('name', 'like', "%{$term}%")->orWhere('user_device', 'like', "%{$term}%"));
            }

            return $query->orderBy('number')->paginate(50);
        }
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
            'extensions' => [
                'number' => ['required', 'string', 'regex:/^[0-9]{2,20}$/', $unique('number')],
                'class_of_service' => ['sometimes', Rule::in(array_keys(Extension::CLASSES_OF_SERVICE))],
                'language' => ['sometimes', Rule::in(array_keys(Extension::LANGUAGES))],
                'features_password' => ['nullable', 'string', 'max:64'],
                'email_address' => ['nullable', 'email', 'max:255'],
                'internal_cid_name' => ['nullable', 'string', 'max:60'],
                'internal_cid_number' => ['nullable', 'string', 'max:32'],
                'external_cid_name' => ['nullable', 'string', 'max:60'],
                'external_cid_number' => ['nullable', 'string', 'max:32'],
                'emergency_cid_name' => ['nullable', 'string', 'max:60'],
                'emergency_cid_number' => ['nullable', 'string', 'max:32'],
                'account_code' => ['nullable', 'string', 'max:32'],
                'technology' => ['sometimes', Rule::in(array_keys(Extension::TECHNOLOGIES))],
                'user_device' => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('extensions', 'user_device')->where('tenant_id', $tenant)->ignore($record?->id)],
                'device_password' => ['nullable', 'string', 'min:8', 'max:128'],
                'device_description' => ['nullable', 'string', 'max:255'],
                'profile' => ['nullable', 'string', 'max:100'],
                'max_contacts' => ['sometimes', 'integer', 'between:1,100'],
                'codecs' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9,._-]+$/'],
                'dtmf_mode' => ['sometimes', Rule::in(array_keys(Extension::DTMF_MODES))],
                'device_emergency_cid_name' => ['nullable', 'string', 'max:60'],
                'device_emergency_cid_number' => ['nullable', 'string', 'max:32'],
                'dispatchable_location' => ['nullable', 'string', 'max:255'],
                'deny' => ['nullable', 'string', 'max:100', 'regex:/^[0-9a-fA-F.:\/(),\s]*$/'],
                'permit' => ['nullable', 'string', 'max:100', 'regex:/^[0-9a-fA-F.:\/(),\s]*$/'],
                'ring_device' => ['sometimes', 'boolean'],
                'webrtc_client' => ['sometimes', 'boolean'],
                'voicemail_enabled' => ['sometimes', 'boolean'],
                'voicemail_mailbox' => ['nullable', 'string', 'regex:/^[0-9]{2,20}$/'],
                'voicemail_password' => ['nullable', 'string', 'max:64'],
                'voicemail_email' => ['nullable', 'email', 'max:255'],
                'voicemail_options' => ['sometimes', 'array'],
                'voicemail_options.*' => ['string', Rule::in(array_keys(Extension::VOICEMAIL_OPTIONS))],
                'recording_mode' => ['sometimes', Rule::in(array_keys(Extension::RECORDING_MODES))],
                'call_group' => ['nullable', 'string', 'max:50', 'regex:/^[0-9,\-\s]*$/'],
                'pickup_group' => ['nullable', 'string', 'max:50', 'regex:/^[0-9,\-\s]*$/'],
                'transport' => ['sometimes', Rule::in(array_keys(Extension::TRANSPORTS))],
                'max_call_duration' => ['nullable', 'integer', 'between:1,1440'],
                'call_waiting' => ['sometimes', 'boolean'],
                'follow_me_enabled' => ['sometimes', 'boolean'],
                'follow_me' => ['sometimes', 'array', 'max:10'],
                'follow_me.*' => ['array:number,timeout'],
                'follow_me.*.number' => ['required', 'string', 'regex:/^[0-9]{2,20}$/', 'distinct'],
                'follow_me.*.timeout' => ['required', 'integer', 'between:5,120'],
                'incoming_routes' => ['sometimes', 'array', 'max:20'],
                'incoming_routes.*' => ['array:did,description'],
                'incoming_routes.*.did' => ['required', 'string', 'regex:/^\+?[0-9]{3,20}$/', 'distinct'],
                'incoming_routes.*.description' => ['nullable', 'string', 'max:255'],
                'contact_company' => ['nullable', 'string', 'max:255'],
                'contact_position' => ['nullable', 'string', 'max:255'],
                'contact_phone' => ['nullable', 'string', 'max:32'],
                'contact_mobile' => ['nullable', 'string', 'max:32'],
                'contact_notes' => ['nullable', 'string', 'max:1000'],
            ],
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
        if ($resource === 'extensions') {
            $this->pruneExtensionRepeaters($request);
        }
        $data = $request->validate($rules);
        if ($resource === 'extensions') {
            $data = $this->extensionSettings($data, $record);
        }
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

    /**
     * Drop empty repeater rows before validation so an operator can leave a
     * blank follow me or DID row in the editor without a required error.
     */
    private function pruneExtensionRepeaters(Request $request): void
    {
        foreach (['follow_me' => 'number', 'incoming_routes' => 'did'] as $list => $key) {
            $rows = $request->input($list);
            if (! is_array($rows)) {
                continue;
            }
            $request->merge([$list => array_values(array_filter(
                $rows,
                fn ($row) => is_array($row) && filled($row[$key] ?? null),
            ))]);
        }
    }

    /**
     * Normalise endpoint settings before persistence. Blank secrets keep the
     * stored value, blank option fields fall back to column defaults, and a
     * selected device technology always carries an endpoint name and password.
     */
    private function extensionSettings(array $data, ?Extension $record): array
    {
        foreach (['class_of_service', 'language', 'technology', 'profile', 'dtmf_mode', 'transport', 'recording_mode'] as $field) {
            if (array_key_exists($field, $data) && blank($data[$field])) {
                unset($data[$field]);
            }
        }
        foreach (['features_password', 'device_password', 'voicemail_password'] as $secret) {
            if (array_key_exists($secret, $data) && blank($data[$secret])) {
                unset($data[$secret]);
            }
        }
        $technology = $data['technology'] ?? $record?->technology ?? 'none';
        $device = array_key_exists('user_device', $data) ? $data['user_device'] : $record?->user_device;
        if ($technology === 'none') {
            // A virtual extension registers no endpoint device.
            $data['user_device'] = null;
        } elseif (blank($device)) {
            // The device name follows the extension number until it is customised.
            $data['user_device'] = $data['number'] ?? $record?->number;
        } else {
            $data['user_device'] = $device;
        }
        if ($technology !== 'none') {
            if (blank($data['device_password'] ?? null) && ! $record?->has_device_password) {
                throw ValidationException::withMessages(['device_password' => 'Set a password for the endpoint device.']);
            }
        }
        foreach (['follow_me', 'incoming_routes'] as $list) {
            if (array_key_exists($list, $data) && is_array($data[$list])) {
                $data[$list] = array_values($data[$list]);
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

    /**
     * Toggle a record between active and inactive without resubmitting the
     * whole editor payload. Standardised across every domain resource so the
     * list screens all expose the same Activate/Deactivate action.
     */
    public function status(Request $request, string $resource, string $id)
    {
        $data = $request->validate(['active' => ['required', 'boolean']]);
        $record = $this->query($request, $resource)->findOrFail($id);

        DB::transaction(function () use ($request, $resource, $record, $data) {
            $before = $this->snapshot($record);
            $record->update(['active' => $data['active']]);
            $this->audit($request, $resource, $record->id, $data['active'] ? 'activate' : 'deactivate', [
                'before' => $before,
                'after' => $this->snapshot($record->fresh()),
            ]);
        });

        return response()->json($this->snapshot($record->fresh()));
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
