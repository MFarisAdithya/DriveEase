@extends('layouts.app')

@section('title', 'Masuk ke Sistem - DriveEase')

@section('body')
<div class="relative min-h-screen flex items-center justify-center px-4 pt-10 pb-36 sm:pb-44 bg-canvas overflow-hidden">
    @include('partials.driveease-backdrop')

    <div class="relative z-10 w-full max-w-md">
        <!-- Logo -->
        <div class="text-center mb-6">
            <a href="{{ route('home') }}" class="inline-flex justify-center focus:outline-none">
                @include('partials.driveease-logo', [
                    'sizeClass' => 'h-16 sm:h-[78px]',
                    'haloSize' => 'w-16 h-16 sm:w-20 sm:h-20',
                ])
            </a>
        </div>

        <!-- Login Card -->
        <div class="bg-white/95 backdrop-blur-md rounded-3xl shadow-[0_24px_60px_rgba(11,40,64,0.09)] border border-[#C6DFEE] p-8">
            <h2 class="text-lg font-bold text-ink mb-6">Masuk ke Akun Anda</h2>

            @if (session('success'))
                <div class="mb-5 p-3 rounded-lg bg-success-surface border border-success-line text-xs text-success">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('error'))
                <div class="mb-5 p-3 rounded-lg bg-danger-surface border border-danger-line text-xs text-danger">
                    {{ session('error') }}
                </div>
            @endif

            <form action="{{ route('login') }}" method="POST" class="space-y-4">
                @csrf

                <!-- Email Field -->
                <div>
                    <label for="email" class="block text-xs font-semibold text-ink uppercase tracking-wider mb-1.5">
                        Alamat Email
                    </label>
                    <input type="email" 
                           id="email" 
                           name="email" 
                           value="{{ old('email') }}" 
                           required 
                           autofocus
                           placeholder="nama@email.com"
                           class="w-full px-3.5 py-2.5 rounded-lg border {{ $errors->has('email') ? 'border-danger-line focus:ring-danger-line' : 'border-field-line focus:border-focus focus:ring-focus' }} text-sm focus:outline-none focus:ring-2 transition-colors">
                    @error('email')
                        <p class="mt-1.5 text-xs text-danger font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Password Field -->
                <div>
                    <label for="password" class="block text-xs font-semibold text-ink uppercase tracking-wider mb-1.5">
                        Kata Sandi
                    </label>
                    <div class="relative">
                        <input type="password"
                               id="password"
                               name="password"
                               required
                               autocomplete="current-password"
                               placeholder="••••••••"
                               class="w-full pl-3.5 pr-11 py-2.5 rounded-lg border {{ $errors->has('password') ? 'border-danger-line focus:ring-danger-line' : 'border-field-line focus:border-focus focus:ring-focus' }} text-sm focus:outline-none focus:ring-2 transition-colors">
                        <button type="button"
                                id="toggle-password"
                                aria-label="Tampilkan kata sandi"
                                aria-pressed="false"
                                aria-controls="password"
                                style="translate: none;"
                                class="absolute inset-y-0 right-0 flex items-center justify-center w-11 text-muted hover:text-ink transition-colors duration-150 ease-out cursor-pointer rounded-r-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus">
                            {{-- Ikon mata terbuka tampil saat kata sandi masih disembunyikan (type="password"). --}}
                            <svg id="icon-eye-show" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            {{-- Ikon mata tercoret tampil saat kata sandi sedang diperlihatkan (type="text"). --}}
                            <svg id="icon-eye-hide" class="w-5 h-5 hidden" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"/>
                            </svg>
                        </button>
                    </div>
                    @error('password')
                        <p class="mt-1.5 text-xs text-danger font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Remember Me & Password Reset -->
                <div class="flex items-center justify-between text-xs pt-1">
                    <label class="flex items-center gap-2 cursor-pointer text-muted select-none">
                        <input type="checkbox" name="remember" class="rounded border-field-line text-link focus:ring-focus">
                        <span>Ingat saya di perangkat ini</span>
                    </label>
                    <a href="{{ route('password.request') }}" class="font-semibold text-link underline hover:text-focus">Lupa kata sandi?</a>
                </div>

                <!-- Submit Button -->
                <div class="pt-2">
                    <button type="submit" class="w-full py-2.5 px-4 bg-action hover:bg-action-hover text-ink font-semibold text-sm rounded-lg shadow-sm hover:shadow transition-colors">
                        Masuk
                    </button>
                </div>
            </form>

            <div class="mt-6 pt-4 border-t border-line text-center text-xs text-muted">
                Belum memiliki akun?
                <a href="{{ route('register') }}" class="font-semibold text-link hover:text-link ml-1">
                    Daftar Akun Baru
                </a>
            </div>
        </div>

    </div>
</div>

<style>
    /* Menyembunyikan tombol mata bawaan Microsoft Edge agar tidak tampil ganda di samping tombol kustom. */
    #password::-ms-reveal,
    #password::-ms-clear {
        display: none;
    }
</style>

<script>
    // document.getElementById() mengambil elemen DOM berdasarkan atribut id masing-masing.
    // Hasilnya disimpan ke konstanta agar mudah diakses saat tombol ditekan.
    const passwordInput = document.getElementById('password');
    const toggleButton = document.getElementById('toggle-password');
    const eyeShowIcon = document.getElementById('icon-eye-show');
    const eyeHideIcon = document.getElementById('icon-eye-hide');

    // Operator && memastikan keempat elemen benar-benar ditemukan sebelum memasang event listener.
    // addEventListener('click', ...) mendaftarkan fungsi yang dijalankan setiap tombol mata diklik.
    if (passwordInput && toggleButton && eyeShowIcon && eyeHideIcon) {
        toggleButton.addEventListener('click', function () {
            // Operator === membandingkan nilai dan tipe data.
            // isHidden bernilai true jika input saat ini masih bertipe 'password' (karakter tertutup).
            const isHidden = passwordInput.type === 'password';

            // Operator ternary (? :) memilih 'text' bila sedang tersembunyi, atau 'password' bila sedang terlihat.
            passwordInput.type = isHidden ? 'text' : 'password';

            // classList.toggle('hidden', kondisi) menambah class 'hidden' jika argumen kedua true dan menghapusnya jika false.
            // Saat kata sandi ditampilkan (isHidden = true), ikon mata terbuka disembunyikan dan ikon mata tercoret dimunculkan.
            eyeShowIcon.classList.toggle('hidden', isHidden);
            eyeHideIcon.classList.toggle('hidden', !isHidden);

            // setAttribute() memperbarui atribut aksesibilitas agar pembaca layar mengetahui status tombol dan labelnya.
            toggleButton.setAttribute('aria-pressed', String(isHidden));
            toggleButton.setAttribute('aria-label', isHidden ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
        });
    }
    // Dengan blok ini, pengguna dapat menekan ikon mata untuk menukar tampilan kata sandi antara tersembunyi dan terlihat beserta ikon dan atribut aksesibilitasnya.
</script>
@endsection
