<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocumentController extends Controller
{
    /**
     * Securely serve customer identity document from private storage.
     */
    public function showIdentity(Request $request, User $user, string $type): BinaryFileResponse|Response
    {
        // Must be authenticated
        $currentUser = $request->user();
        if (!$currentUser) {
            abort(403, 'Akses ditolak.');
        }

        // Authorization: Admin can view any customer document, customer can only view their own
        $isOwner = $currentUser->id === $user->id;
        $isAdmin = $currentUser->isAdmin();

        if (!$isAdmin && !$isOwner) {
            abort(403, 'Akses ditolak. Anda tidak berhak melihat dokumen ini.');
        }

        // Validate document type
        if (!in_array($type, ['ktp', 'sim'], true)) {
            abort(404, 'Tipe dokumen tidak valid.');
        }

        $imageField = $type === 'ktp' ? 'ktp_image' : 'sim_image';
        $path = $user->{$imageField};

        if ((!$path || !Storage::disk('local')->exists($path)) && $isOwner && session("temp_{$type}_image")) {
            $path = session("temp_{$type}_image");
        }

        if (!$path || !Storage::disk('local')->exists($path)) {
            abort(404, 'Dokumen identitas tidak ditemukan atau belum diunggah.');
        }

        $fullPath = Storage::disk('local')->path($path);
        $mimeType = Storage::disk('local')->mimeType($path) ?? 'image/jpeg';

        $response = response()->file($fullPath, [
            'Content-Type' => $mimeType,
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);

        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');

        return $response;
    }
}
