<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Programme;
use App\Models\StaffProfile;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminUserController extends Controller
{
    public function index(Request $request)
    {
        $v = $request->validate(['role' => ['sometimes', Rule::in(['student', 'staff'])], 'search' => 'sometimes|string|max:100', 'page' => 'sometimes|integer|min:1', 'per_page' => 'sometimes|integer|min:1|max:100']);
        $rows = User::with(['studentProfile', 'staffProfile.departments'])->when($v['role'] ?? null, fn ($q, $role) => $q->where('role', $role))
            ->when($v['search'] ?? null, fn ($q, $term) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$term.'%')->orWhere('email', 'like', '%'.$term.'%')))
            ->orderByDesc('created_at')->orderByDesc('id')->paginate($v['per_page'] ?? 20);
        return response()->json(['data' => collect($rows->items())->map(fn ($u) => $this->payload($u)), 'meta' => ['current_page' => $rows->currentPage(), 'last_page' => $rows->lastPage(), 'per_page' => $rows->perPage(), 'total' => $rows->total()], 'links' => ['next' => $rows->nextPageUrl(), 'prev' => $rows->previousPageUrl()]]);
    }

    public function show(User $user)
    {
        return response()->json(['data' => $this->payload($user->load(['studentProfile', 'staffProfile.departments']))]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $user = DB::transaction(function () use ($data) {
            $user = User::create(['name' => $data['name'], 'email' => strtolower(trim($data['email'])), 'password' => $data['password'], 'role' => $data['role']]);
            $this->saveProfile($user, $data);
            return $user;
        });
        return response()->json(['data' => $this->payload($user->load(['studentProfile', 'staffProfile.departments']))], 201);
    }

    public function update(Request $request, User $user)
    {
        $data = $this->validated($request, $user);
        DB::transaction(function () use ($user, $data) {
            $user->update(array_filter(['name' => $data['name'] ?? null, 'email' => isset($data['email']) ? strtolower(trim($data['email'])) : null, 'password' => $data['password'] ?? null], fn ($v) => $v !== null));
            $this->saveProfile($user, $data);
        });
        return response()->json(['data' => $this->payload($user->fresh()->load(['studentProfile', 'staffProfile.departments']))]);
    }

    public function adminLevel(Request $request, User $user)
    {
        abort_unless($user->role === 'staff' && $user->staffProfile, 422, 'Only staff can have an admin level.');
        $data = $request->validate(['admin_level' => ['present', 'nullable', Rule::in(['dept_admin', 'super_admin'])]]);
        DB::transaction(function () use ($request, $user, $data) {
            $profile = StaffProfile::where('user_id', $user->id)->lockForUpdate()->firstOrFail();
            $current = $profile->admin_level;
            $next = $data['admin_level'];
            if ($current === 'super_admin' && $next !== 'super_admin') $this->guardSuperAdmin($request, $user);
            if ($next === 'dept_admin' && ! $profile->departments()->wherePivot('is_primary', true)->exists()) abort(422, 'A department admin needs a primary department.');
            $profile->update(['admin_level' => $next]);
            if ($current !== $next) $user->tokens()->delete();
        });
        return response()->json(['data' => $this->payload($user->fresh()->load(['studentProfile', 'staffProfile.departments']))]);
    }

    public function destroy(Request $request, User $user)
    {
        DB::transaction(function () use ($request, $user) {
            if ($user->staffProfile?->admin_level === 'super_admin') $this->guardSuperAdmin($request, $user);
            $used = $user->requests()->exists() || DB::table('request_stages')->where('handled_by', $user->id)->exists()
                || DB::table('status_history')->where('changed_by', $user->id)->exists()
                || DB::table('stage_reassignments')->where('from_user_id', $user->id)->orWhere('to_user_id', $user->id)->orWhere('reassigned_by', $user->id)->exists()
                || $user->notifications()->exists();
            abort_if($used, 409, 'This user is referenced by request or activity data.');
            $user->tokens()->delete();
            if ($user->staffProfile) {
                $user->staffProfile->departments()->detach();
                $user->staffProfile->delete();
            }
            $user->studentProfile?->delete();
            $user->delete();
        });
        return response()->noContent();
    }

    private function guardSuperAdmin(Request $request, User $user): void
    {
        abort_if($request->user()->id === $user->id, 409, 'You cannot remove your own Super Admin access.');
        abort_if(StaffProfile::where('admin_level', 'super_admin')->lockForUpdate()->pluck('id')->count() <= 1, 409, 'The last Super Admin cannot be removed.');
    }

    private function validated(Request $request, ?User $user = null): array
    {
        if ($request->exists('email')) $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);
        if ($request->exists('matricule')) $request->merge(['matricule' => strtoupper(trim((string) $request->input('matricule')))]);
        if ($request->exists('staff_id')) $request->merge(['staff_id' => strtoupper(trim((string) $request->input('staff_id')))]);
        $create = $user === null;
        $role = $user?->role ?? $request->input('role');
        $base = $create ? 'required' : 'sometimes';
        $rules = [
            'role' => $create ? ['required', Rule::in(['student', 'staff'])] : ['prohibited'],
            'name' => [$base, 'string', 'max:255'],
            'email' => [$base, 'email', 'max:255', Rule::unique('users')->ignore($user?->id)],
            'password' => [$base, 'string', 'min:8'],
        ];
        if ($role === 'student') $rules += [
            'matricule' => [$base, 'string', 'max:100', Rule::unique('student_profiles')->ignore($user?->studentProfile?->id)],
            'faculty_id' => [$base, 'integer', 'exists:faculties,id'],
            'department_id' => [$base, 'integer', 'exists:departments,id'],
            'programme_id' => [$base, 'integer', 'exists:programmes,id'],
            'level' => [$base, Rule::in(['100', '200', '300', '400', '500', '600'])],
        ];
        if ($role === 'staff') $rules += [
            'staff_id' => [$base, 'string', 'max:100', Rule::unique('staff_profiles')->ignore($user?->staffProfile?->id)],
            'department_ids' => [$base, 'array', 'min:1'],
            'department_ids.*' => ['integer', 'distinct', 'exists:departments,id'],
            'primary_department_id' => [$base, 'integer', 'exists:departments,id'],
        ];
        $data = Validator::make($request->all(), $rules)->validate();
        if ($role === 'student') {
            $faculty = $data['faculty_id'] ?? $user?->studentProfile?->faculty_id;
            $department = $data['department_id'] ?? $user?->studentProfile?->department_id;
            $programme = $data['programme_id'] ?? $user?->studentProfile?->programme_id;
            if (!Department::whereKey($department)->where('faculty_id', $faculty)->exists() || !Programme::whereKey($programme)->where('department_id', $department)->exists()) {
                throw ValidationException::withMessages(['programme_id' => 'Faculty, department and programme must agree.']);
            }
        }
        if ($role === 'staff') {
            $ids = $data['department_ids'] ?? $user?->staffProfile?->departments->pluck('id')->all();
            $primary = $data['primary_department_id'] ?? $user?->staffProfile?->departments->first(fn ($d) => $d->pivot->is_primary)?->id;
            if (!$ids || !in_array($primary, $ids)) throw ValidationException::withMessages(['primary_department_id' => 'Choose one primary department from the membership list.']);
        }
        return $data;
    }

    private function saveProfile(User $user, array $data): void
    {
        if ($user->role === 'student') {
            $profile = $user->studentProfile ?? new StudentProfile(['user_id' => $user->id, 'status' => 'active']);
            $profile->fill(array_intersect_key($data, array_flip(['matricule', 'faculty_id', 'department_id', 'programme_id', 'level'])))->save();
            return;
        }
        $profile = $user->staffProfile ?? new StaffProfile(['user_id' => $user->id]);
        $profile->fill(array_intersect_key($data, array_flip(['staff_id'])))->save();
        if (isset($data['department_ids']) || isset($data['primary_department_id'])) {
            $ids = $data['department_ids'] ?? $profile->departments->pluck('id')->all();
            $primary = $data['primary_department_id'] ?? $profile->departments->first(fn ($d) => $d->pivot->is_primary)?->id;
            $profile->departments()->sync(collect($ids)->mapWithKeys(fn ($id) => [$id => ['is_primary' => $id === $primary]])->all());
        }
    }

    private function payload(User $user): array
    {
        return ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $user->role, 'created_at' => $user->created_at,
            'student_profile' => $user->studentProfile,
            'staff_profile' => $user->staffProfile ? ['staff_id' => $user->staffProfile->staff_id, 'admin_level' => $user->staffProfile->admin_level,
                'departments' => $user->staffProfile->departments->map(fn ($d) => ['id' => $d->id, 'name' => $d->name, 'is_primary' => (bool) $d->pivot->is_primary])] : null];
    }
}
