<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRequestRequest;
use App\Http\Resources\RequestResource;
use App\Models\Request as UserRequest;
use App\Models\RequestType;
use App\Models\RequestTypeTier;
use App\Services\RequestCreationService;
use App\Services\RequestEventRecorder;
use App\Services\RequestStatusNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use UnexpectedValueException;

class RequestController extends Controller
{
    public function index()
    {
        return RequestResource::collection(
            Auth::user()->requests()
                ->with([
                    'requestType',
                    'attachments',
                    'requestStages.department',
                    'statusHistories',
                    'payments',
                    'pendingUpgradeTier',
                ])
                ->latest()
                ->get()
        );
    }

    public function create()
    {
        //
    }

    /**
     * Create a request and its initial department stages.
     */
    public function store(StoreRequestRequest $request, RequestCreationService $requestCreation)
    {
        $storedPaths = [];

        try {
            return DB::transaction(function () use ($request, $requestCreation, &$storedPaths) {
                $type = RequestType::findOrFail($request->request_type_id);
                $userRequest = $requestCreation->createForStudent(
                    Auth::user(),
                    $type,
                    $request->description,
                    $request->input('request_type_tier_id') ? (int) $request->input('request_type_tier_id') : null,
                );

                if ($request->hasFile('attachments')) {
                    foreach ($request->file('attachments', []) as $file) {
                        $path = $file->store('attachments', 'local');

                        if (! is_string($path) || $path === '') {
                            throw new \RuntimeException('Failed to store the uploaded attachment.');
                        }

                        $storedPaths[] = $path;

                        $userRequest->attachments()->create([
                            'file_path'     => $path,
                            'original_name' => $file->getClientOriginalName(),
                            'mime_type'     => $file->getMimeType(),
                            'file_size'     => $file->getSize(),
                        ]);
                    }
                }

                return new RequestResource(
                    $userRequest->load([
                        'requestType',
                        'requestStages.department',
                        'attachments',
                        'statusHistories',
                        'payments',
                        'pendingUpgradeTier',
                    ])
                );
            });
        } catch (\Throwable $exception) {
            foreach ($storedPaths as $path) {
                try {
                    Storage::disk('local')->delete($path);
                } catch (\Throwable $cleanupException) {
                    report($cleanupException);
                }
            }

            throw $exception;
        }
    }

    public function show(UserRequest $request)
    {
        $user = Auth::user();
        $isOwner = $request->student_id === $user->id;
        $isStaff = $user->role === 'staff';

        abort_unless($isOwner || $isStaff, 403);

        return new RequestResource(
            $request->load([
                'requestStages',
                'attachments',
                'statusHistories',
                'payments',
                'pendingUpgradeTier',
            ])
        );
    }

    public function reopen(
        Request $httpRequest,
        UserRequest $request,
        RequestStatusNotificationService $notifications,
        RequestEventRecorder $events,
    ) {
        $user = Auth::user();
        $isOwner = $request->student_id === $user->id;
        $isSuperAdmin = $user->role === 'staff'
            && $user->staffProfile?->admin_level === 'super_admin';

        abort_unless($isOwner || $isSuperAdmin, 403);

        return DB::transaction(function () use ($httpRequest, $request, $user, $isSuperAdmin, $notifications, $events) {
            $lockedRequest = UserRequest::query()
                ->whereKey($request->id)
                ->lockForUpdate()
                ->firstOrFail();

            $tierId = $httpRequest->input('request_type_tier_id');
            $cancelUpgrade = $httpRequest->boolean('cancel_upgrade');

            if ($cancelUpgrade && $lockedRequest->status === 'awaiting_payment' && $lockedRequest->is_reopened) {
                // Cancel in-progress upgrade during reopen
                $lockedRequest->pending_upgrade_tier_id = null;
                $lockedRequest->payments()->whereIn('status', ['pending', 'processing'])->update(['status' => 'expired']);

                if ($lockedRequest->amount_paid >= $lockedRequest->fee_amount) {
                    $rejectedStages = $lockedRequest->requestStages()->where('status', 'rejected')->lockForUpdate()->get();
                    if ($rejectedStages->isNotEmpty()) {
                        $rejectedStages->first()->update(['status' => 'pending', 'handled_by' => null]);
                    }
                    $lockedRequest->update(['status' => 'pending']);
                    $lockedRequest->statusHistories()->create([
                        'old_status' => 'awaiting_payment',
                        'new_status' => 'pending',
                        'changed_by' => $user->id,
                        'note'       => "Upgrade cancelled; reopened with {$lockedRequest->tier_name_snapshot} tier.",
                    ]);
                    $events->recordRequestStatusChanged($lockedRequest);
                    $notifications->notifyStudent($lockedRequest, 'pending');
                } else {
                    $lockedRequest->save();
                }

                return new RequestResource(
                    $lockedRequest->fresh()->load([
                        'requestType',
                        'requestStages.department',
                        'attachments',
                        'statusHistories',
                        'payments',
                        'pendingUpgradeTier',
                    ])
                );
            }

            abort_unless(
                $lockedRequest->status === 'rejected',
                422,
                'Only rejected requests can be reopened.'
            );

            $rejectedStages = $lockedRequest->requestStages()
                ->where('status', 'rejected')
                ->lockForUpdate()
                ->get();

            if ($rejectedStages->count() !== 1) {
                throw new UnexpectedValueException(
                    "Invariant violated: Expected exactly 1 rejected stage for request {$lockedRequest->id}, found {$rejectedStages->count()}."
                );
            }

            if ($tierId && (int) $tierId !== (int) $lockedRequest->request_type_tier_id) {
                $newTier = RequestTypeTier::where('request_type_id', $lockedRequest->request_type_id)->findOrFail($tierId);

                $lockedRequest->update([
                    'status'                  => 'awaiting_payment',
                    'pending_upgrade_tier_id' => $newTier->id,
                    'is_reopened'             => true,
                ]);

                $lockedRequest->statusHistories()->create([
                    'old_status' => 'rejected',
                    'new_status' => 'awaiting_payment',
                    'changed_by' => $user->id,
                    'note'       => "Reopened with requested upgrade to {$newTier->name}. Awaiting fee top-up payment.",
                ]);

                return new RequestResource(
                    $lockedRequest->fresh()->load([
                        'requestType',
                        'requestStages.department',
                        'attachments',
                        'statusHistories',
                        'payments',
                        'pendingUpgradeTier',
                    ])
                );
            }

            // Standard same-tier free reopen
            $rejectedStages->first()->update([
                'status'     => 'pending',
                'handled_by' => null,
            ]);

            $lockedRequest->update([
                'status'      => 'pending',
                'is_reopened' => true,
            ]);

            $lockedRequest->statusHistories()->create([
                'old_status' => 'rejected',
                'new_status' => 'pending',
                'changed_by' => $user->id,
                'note'       => $isSuperAdmin
                    ? 'Request reopened by administrator.'
                    : 'Request reopened by student.',
            ]);

            $events->recordRequestStatusChanged($lockedRequest);
            $notifications->notifyStudent($lockedRequest, 'pending');

            return new RequestResource(
                $lockedRequest->fresh()->load([
                    'requestType',
                    'requestStages.department',
                    'attachments',
                    'statusHistories',
                    'payments',
                    'pendingUpgradeTier',
                ])
            );
        });
    }

    public function collect(UserRequest $request, RequestEventRecorder $events)
    {
        $user = Auth::user();
        abort_unless($request->student_id === $user->id, 403);

        return DB::transaction(function () use ($request, $user, $events) {
            $lockedRequest = UserRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedRequest->status === 'ready', 422, 'Only ready requests can be marked as collected.');

            $lockedRequest->update(['status' => 'collected']);

            $lockedRequest->statusHistories()->create([
                'old_status'       => 'ready',
                'new_status'       => 'collected',
                'changed_by'       => $user->id,
                'request_stage_id' => null,
                'note'             => 'Document collected by student.',
            ]);

            $events->recordRequestStatusChanged($lockedRequest);

            return new RequestResource(
                $lockedRequest->fresh()->load([
                    'requestType',
                    'requestStages.department',
                    'attachments',
                    'statusHistories',
                    'payments',
                    'pendingUpgradeTier',
                ])
            );
        });
    }
}
