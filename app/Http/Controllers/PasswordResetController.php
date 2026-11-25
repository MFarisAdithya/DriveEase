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
    /**
     * Show the forgot-password form where a guest can request a reset link.
     */
    public function requestLink(): View
    {
        return view('auth.password-reset');
    }

    /**
     * Send a password reset link if the account exists and is active.
     */
    public function sendLink(Request $request): RedirectResponse
    {
        $credentials = $request->validate(['email' => ['required', 'email', 'max:255']]);

        // Str::lower() dan trim() menormalkan huruf kecil serta spasi email
        // sebelum dicocokkan ke tabel users agar konsisten dengan login dan registrasi.
        $email = Str::lower(trim($credentials['email']));

        // whereNotNull('deactivated_at') memeriksa apakah akun sudah dinonaktifkan admin.
        // exists() hanya mengecek keberadaan baris tanpa mengambil seluruh kolom model.
        // Jika akun nonaktif, pengiriman tautan dilewati tetapi respons tetap netral
        // supaya pihak luar tidak bisa menebak status keaktifan sebuah alamat email.
        $isDeactivated = User::where('email', $email)
            ->whereNotNull('deactivated_at')
            ->exists();

        if (! $isDeactivated) {
            // Password::sendResetLink() mencari pengguna lewat broker Laravel, membuat
            // token bertenggat di tabel password_reset_tokens, lalu mengirim notifikasi.
            Password::sendResetLink(['email' => $email]);
        }

        return back()->with('success', 'Jika email terdaftar, tautan pemulihan akan dikirim.');
    }

    /**
     * Show the password reset form pre-populated with the token and email query.
     */
    public function edit(Request $request, string $token): View
    {
        return view('auth.password-reset', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    /**
     * Validate the reset token and update the active user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        $credentials['email'] = Str::lower(trim($credentials['email']));

        // Blokir akun nonaktif sekalipun memegang token reset yang dibuat sebelum
        // penonaktifan, agar admin tetap memegang kendali penuh atas akses staf nonaktif.
        $isDeactivated = User::where('email', $credentials['email'])
            ->whereNotNull('deactivated_at')
            ->exists();

        if ($isDeactivated) {
            return back()->withInput($request->only('email'))->withErrors([
                'email' => 'Tautan pemulihan tidak valid atau sudah kedaluwarsa.',
            ]);
        }

        // Password::reset() memverifikasi kecocokan token dengan email serta masa berlakunya.
        // Closure dijalankan hanya jika token sah: password baru di-hash oleh cast model User,
        // remember token diacak ulang lewat Str::random(60) agar cookie lama gugur, lalu
        // event PasswordReset dipicu sebagai penanda pemulihan berhasil.
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
