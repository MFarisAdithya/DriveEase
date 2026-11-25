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
        // ?-> menghasilkan null jika pengunjung belum masuk. Akun nonaktif
        // memiliki waktu penonaktifan, sehingga sesi yang masih terbuka diakhiri
        // sebelum permintaan diteruskan ke controller.
        if ($request->user()?->deactivated_at !== null) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'Akun ini telah dinonaktifkan.']);
        }

        return $next($request);
    }
}
