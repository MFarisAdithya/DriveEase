<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    public function requestLink(): View
    {
        return view('auth.password-reset');
    }

    public function sendLink(Request $request): RedirectResponse
    {
        $credentials = $request->validate(['email' => ['required', 'email', 'max:255']]);

        // Password adalah facade untuk broker reset Laravel. Broker mencari pengguna,
        // membuat token bertenggat, lalu mengirim tautan melalui notifikasi.
        // Respons yang sama untuk setiap alamat menjaga kerahasiaan daftar akun.
        Password::sendResetLink(['email' => Str::lower(trim($credentials['email']))]);

        return back()->with('success', 'Jika email terdaftar, tautan pemulihan akan dikirim.');
    }

    public function edit(Request $request, string $token): View
    {
        return view('auth.password-reset', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        $credentials['email'] = Str::lower(trim($credentials['email']));

        // reset() memeriksa apakah token cocok dengan email dan masih berlaku.
        // Closure adalah fungsi yang dijalankan broker ketika pemeriksaan berhasil.
        // Model User melakukan hashing saat password disimpan. Token remember
        // diperbarui agar cookie ingat-saya yang lama tidak dapat digunakan kembali.
        // Dengan demikian, hanya pemegang tautan yang sah dapat mengganti password.
        $status = Password::reset($credentials, function (User $user, string $password): void {
            $user->password = $password;
            $user->setRememberToken(Str::random(60));
            $user->save();

            event(new PasswordReset($user));
        });

        if ($status !== Password::PasswordReset) {
            return back()->withInput($request->only('email'))->withErrors([
                'email' => 'Tautan pemulihan tidak valid atau sudah kedaluwarsa.',
            ]);
        }

        return redirect()->route('login')->with('success', 'Kata sandi berhasil diperbarui. Silakan masuk.');
    }
}
