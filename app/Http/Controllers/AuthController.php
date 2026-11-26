<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return $this->redirectBasedOnRole(Auth::user());
        }

        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $this->normalizeEmail($request);
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'password.required' => 'Kata sandi wajib diisi.',
        ]);

        $key = 'login:'.hash('sha256', $credentials['email'].'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Terlalu banyak percobaan masuk. Coba lagi dalam '.RateLimiter::availableIn($key).' detik.']);
        }
        RateLimiter::hit($key, 60);
        if (! app()->environment(['local', 'testing']) && in_array($credentials['email'], ['admin@driveease.test', 'staff@driveease.test', 'customer@driveease.test'], true)) {
            throw ValidationException::withMessages(['email' => 'Akun demo tidak tersedia pada lingkungan ini.']);
        }
        $remember = $request->boolean('remember');

        if (Auth::attempt($credentials + ['deactivated_at' => null], $remember)) {
            RateLimiter::clear($key);
            $request->session()->regenerate();

            return $this->redirectBasedOnRole(Auth::user())
                ->with('success', 'Selamat datang kembali, '.Auth::user()->name.'!');
        }

        return back()
            ->withInput($request->only('email', 'remember'))
            ->withErrors([
                'email' => 'Email atau kata sandi yang Anda masukkan salah.',
            ]);
    }

    public function showRegister(): View|RedirectResponse
    {
        if (Auth::check()) {
            return $this->redirectBasedOnRole(Auth::user());
        }

        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $this->normalizeEmail($request);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:25'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'name.required' => 'Nama lengkap wajib diisi.',
            'email.required' => 'Alamat email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Alamat email sudah terdaftar. Silakan gunakan email lain atau masuk ke akun Anda.',
            'phone.required' => 'Nomor telepon/WhatsApp wajib diisi.',
            'password.required' => 'Kata sandi wajib diisi.',
            'password.min' => 'Kata sandi minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
        ]);

        $customerRole = Role::firstOrCreate(
            ['name' => 'customer'],
            ['display_name' => 'Pelanggan']
        );

        $user = User::create([
            'role_id' => $customerRole->id,
            'name' => $validated['name'],
            'email' => strtolower($validated['email']),
            'phone' => $validated['phone'],
            'password' => Hash::make($validated['password']),
            'verification_status' => 'pending',
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return $this->redirectBasedOnRole($user)
            ->with('success', 'Pendaftaran berhasil! Selamat datang di DriveEase, '.$user->name.'.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')
            ->with('success', 'Anda telah berhasil keluar dari sistem.');
    }

    private function normalizeEmail(Request $request): void
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => mb_strtolower(trim($request->input('email')))]);
        }
    }

    protected function redirectBasedOnRole($user): RedirectResponse
    {
        // Middleware auth menyimpan halaman yang diminta sebelum login. Hanya rute
        // pemesanan lokal yang boleh dilanjutkan, termasuk setelah pendaftaran.
        // Bangun ulang URL dari pilihan yang valid agar parameter lain tidak terbawa.
        $intended = session()->pull('url.intended');
        if ($user->isCustomer() && is_string($intended)
            && strtok($intended, '?') === route('customer.bookings.create')) {
            parse_str((string) parse_url($intended, PHP_URL_QUERY), $selection);
            $validator = validator($selection, [
                'car_id' => ['required', 'integer', 'min:1'],
                'pickup_at' => ['required', 'date_format:Y-m-d\TH:i'],
                'total_days' => ['required', 'integer', 'between:1,365'],
            ]);
            if ($validator->passes()) {
                return redirect()->route('customer.bookings.create', $validator->validated());
            }
        }

        return match ($user->role?->name) {
            'admin' => redirect()->route('admin.dashboard'),
            'staff' => redirect()->route('staff.dashboard'),
            'customer' => redirect()->route('customer.dashboard'),
            default => redirect()->route('login'),
        };
    }
}
