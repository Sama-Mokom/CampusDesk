<?php

namespace App\Http\Controllers;

use App\Models\Request as DocumentRequest;
use App\Models\StatusHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminOverviewController extends Controller
{
    private const STATUSES = ['draft', 'pending', 'in_review', 'forwarded', 'ready', 'collected', 'rejected'];

    private function filters(Request $request, array $extra = []): array
    {
        $v = $request->validate(array_merge(['page' => 'sometimes|integer|min:1', 'per_page' => 'sometimes|integer|min:1|max:100', 'date_from' => 'sometimes|date_format:Y-m-d', 'date_to' => 'sometimes|date_format:Y-m-d|after_or_equal:date_from'], $extra));
        return $v;
    }

    private function paged($query, array $v)
    {
        $page = $query->orderByDesc('created_at')->orderByDesc('id')->paginate($v['per_page'] ?? 20);
        return response()->json(['data' => $page->items(), 'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()], 'links' => ['next' => $page->nextPageUrl(), 'prev' => $page->previousPageUrl()]]);
    }

    public function requests(Request $request)
    {
        $v = $this->filters($request, ['search' => 'sometimes|string|max:100', 'faculty_id' => 'sometimes|integer|exists:faculties,id', 'department_id' => 'sometimes|integer|exists:departments,id', 'request_type_id' => 'sometimes|integer|exists:request_types,id', 'status' => ['sometimes', Rule::in(self::STATUSES)], 'reopened' => 'sometimes|boolean']);
        $query = DocumentRequest::with(['requestType:id,name', 'student:id,name', 'student.studentProfile:id,user_id,matricule,faculty_id,department_id'])
            ->when($v['search'] ?? null, fn ($q, $term) => $q->where(fn ($q) => $q->where('requests.id', 'like', '%'.$term.'%')->orWhereHas('student', fn ($u) => $u->where('name', 'like', '%'.$term.'%')->orWhereHas('studentProfile', fn ($p) => $p->where('matricule', 'like', '%'.$term.'%')))))
            ->when($v['faculty_id'] ?? null, fn ($q, $id) => $q->whereHas('student.studentProfile', fn ($p) => $p->where('faculty_id', $id)))
            ->when($v['department_id'] ?? null, fn ($q, $id) => $q->where(fn ($q) => $q->whereHas('student.studentProfile', fn ($p) => $p->where('department_id', $id))->orWhereHas('requestStages', fn ($s) => $s->where('department_id', $id))))
            ->when($v['request_type_id'] ?? null, fn ($q, $id) => $q->where('request_type_id', $id))
            ->when($v['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when(array_key_exists('reopened', $v), fn ($q) => $q->where('is_reopened', $v['reopened']))
            ->when($v['date_from'] ?? null, fn ($q, $date) => $q->where('created_at', '>=', Carbon::parse($date, config('app.timezone'))->startOfDay()->utc()))
            ->when($v['date_to'] ?? null, fn ($q, $date) => $q->where('created_at', '<=', Carbon::parse($date, config('app.timezone'))->endOfDay()->utc()));
        return $this->paged($query, $v);
    }

    public function show(DocumentRequest $request)
    {
        $request->load(['student:id,name,email', 'student.studentProfile', 'requestType:id,name', 'requestStages.department:id,name', 'attachments', 'statusHistories.user:id,name']);
        return response()->json(['data' => [
            'id' => $request->id, 'student' => $request->student, 'request_type' => $request->requestType,
            'description' => $request->description, 'status' => $request->status, 'is_reopened' => $request->is_reopened,
            'created_at' => $request->created_at, 'stages' => $request->requestStages,
            'attachments' => $request->attachments->map(fn ($a) => ['id' => $a->id, 'original_name' => $a->original_name, 'mime_type' => $a->mime_type]),
            'status_history' => $request->statusHistories->map(fn ($h) => $this->history($h)),
        ]]);
    }

    public function audit(Request $request)
    {
        $v = $this->filters($request, ['request_id' => 'sometimes|integer|min:1', 'actor_id' => 'sometimes|integer|min:1', 'new_status' => 'sometimes|string|max:50']);
        $query = StatusHistory::with('user:id,name')->when($v['request_id'] ?? null, fn ($q, $id) => $q->where('request_id', $id))
            ->when($v['actor_id'] ?? null, fn ($q, $id) => $q->where('changed_by', $id))
            ->when($v['new_status'] ?? null, fn ($q, $status) => $q->where('new_status', $status))
            ->when($v['date_from'] ?? null, fn ($q, $date) => $q->where('changed_at', '>=', Carbon::parse($date, config('app.timezone'))->startOfDay()->utc()))
            ->when($v['date_to'] ?? null, fn ($q, $date) => $q->where('changed_at', '<=', Carbon::parse($date, config('app.timezone'))->endOfDay()->utc()));
        $page = $query->orderByDesc('changed_at')->orderByDesc('id')->paginate($v['per_page'] ?? 20);
        return response()->json(['data' => collect($page->items())->map(fn ($h) => $this->history($h)), 'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()], 'links' => ['next' => $page->nextPageUrl(), 'prev' => $page->previousPageUrl()]]);
    }

    public function stats()
    {
        $counts = DocumentRequest::select('status', DB::raw('COUNT(*) AS total'))->groupBy('status')->pluck('total', 'status');
        $byStatus = array_fill_keys(self::STATUSES, 0);
        foreach ($counts as $status => $count) $byStatus[$status] = (int) $count;
        $today = Carbon::now(config('app.timezone'));
        $recent = StatusHistory::with('user:id,name')->orderByDesc('changed_at')->orderByDesc('id')->limit(10)->get()->map(fn ($h) => $this->history($h));
        // Correlated subqueries bound memory use to the database aggregation result.
        $driver = DB::connection()->getDriverName();
        $epoch = $driver === 'sqlite' ? "strftime('%%s', %s)" : 'UNIX_TIMESTAMP(%s)';
        $start = "COALESCE((SELECT MAX(h.changed_at) FROM status_history h WHERE h.request_id = requests.id AND h.old_status = 'rejected' AND h.new_status = 'pending'), requests.created_at)";
        $end = "(SELECT MIN(h.changed_at) FROM status_history h WHERE h.request_id = requests.id AND h.request_stage_id IS NULL AND h.new_status IN ('ready','rejected') AND h.changed_at >= $start)";
        $sql = sprintf('AVG((%s - %s) / 3600.0) AS average', sprintf($epoch, $end), sprintf($epoch, $start));
        $average = DocumentRequest::whereIn('status', ['ready', 'collected', 'rejected'])->selectRaw($sql)->first()?->average;
        return response()->json(['data' => ['total' => array_sum($byStatus), 'requests_today' => DocumentRequest::whereBetween('created_at', [$today->copy()->startOfDay()->utc(), $today->copy()->endOfDay()->utc()])->count(), 'by_status' => $byStatus, 'avg_resolution_hours' => $average === null ? null : round((float) $average, 2), 'recent_activity' => $recent]]);
    }

    private function history(StatusHistory $h): array
    {
        return ['id' => $h->id, 'request_id' => $h->request_id, 'request_stage_id' => $h->request_stage_id,
            'old_status' => $h->old_status, 'new_status' => $h->new_status,
            'changed_by' => $h->user ? ['id' => $h->user->id, 'name' => $h->user->name] : null,
            'note' => $h->note, 'changed_at' => $h->changed_at];
    }
}
