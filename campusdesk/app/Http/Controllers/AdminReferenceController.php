<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Faculty;
use App\Models\Programme;
use App\Models\RequestType;
use App\Models\StudentProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminReferenceController extends Controller
{
    private function model(string $kind): string
    {
        return match ($kind) {
            'faculties' => Faculty::class, 'departments' => Department::class,
            'programmes' => Programme::class, 'request-types' => RequestType::class,
            default => abort(404),
        };
    }

    public function index(Request $request)
    {
        $input = $request->validate(['search' => 'sometimes|string|max:100', 'page' => 'sometimes|integer|min:1', 'per_page' => 'sometimes|integer|min:1|max:100']);
        $kind = $request->route('kind');
        $model = $this->model($kind);
        $query = $model::query();
        if ($kind === 'request-types') {
            $query->with('tiers');
        }
        $rows = $query->when($input['search'] ?? null, fn ($q, $term) => $q->where('name', 'like', '%'.$term.'%'))
            ->orderByDesc('created_at')->orderByDesc('id')->paginate($input['per_page'] ?? 20);
        return response()->json(['data' => $rows->items(), 'meta' => ['current_page' => $rows->currentPage(), 'last_page' => $rows->lastPage(), 'per_page' => $rows->perPage(), 'total' => $rows->total()], 'links' => ['next' => $rows->nextPageUrl(), 'prev' => $rows->previousPageUrl()]]);
    }

    public function store(Request $request)
    {
        $kind = $request->route('kind');
        $data = $this->validated($request, $kind);
        $tiers = null;
        if ($kind === 'request-types' && array_key_exists('tiers', $data)) {
            $tiers = $data['tiers'];
            unset($data['tiers']);
        }
        $model = $this->model($kind);
        $row = DB::transaction(function () use ($model, $data, $kind, $tiers) {
            $created = $model::create($data);
            if ($kind === 'request-types' && is_array($tiers)) {
                foreach ($tiers as $tierData) {
                    $created->tiers()->create($tierData);
                }
            }
            return $created;
        });
        return response()->json(['data' => $kind === 'request-types' ? $row->load('tiers') : $row], 201);
    }

    public function update(Request $request, int $id)
    {
        $kind = $request->route('kind');
        $model = $this->model($kind);
        $row = $model::findOrFail($id);
        $data = $this->validated($request, $kind, $row);
        $tiers = null;
        if ($kind === 'request-types' && array_key_exists('tiers', $data)) {
            $tiers = $data['tiers'];
            unset($data['tiers']);
        }
        if ($kind === 'faculties' && array_key_exists('matricule_prefix', $data) && $data['matricule_prefix'] !== $row->matricule_prefix && StudentProfile::where('faculty_id', $id)->exists()) {
            abort(409, 'The matricule prefix cannot change while students belong to this faculty.');
        }
        if ($kind === 'departments') $this->guardRecordsDepartment($row, $data);
        if ($kind === 'programmes' && isset($data['department_id']) && $data['department_id'] !== $row->department_id && StudentProfile::where('programme_id', $id)->exists()) {
            abort(409, 'A programme with students cannot move to another department.');
        }
        DB::transaction(function () use ($row, $data, $kind, $tiers) {
            $row->update($data);
            if ($kind === 'request-types' && is_array($tiers)) {
                foreach ($tiers as $tierData) {
                    $row->tiers()->updateOrCreate(
                        ['code' => $tierData['code']],
                        [
                            'name'            => $tierData['name'],
                            'description'     => $tierData['description'] ?? null,
                            'fee_amount'      => $tierData['fee_amount'],
                            'currency'        => $tierData['currency'] ?? 'XAF',
                            'turnaround_days' => $tierData['turnaround_days'],
                            'priority'        => $tierData['priority'] ?? 1,
                            'is_active'       => $tierData['is_active'] ?? true,
                            'sort_order'      => $tierData['sort_order'] ?? 1,
                        ]
                    );
                }
            }
        });
        return response()->json(['data' => $kind === 'request-types' ? $row->fresh(['tiers']) : $row->fresh()]);
    }

    public function destroy(Request $request, int $id)
    {
        $kind = $request->route('kind');
        $model = $this->model($kind);
        $row = $model::findOrFail($id);
        $used = match ($kind) {
            'faculties' => $row->departments()->exists() || $row->programmes()->exists() || StudentProfile::where('faculty_id', $id)->exists(),
            'departments' => $row->programmes()->exists() || $row->requestStages()->exists() || $row->staffProfiles()->exists() || StudentProfile::where('department_id', $id)->exists() || $this->sequenceUses($id),
            'programmes' => StudentProfile::where('programme_id', $id)->exists(),
            'request-types' => $row->requests()->exists(),
        };
        abort_if($used, 409, 'This record is referenced and cannot be deleted.');
        if ($kind === 'departments') $this->guardRecordsDepartment($row, ['type' => null]);
        $row->delete();
        return response()->noContent();
    }

    private function validated(Request $request, string $kind, $row = null): array
    {
        $partial = $row ? 'sometimes' : 'required';
        $rules = match ($kind) {
            'faculties' => [
                'name' => [$partial, 'string', 'max:255'],
                'code' => [$partial, 'string', 'max:50', Rule::unique('faculties')->ignore($row?->id)],
                'matricule_prefix' => [$partial, 'string', 'max:50', Rule::unique('faculties')->ignore($row?->id)],
            ],
            'departments' => [
                'faculty_id' => [$partial, 'integer', 'exists:faculties,id'],
                'name' => [$partial, 'string', 'max:255'],
                'code' => [$partial, 'string', 'max:50', Rule::unique('departments')->ignore($row?->id)],
                'type' => [$partial, Rule::in(['academic', 'records', 'admin'])],
            ],
            'programmes' => [
                'department_id' => [$partial, 'integer', 'exists:departments,id'],
                'name' => [$partial, 'string', 'max:255'],
                'code' => [$partial, 'string', 'max:50'],
                'degree_type' => [$partial, Rule::in(['BACHELOR', 'CERTIFICATE', 'MASTER', 'PHD'])],
            ],
            'request-types' => [
                'name' => [$partial, 'string', 'max:255'],
                'description' => ['sometimes', 'nullable', 'string'],
                'default_department_sequence' => [$partial, 'array', 'min:1'],
                'default_department_sequence.*' => ['required'],
                'tiers' => ['sometimes', 'array', 'min:1'],
                'tiers.*.id' => ['sometimes', 'integer', 'exists:request_type_tiers,id'],
                'tiers.*.code' => ['required_with:tiers', 'string', 'max:50'],
                'tiers.*.name' => ['required_with:tiers', 'string', 'max:100'],
                'tiers.*.description' => ['sometimes', 'nullable', 'string'],
                'tiers.*.fee_amount' => ['required_with:tiers', 'integer', 'min:100'],
                'tiers.*.currency' => ['sometimes', 'string', 'size:3'],
                'tiers.*.turnaround_days' => ['required_with:tiers', 'integer', 'min:1'],
                'tiers.*.priority' => ['required_with:tiers', 'integer', Rule::in([1, 2, 3])],
                'tiers.*.is_active' => ['sometimes', 'boolean'],
                'tiers.*.sort_order' => ['sometimes', 'integer', 'min:1'],
            ],
        };
        $data = $request->validate($rules);
        if ($kind === 'programmes') {
            $code = $data['code'] ?? $row?->code;
            $department = $data['department_id'] ?? $row?->department_id;
            $degree = $data['degree_type'] ?? $row?->degree_type;
            if (Programme::where('code', $code)->where('department_id', $department)->where('degree_type', $degree)->when($row, fn ($q) => $q->whereKeyNot($row->id))->exists()) {
                throw ValidationException::withMessages(['code' => 'This code, department and degree combination already exists.']);
            }
        }
        if ($kind === 'request-types' && isset($data['default_department_sequence'])) {
            foreach ($data['default_department_sequence'] as $entry) {
                if (in_array($entry, ['STUDENT_DEPARTMENT', 'FACULTY_RECORDS'], true)) continue;
                if (!is_int($entry) && !(is_string($entry) && ctype_digit($entry))) throw ValidationException::withMessages(['default_department_sequence' => 'Unsupported routing token.']);
                if (!Department::whereKey((int) $entry)->exists()) throw ValidationException::withMessages(['default_department_sequence' => 'A routing department does not exist.']);
            }
            if (in_array('FACULTY_RECORDS', $data['default_department_sequence'], true)) {
                $missing = Faculty::whereHas('departments', fn ($q) => $q->where('type', 'academic'))
                    ->whereDoesntHave('departments', fn ($q) => $q->where('type', 'records'))->exists();
                if ($missing) throw ValidationException::withMessages(['default_department_sequence' => 'Each eligible faculty needs a records department.']);
            }
        }
        return $data;
    }

    private function sequenceUses(int $id): bool
    {
        foreach (RequestType::select('default_department_sequence')->cursor() as $type) {
            if (in_array($id, array_map(fn ($v) => is_numeric($v) ? (int) $v : $v, $type->default_department_sequence), true)) return true;
        }
        return false;
    }

    private function guardRecordsDepartment(Department $row, array $data): void
    {
        $nextType = array_key_exists('type', $data) ? $data['type'] : $row->type;
        if ($row->type !== 'records' || ($nextType === 'records' && ($data['faculty_id'] ?? $row->faculty_id) === $row->faculty_id)) return;
        if (Department::where('faculty_id', $row->faculty_id)->where('type', 'records')->whereKeyNot($row->id)->exists()) return;
        $needed = StudentProfile::where('faculty_id', $row->faculty_id)->exists()
            && RequestType::all()->contains(fn ($type) => in_array('FACULTY_RECORDS', $type->default_department_sequence, true));
        abort_if($needed, 409, 'This is the only records department for a faculty with students and records routing.');
    }
}
