<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Services\IdentityDocuments;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

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

        $documents = app(IdentityDocuments::class);
        $hasKtpFile = $documents->exists($user->ktp_image) || $documents->exists(session('temp_ktp_image')) || $request->hasFile('ktp_image');
        $hasSimFile = $documents->exists($user->sim_image) || $documents->exists(session('temp_sim_image')) || $request->hasFile('sim_image');

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

        try {
            foreach (['ktp', 'sim'] as $type) {
                $field = "{$type}_image";
                if ($request->hasFile($field) && ! $validator->errors()->has($field)) {
                    $path = $request->file($field)->store('identities_temp', 'local');
                    if (! $path || ! $documents->exists($path)) {
                        throw new RuntimeException('Gagal menyimpan unggahan sementara.');
                    }
                    $previous = session("temp_{$field}");
                    session(["temp_{$field}" => $path, "temp_{$field}_name" => $request->file($field)->getClientOriginalName()]);
                    $documents->delete($previous);
                }
            }
        } catch (Throwable $exception) {
            report($exception);

            return back()->withInput()->with('error', 'Unggahan belum tersimpan. Silakan coba kembali.');
        }

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            $documents->submit($user, $validator->validated());
        } catch (Throwable $exception) {
            report($exception);

            return back()->withInput()->with('error', 'Dokumen belum dapat disimpan. Data sebelumnya tetap aman. Silakan coba kembali.');
        }

        return redirect()->route('customer.dashboard')
            ->with('success', 'Dokumen identitas berhasil dikirim dan sedang menunggu verifikasi dari tim admin.');
    }
}
