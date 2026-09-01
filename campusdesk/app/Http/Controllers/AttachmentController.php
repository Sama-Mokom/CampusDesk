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
        $isStaff = $user->role === 'staff';

        abort_unless($isOwner || $isStaff, 403);
        abort_unless(Storage::exists($attachment->file_path), 404);

        return Storage::response(
            $attachment->file_path,
            $attachment->original_name,
            ['Content-Type' => $attachment->mime_type]
        );
    }
}
