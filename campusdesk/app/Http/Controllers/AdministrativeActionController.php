<?php

namespace App\Http\Controllers;

use App\Models\AdministrativeAction;
use Illuminate\Http\Request;

class AdministrativeActionController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'action' => ['sometimes', 'string', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $rows = AdministrativeAction::with('actor:id,name')
            ->when($data['action'] ?? null, fn ($query, $action) => $query->where('action', $action))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($data['per_page'] ?? 20);

        return response()->json([
            'data' => $rows->items(),
            'meta' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
            ],
            'links' => ['next' => $rows->nextPageUrl(), 'prev' => $rows->previousPageUrl()],
        ]);
    }
}
