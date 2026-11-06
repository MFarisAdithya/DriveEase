<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class VerificationController extends Controller
{
    /**
     * Show customer identity verification page.
     */
    public function create(): View|RedirectResponse
    {
        $user = auth()->user();

        return view('customer.verification', compact('user'));
    }

    /**
     * Store or update customer identity documents.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = auth()->user();

        // If user is already verified, do not allow resubmission
        if ($user->isVerified()) {
            return redirect()->route('customer.dashboard')
                ->with('error', 'Akun Anda sudah terverifikasi.');
        }

        $rules = [
            'ktp_number' => ['required', 'digits:16'],
            'sim_number' => ['required', 'string', 'min:5', 'max:30'],
            'ktp_image' => [$user->ktp_image ? 'nullable' : 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'sim_image' => [$user->sim_image ? 'nullable' : 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];

        $messages = [
            'ktp_number.required' => 'Nomor KTP / NIK wajib diisi.',
            'ktp_number.digits' => 'Nomor KTP harus tepat 16 digit angka.',
            'sim_number.required' => 'Nomor SIM wajib diisi.',
            'ktp_image.required' => 'Foto KTP wajib diunggah.',
            'ktp_image.image' => 'File KTP harus berupa gambar.',
            'ktp_image.mimes' => 'Format foto KTP harus berupa JPG, JPEG, PNG, atau WEBP.',
            'ktp_image.max' => 'Ukuran file foto KTP maksimal 2 MB.',
            'sim_image.required' => 'Foto SIM wajib diunggah.',
            'sim_image.image' => 'File SIM harus berupa gambar.',
            'sim_image.mimes' => 'Format foto SIM harus berupa JPG, JPEG, PNG, atau WEBP.',
            'sim_image.max' => 'Ukuran file foto SIM maksimal 2 MB.',
        ];

        $validated = $request->validate($rules, $messages);

        // Store KTP image privately
        if ($request->hasFile('ktp_image')) {
            if ($user->ktp_image && Storage::disk('local')->exists($user->ktp_image)) {
                Storage::disk('local')->delete($user->ktp_image);
            }
            $user->ktp_image = $request->file('ktp_image')->store('identities', 'local');
        }

        // Store SIM image privately
        if ($request->hasFile('sim_image')) {
            if ($user->sim_image && Storage::disk('local')->exists($user->sim_image)) {
                Storage::disk('local')->delete($user->sim_image);
            }
            $user->sim_image = $request->file('sim_image')->store('identities', 'local');
        }

        $user->ktp_number = $validated['ktp_number'];
        $user->nik_ktp = $validated['ktp_number'];
        $user->sim_number = $validated['sim_number'];
        $user->verification_status = 'pending';
        $user->rejection_reason = null;
        $user->verified_at = null;
        $user->verified_by = null;
        $user->save();

        return redirect()->route('customer.dashboard')
            ->with('success', 'Dokumen identitas berhasil dikirim dan sedang menunggu verifikasi dari tim admin.');
    }
}
