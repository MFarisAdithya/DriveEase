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

        $base = User::whereHas('role', fn ($role) => $role->where('name', 'customer'));
        $pending = (clone $base)->where('verification_status', 'pending');
        $complete = "COALESCE(NULLIF(ktp_number, ''), nik_ktp, '') <> '' AND COALESCE(sim_number, '') <> '' AND COALESCE(ktp_image, '') <> '' AND COALESCE(sim_image, '') <> ''";
        $queues = [
            'all' => $base,
            'pending' => $pending,
            'ready' => (clone $pending)->whereRaw($complete),
            'incomplete' => (clone $pending)->whereRaw("NOT ($complete)"),
            'verified' => (clone $base)->where('verification_status', 'verified'),
            'rejected' => (clone $base)->where('verification_status', 'rejected'),
        ];
        $status = is_string($status) && isset($queues[$status]) ? $status : 'all';
        $counts = array_map(fn ($query) => $query->count(), $queues);
        $customers = $queues[$status]->latest('updated_at')->orderByDesc('id')->paginate(15)->withQueryString();

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

        if (! $user->hasSubmittedDocuments()) {
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
