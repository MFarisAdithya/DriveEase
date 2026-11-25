<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function edit(Request $request): View
    {
        return view('auth.account', ['account' => $request->user()]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $account = $request->user();

        // Rule::unique() mencari email yang sama di tabel users. ignore() mengecualikan
        // ID akun yang sudah ditentukan server dari sesi. fill() hanya menerima data
        // hasil validasi, sehingga formulir ini mengubah profil akun yang sedang masuk.
        $email = $request->input('email');
        if (is_string($email)) {
            $request->merge(['email' => Str::lower(trim($email))]);
        }

        $profile = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($account)],
            // Pelanggan memberikan nomor kontak sejak registrasi. nullable
            // membolehkan admin dan staf menyimpan profil dengan nomor kosong.
            'phone' => [$account->isCustomer() ? 'required' : 'nullable', 'string', 'max:25'],
        ]);
        $account->fill($profile)->save();

        return back()->with('success', 'Profil berhasil diperbarui.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        // current_password membandingkan isian dengan hash akun pada sesi saat ini.
        // confirmed memeriksa kecocokan dengan password_confirmation. Cast pada model
        // mengubah kata sandi baru menjadi hash saat nilai diisikan ke atribut password.
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $account = $request->user();
        $account->password = $validated['password'];
        $account->setRememberToken(Str::random(60));
        $account->save();
        $request->session()->regenerate();

        return back()->with('success', 'Kata sandi berhasil diperbarui.');
    }
}
