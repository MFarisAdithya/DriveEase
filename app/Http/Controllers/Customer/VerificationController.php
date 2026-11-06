<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
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

        $hasKtpFile = $user->ktp_image || session('temp_ktp_image') || $request->hasFile('ktp_image');
        $hasSimFile = $user->sim_image || session('temp_sim_image') || $request->hasFile('sim_image');

        $rules = [
            'ktp_number' => ['required', 'digits:16'],
            'sim_number' => ['required', 'digits:14'],
            'ktp_image' => [$hasKtpFile ? 'nullable' : 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'sim_image' => [$hasSimFile ? 'nullable' : 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];

        $messages = [
            'ktp_number.required' => 'Nomor KTP / NIK wajib diisi.',
            'ktp_number.digits' => 'Nomor KTP harus tepat 16 digit angka.',
            'sim_number.required' => 'Nomor SIM wajib diisi.',
            'sim_number.digits' => 'Nomor SIM harus tepat 14 digit angka.',
            'sim_number.min' => 'Nomor SIM harus tepat 14 digit angka.',
            'sim_number.max' => 'Nomor SIM harus tepat 14 digit angka.',
            'ktp_image.required' => 'Foto KTP asli wajib diunggah.',
            'ktp_image.image' => 'Berkas foto KTP harus berupa gambar.',
            'ktp_image.mimes' => 'Format foto KTP harus berupa JPG, JPEG, PNG, atau WEBP.',
            'ktp_image.max' => 'Ukuran file foto KTP maksimal 2 MB.',
            'sim_image.required' => 'Foto SIM asli wajib diunggah.',
            'sim_image.image' => 'Berkas foto SIM harus berupa gambar.',
            'sim_image.mimes' => 'Format foto SIM harus berupa JPG, JPEG, PNG, atau WEBP.',
            'sim_image.max' => 'Ukuran file foto SIM maksimal 2 MB.',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        // Preserve uploaded files into temporary private storage so they are not lost if validation fails
        if ($request->hasFile('ktp_image') && $request->file('ktp_image')->isValid()) {
            if (!$validator->errors()->has('ktp_image')) {
                $tempKtp = $request->file('ktp_image')->store('identities_temp', 'local');
                session([
                    'temp_ktp_image' => $tempKtp,
                    'temp_ktp_image_name' => $request->file('ktp_image')->getClientOriginalName(),
                ]);
            }
        }

        if ($request->hasFile('sim_image') && $request->file('sim_image')->isValid()) {
            if (!$validator->errors()->has('sim_image')) {
                $tempSim = $request->file('sim_image')->store('identities_temp', 'local');
                session([
                    'temp_sim_image' => $tempSim,
                    'temp_sim_image_name' => $request->file('sim_image')->getClientOriginalName(),
                ]);
            }
        }

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        // Commit KTP image (from new upload or preserved temporary upload)
        if ($request->hasFile('ktp_image')) {
            if ($user->ktp_image && Storage::disk('local')->exists($user->ktp_image)) {
                Storage::disk('local')->delete($user->ktp_image);
            }
            $user->ktp_image = $request->file('ktp_image')->store('identities', 'local');
            session()->forget(['temp_ktp_image', 'temp_ktp_image_name']);
        } elseif (session('temp_ktp_image') && Storage::disk('local')->exists(session('temp_ktp_image'))) {
            if ($user->ktp_image && Storage::disk('local')->exists($user->ktp_image)) {
                Storage::disk('local')->delete($user->ktp_image);
            }
            $permanentKtp = 'identities/' . basename(session('temp_ktp_image'));
            Storage::disk('local')->move(session('temp_ktp_image'), $permanentKtp);
            $user->ktp_image = $permanentKtp;
            session()->forget(['temp_ktp_image', 'temp_ktp_image_name']);
        }

        // Commit SIM image (from new upload or preserved temporary upload)
        if ($request->hasFile('sim_image')) {
            if ($user->sim_image && Storage::disk('local')->exists($user->sim_image)) {
                Storage::disk('local')->delete($user->sim_image);
            }
            $user->sim_image = $request->file('sim_image')->store('identities', 'local');
            session()->forget(['temp_sim_image', 'temp_sim_image_name']);
        } elseif (session('temp_sim_image') && Storage::disk('local')->exists(session('temp_sim_image'))) {
            if ($user->sim_image && Storage::disk('local')->exists($user->sim_image)) {
                Storage::disk('local')->delete($user->sim_image);
            }
            $permanentSim = 'identities/' . basename(session('temp_sim_image'));
            Storage::disk('local')->move(session('temp_sim_image'), $permanentSim);
            $user->sim_image = $permanentSim;
            session()->forget(['temp_sim_image', 'temp_sim_image_name']);
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
