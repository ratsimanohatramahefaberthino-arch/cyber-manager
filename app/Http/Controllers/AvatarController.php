<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AvatarController extends Controller
{
    public function show(User $user): StreamedResponse
    {
        abort_if(!$user->avatar, 404);

        $disk = Storage::disk('public');
        abort_if(!$disk->exists($user->avatar), 404);

        $mime = $disk->mimeType($user->avatar) ?? 'image/jpeg';

        return $disk->response($user->avatar, basename($user->avatar), [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=31536000',
        ]);
    }
}