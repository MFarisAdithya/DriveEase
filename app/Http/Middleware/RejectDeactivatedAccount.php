<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RejectDeactivatedAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        // Operator ?-> (nullsafe) memanggil method isDeactivated() hanya jika
        // $request->user() tidak bernilai null (pengguna sudah masuk melalui sesi).
        // Perbandingan === true memastikan tamu (null) tidak dianggap nonaktif.
        // Jika akun berstatus nonaktif, Auth::logout() menghapus status login,
        // invalidate() menghapus data sesi lama, dan regenerateToken() membuat
        // token CSRF baru sebelum mengalihkan ke halaman login.
        if ($request->user()?->isDeactivated() === true) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Akun ini telah dinonaktifkan.',
            ]);
        }

        // $next($request) meneruskan permintaan ke middleware berikutnya atau controller
        // apabila pengguna masih aktif atau berstatus tamu.
        return $next($request);
    }
}
