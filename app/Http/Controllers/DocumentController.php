<?php

namespace App\Http\Controllers;

use App\Models\Inspection;
use App\Models\Rental;
use App\Models\RentalExtension;
use App\Models\RentalPayment;
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
        if (! $currentUser) {
            abort(403, 'Akses ditolak.');
        }

        // Authorization: Admin can view any customer document, customer can only view their own
        $isOwner = $currentUser->id === $user->id;
        $isAdmin = $currentUser->isAdmin();

        if (! $isAdmin && ! $isOwner) {
            abort(403, 'Akses ditolak. Anda tidak berhak melihat dokumen ini.');
        }

        // Validate document type
        if (! in_array($type, ['ktp', 'sim'], true)) {
            abort(404, 'Tipe dokumen tidak valid.');
        }

        $imageField = $type === 'ktp' ? 'ktp_image' : 'sim_image';
        $path = $user->{$imageField};

        if ((! $path || ! Storage::disk('local')->exists($path)) && $isOwner && session("temp_{$type}_image")) {
            $path = session("temp_{$type}_image");
        }

        if (! $path || ! Storage::disk('local')->exists($path)) {
            abort(404, 'Dokumen identitas tidak ditemukan atau belum diunggah.');
        }

        return $this->streamPrivateFile($path);
    }

    /**
     * Securely serve a booking payment proof from private local storage (NFR-01).
     */
    public function showPaymentProof(Request $request, RentalPayment $payment): BinaryFileResponse|Response
    {
        $payment->loadMissing('rental');

        return $this->streamPrivateDocumentForOwnerOrAdmin(
            $request,
            (int) $payment->rental?->user_id,
            $payment->proof_path,
            'Bukti pembayaran tidak ditemukan.'
        );
    }

    /**
     * Securely serve an extension payment proof from private local storage (NFR-01).
     */
    public function showExtensionPaymentProof(Request $request, RentalExtension $extension): BinaryFileResponse|Response
    {
        $extension->loadMissing('rental');

        return $this->streamPrivateDocumentForOwnerOrAdmin(
            $request,
            (int) $extension->rental?->user_id,
            $extension->payment_proof,
            'Bukti pembayaran perpanjangan tidak ditemukan.'
        );
    }

    /**
     * Sajikan foto bukti pembayaran tagihan tambahan secara privat hanya untuk pemilik atau admin (NFR-01, C-10, A-09).
     */
    public function showAdditionalChargeProof(Request $request, Rental $rental): BinaryFileResponse|Response
    {
        return $this->streamPrivateDocumentForOwnerOrAdmin(
            $request,
            (int) $rental->user_id,
            $rental->additional_charge_proof,
            'Bukti pembayaran tagihan tambahan tidak ditemukan.'
        );
    }

    /**
     * Sajikan foto kondisi inspeksi awal/akhir secara privat untuk admin, staf operasional, atau pemilik transaksi (NFR-01, S-03, S-04).
     */
    public function showInspectionPhoto(Request $request, Inspection $inspection): BinaryFileResponse|Response
    {
        $inspection->loadMissing('rental');

        return $this->streamPrivateDocumentForOwnerOrStaffOrAdmin(
            $request,
            (int) $inspection->rental?->user_id,
            $inspection->condition_photo,
            'Foto kondisi inspeksi kendaraan tidak ditemukan.'
        );
    }

    /**
     * Sajikan bukti pendukung kerusakan kendaraan secara privat untuk admin, staf operasional, atau pemilik transaksi (NFR-01, A-09).
     */
    public function showDamageEvidence(Request $request, Rental $rental): BinaryFileResponse|Response
    {
        return $this->streamPrivateDocumentForOwnerOrStaffOrAdmin(
            $request,
            (int) $rental->user_id,
            $rental->damage_evidence_path,
            'Foto bukti kerusakan kendaraan tidak ditemukan.'
        );
    }

    /**
     * Enforce owner-or-admin authorization and stream a file with no-store cache headers.
     */
    private function streamPrivateDocumentForOwnerOrAdmin(
        Request $request,
        int $ownerUserId,
        ?string $path,
        string $missingMessage
    ): BinaryFileResponse|Response {
        $currentUser = $request->user();
        if (! $currentUser) {
            abort(403, 'Akses ditolak.');
        }

        // Hanya pemilik transaksi dan admin berwenang yang diizinkan membaca bukti pembayaran.
        // Staf lapangan maupun pelanggan lain langsung ditolak dengan HTTP 403.
        $isOwner = $currentUser->id === $ownerUserId;
        $isAdmin = $currentUser->isAdmin();

        if (! $isAdmin && ! $isOwner) {
            abort(403, 'Akses ditolak. Anda tidak berhak melihat dokumen pembayaran ini.');
        }

        if (! $path || ! Storage::disk('local')->exists($path)) {
            abort(404, $missingMessage);
        }

        return $this->streamPrivateFile($path);
    }

    /**
     * Terapkan otorisasi foto fisik operasional untuk pemilik pesanan, staf lapangan, dan admin.
     */
    private function streamPrivateDocumentForOwnerOrStaffOrAdmin(
        Request $request,
        int $ownerUserId,
        ?string $path,
        string $missingMessage
    ): BinaryFileResponse|Response {
        $currentUser = $request->user();
        if (! $currentUser) {
            abort(403, 'Akses ditolak.');
        }

        $authorized = $currentUser->isAdmin()
            || $currentUser->isStaff()
            || $currentUser->id === $ownerUserId;

        if (! $authorized) {
            abort(403, 'Akses ditolak. Anda tidak berhak melihat dokumen inspeksi ini.');
        }

        if (! $path || ! Storage::disk('local')->exists($path)) {
            abort(404, $missingMessage);
        }

        return $this->streamPrivateFile($path);
    }

    /**
     * Alirkan berkas privat dengan header no-store agar peramban tidak menyimpan salinan cache.
     */
    private function streamPrivateFile(string $path): BinaryFileResponse|Response
    {
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
