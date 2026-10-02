<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    public function show(Attachment $attachment): StreamedResponse
    {
        $docRequest = $attachment->request;

        $user = Auth::user();
        $isOwner = $docRequest->student_id === $user->id;
        $isSuperAdmin = $user->role === 'staff'
            && $user->staffProfile?->admin_level === 'super_admin';
        $isAssignedHandler = $user->role === 'staff'
            && $docRequest->requestStages()->where('handled_by', $user->id)->exists();
        $isDepartmentStaff = $user->role === 'staff'
            && $user->staffProfile?->departments()
                ->whereIn('departments.id', $docRequest->requestStages()->select('department_id'))
                ->exists();

        abort_unless($isOwner || $isSuperAdmin || $isAssignedHandler || $isDepartmentStaff, 403);
        $disk = Storage::disk('local');

        abort_unless($disk->exists($attachment->file_path), 404);

        return $disk->response(
            $attachment->file_path,
            $attachment->original_name,
            ['Content-Type' => $attachment->mime_type]
        );
    }
}
