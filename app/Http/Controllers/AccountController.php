<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AccountController extends Controller
{
    /**
     * Show the account profile and password management page for the signed-in user.
     */
    public function edit(Request $request): View
    {
        // $request->user() mengambil instance model User yang sedang terautentikasi di sesi.
        // view() mengirim objek tersebut sebagai variabel $account ke resources/views/auth/account.blade.php.
        return view('auth.account', ['account' => $request->user()]);
    }

    /**
     * Update the signed-in user's profile attributes without exposing role or verification fields.
     */
    public function updateProfile(Request $request): RedirectResponse
    {
        $account = $request->user();

        // is_string() memastikan input email benar-benar teks sebelum dinormalisasi.
        // Str::lower(trim(...)) menyeragamkan email ke huruf kecil dan membuang spasi tepi
        // supaya Rule::unique() menolak duplikasi email yang hanya berbeda huruf kapital.
        $email = $request->input('email');
        if (is_string($email)) {
            $request->merge(['email' => Str::lower(trim($email))]);
        }

        // validate() hanya mengembalikan field yang terdaftar di array aturan ini,
        // sehingga field tambahan seperti role_id atau verification_status diabaikan.
        // Rule::unique('users')->ignore($account) mengecualikan baris akun saat ini,
        // sedangkan 'sometimes' membuat kolom address hanya divalidasi dan diubah jika dikirim.
        $profile = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($account)],
            // Pelanggan wajib memiliki nomor telepon aktif sejak registrasi, sedangkan
            // admin dan staf boleh mengosongkannya dengan aturan nullable.
            'phone' => [$account->isCustomer() ? 'required' : 'nullable', 'string', 'max:25'],
            'address' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        // Method chaining fill($profile)->save() mengisi atribut hasil validasi ke model
        // sesuai $fillable lalu menjalankan query UPDATE ke tabel users.
        $account->fill($profile)->save();

        return back()->with('success', 'Profil berhasil diperbarui.');
    }

    /**
     * Verify current password and rotate the signed-in user's password and session ID.
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        // Aturan 'current_password' membandingkan input dengan hash kata sandi akun pada sesi aktif.
        // Aturan 'confirmed' mewajibkan adanya input password_confirmation yang nilainya sama.
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $account = $request->user();
        // Cast 'hashed' pada model User otomatis meng-hash string $validated['password'].
        // setRememberToken() mengganti token ingat-saya, lalu save() menyimpan perubahan.
        $account->password = $validated['password'];
        $account->setRememberToken(Str::random(60));
        $account->save();

        // regenerate() mengganti ID sesi setelah perubahan kredensial demi mencegah session fixation.
        $request->session()->regenerate();

        return back()->with('success', 'Kata sandi berhasil diperbarui.');
    }
}
