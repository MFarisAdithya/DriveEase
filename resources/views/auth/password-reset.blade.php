@extends('layouts.app')

@section('title', 'Pemulihan Akun - DriveEase')

@section('body')
<main class="relative min-h-screen flex items-center justify-center bg-canvas px-4 pt-10 pb-36 sm:pb-44 overflow-hidden">
    @include('partials.driveease-backdrop')
    <div class="relative z-10 w-full max-w-md space-y-6">
        <div class="text-center">
            <a href="{{ route('home') }}" class="inline-flex justify-center focus:outline-none">
                @include('partials.driveease-logo', [
                    'sizeClass' => 'h-16 sm:h-[78px]',
                    'haloSize' => 'w-16 h-16 sm:w-20 sm:h-20',
                ])
            </a>
        </div>

        <div class="rounded-3xl border border-[#C6DFEE] bg-white/95 p-8 shadow-[0_24px_60px_rgba(11,40,64,0.09)] backdrop-blur-md">
            <h1 class="mb-2 text-lg font-bold text-ink">{{ isset($token) ? 'Buat Kata Sandi Baru' : 'Lupa Kata Sandi' }}</h1>
            <p class="mb-5 text-xs text-muted">
                {{ isset($token) ? 'Masukkan kata sandi baru minimal 8 karakter untuk memulihkan akun Anda.' : 'Masukkan alamat email terdaftar untuk menerima tautan pemulihan kata sandi.' }}
            </p>

            @if (session('success'))
                <p role="status" class="mb-4 rounded-lg border border-success-line bg-success-surface p-3 text-xs font-medium text-success">{{ session('success') }}</p>
            @endif
            @if ($errors->any())
                <ul role="alert" class="mb-4 rounded-lg border border-danger-line bg-danger-surface p-3 text-xs text-danger">
                    @foreach ($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            @endif

            {{-- isset() membedakan formulir permintaan email dan penggantian kata sandi
                 dari keberadaan token yang dikirim controller. @csrf menyisipkan token
                 sesi agar Laravel dapat memeriksa asal pengiriman formulir. --}}
            <form method="POST" action="{{ route(isset($token) ? 'password.update' : 'password.email') }}" class="space-y-4">
                @csrf
                <div>
                    <label for="email" class="block text-xs font-semibold text-ink uppercase tracking-wider mb-1.5">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email', is_string($email ?? null) ? $email : '') }}" autocomplete="email" required class="w-full rounded-lg border {{ $errors->has('email') ? 'border-danger-line' : 'border-field-line' }} p-2.5 text-sm">
                    @error('email')
                        <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                    @enderror
                </div>
                @isset($token)
                    <input type="hidden" name="token" value="{{ $token }}">
                    <div>
                        <label for="password" class="block text-xs font-semibold text-ink uppercase tracking-wider mb-1.5">Kata sandi baru</label>
                        <input id="password" name="password" type="password" autocomplete="new-password" minlength="8" required class="w-full rounded-lg border {{ $errors->has('password') ? 'border-danger-line' : 'border-field-line' }} p-2.5 text-sm">
                        @error('password')
                            <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="password_confirmation" class="block text-xs font-semibold text-ink uppercase tracking-wider mb-1.5">Ulangi kata sandi baru</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required class="w-full rounded-lg border border-field-line p-2.5 text-sm">
                    </div>
                @endisset
                <button type="submit" class="w-full rounded-lg bg-action hover:bg-action-hover p-3 text-sm font-semibold text-ink shadow-sm transition-colors">{{ isset($token) ? 'Simpan Kata Sandi' : 'Kirim Tautan Pemulihan' }}</button>
            </form>
            <div class="mt-5 border-t border-line pt-4 text-center">
                <a href="{{ route('login') }}" class="text-xs font-semibold text-link underline">Kembali ke halaman masuk</a>
            </div>
        </div>
    </div>
</main>
@endsection
