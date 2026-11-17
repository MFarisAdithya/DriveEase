<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\VerificationQueue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class VerificationController extends Controller
{
    /**
     * Display list of customer verifications.
     */
    public function index(Request $request): View
    {
        $status = $request->query('status');

        $queues = app(VerificationQueue::class)->queries();
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
        abort_unless($user->isCustomer(), 404, 'Pelanggan tidak ditemukan.');

        $user->load(['verifier', 'role']);

        return view('admin.verifications.show', compact('user'));
    }

    /**
     * Approve customer verification.
     */
    public function approve(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->isCustomer(), 404, 'Pelanggan tidak ditemukan.');

        $approved = DB::transaction(function () use ($user): bool {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            if (! $locked->hasSubmittedDocuments()) {
                return false;
            }
            $locked->verification_status = 'verified';
            $locked->verified_at = now();
            $locked->verified_by = auth()->id();
            $locked->rejection_reason = null;
            $locked->saveOrFail();

            return true;
        });
        if (! $approved) {
            return back()->with('error', 'Dokumen pelanggan belum lengkap atau berkas tidak ditemukan.');
        }

        return redirect()->route('admin.verifications.show', $user)
            ->with('success', "Identitas pelanggan {$user->name} berhasil diverifikasi dan disetujui.");
    }

    /**
     * Reject customer verification with reason.
     */
    public function reject(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->isCustomer(), 404, 'Pelanggan tidak ditemukan.');

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'rejection_reason.required' => 'Alasan penolakan wajib diisi agar pelanggan dapat memperbaiki dokumennya.',
            'rejection_reason.min' => 'Alasan penolakan minimal 5 karakter.',
            'rejection_reason.max' => 'Alasan penolakan maksimal 500 karakter.',
        ]);

        $user->verification_status = 'rejected';
        $user->rejection_reason = $validated['rejection_reason'];
        $user->verified_at = null;
        $user->verified_by = null;
        $user->save();

        return redirect()->route('admin.verifications.show', $user)
            ->with('success', "Verifikasi pelanggan {$user->name} berhasil ditolak.");
    }
}
