<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VerificationController extends Controller
{
    /**
     * Display list of customer verifications.
     */
    public function index(Request $request): View
    {
        $status = $request->query('status');

        $query = User::whereHas('role', fn($q) => $q->where('name', 'customer'))
            ->with(['verifier']);

        if ($status && in_array($status, ['pending', 'verified', 'rejected'], true)) {
            $query->where('verification_status', $status);
        }

        // Prioritize pending verifications, then newest
        $customers = $query->orderByRaw("CASE WHEN verification_status = 'pending' THEN 0 ELSE 1 END")
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString();

        $counts = [
            'all' => User::whereHas('role', fn($q) => $q->where('name', 'customer'))->count(),
            'pending' => User::whereHas('role', fn($q) => $q->where('name', 'customer'))->where('verification_status', 'pending')->count(),
            'verified' => User::whereHas('role', fn($q) => $q->where('name', 'customer'))->where('verification_status', 'verified')->count(),
            'rejected' => User::whereHas('role', fn($q) => $q->where('name', 'customer'))->where('verification_status', 'rejected')->count(),
        ];

        return view('admin.verifications.index', compact('customers', 'counts', 'status'));
    }

    /**
     * Display customer identity detail.
     */
    public function show(User $user): View
    {
        abort_unless($user->isCustomer(), 404, 'Customer tidak ditemukan.');

        $user->load(['verifier', 'role']);

        return view('admin.verifications.show', compact('user'));
    }

    /**
     * Approve customer verification.
     */
    public function approve(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->isCustomer(), 404, 'Customer tidak ditemukan.');

        if (!$user->hasSubmittedDocuments()) {
            return back()->with('error', 'Dokumen customer belum lengkap untuk dapat disetujui.');
        }

        $user->verification_status = 'verified';
        $user->verified_at = now();
        $user->verified_by = auth()->id();
        $user->rejection_reason = null;
        $user->save();

        return redirect()->route('admin.verifications.show', $user)
            ->with('success', "Identitas customer {$user->name} berhasil diverifikasi dan disetujui.");
    }

    /**
     * Reject customer verification with reason.
     */
    public function reject(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->isCustomer(), 404, 'Customer tidak ditemukan.');

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'rejection_reason.required' => 'Alasan penolakan wajib diisi agar customer dapat memperbaiki dokumennya.',
            'rejection_reason.min' => 'Alasan penolakan minimal 5 karakter.',
            'rejection_reason.max' => 'Alasan penolakan maksimal 500 karakter.',
        ]);

        $user->verification_status = 'rejected';
        $user->rejection_reason = $validated['rejection_reason'];
        $user->verified_at = null;
        $user->verified_by = null;
        $user->save();

        return redirect()->route('admin.verifications.show', $user)
            ->with('success', "Verifikasi customer {$user->name} berhasil ditolak.");
    }
}
