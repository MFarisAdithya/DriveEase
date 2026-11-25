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
            <a href="{{ route('password.request') }}" class="text-sm text-ink underline">Lupa kata sandi?</a>

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
                    <input type="password" 
                           id="password" 
                           name="password" 
                           required 
                           placeholder="••••••••"
                           class="w-full px-3.5 py-2.5 rounded-lg border {{ $errors->has('password') ? 'border-danger-line focus:ring-danger-line' : 'border-field-line focus:border-focus focus:ring-focus' }} text-sm focus:outline-none focus:ring-2 transition-colors">
                    @error('password')
                        <p class="mt-1.5 text-xs text-danger font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Remember Me -->
                <div class="flex items-center justify-between text-xs pt-1">
                    <label class="flex items-center gap-2 cursor-pointer text-muted select-none">
                        <input type="checkbox" name="remember" class="rounded border-field-line text-link focus:ring-focus">
                        <span>Ingat saya di perangkat ini</span>
                    </label>
                </div>

                <!-- Submit Button -->
                <div class="pt-2">
                    <button type="submit" class="w-full py-2.5 px-4 bg-action hover:bg-action-hover text-ink font-semibold text-sm rounded-lg shadow-sm hover:shadow transition-colors">
                        Masuk ke Dasbor
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

        @if (app()->environment(['local', 'testing']))
        <!-- Demo Accounts Helper -->
        <div class="mt-6 bg-surface-hover/80 rounded-xl border border-field-line/80 p-4 text-xs text-subtle">
            <p class="font-semibold text-subtle mb-2 flex items-center gap-1.5">
                <svg class="w-4 h-4 text-link" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Akun Demo Uji Coba (Kata sandi: password123)
            </p>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 mt-2">
                <button type="button" 
                        onclick="document.getElementById('email').value='admin@driveease.test'; document.getElementById('password').value='password123';"
                        class="p-2 bg-navigation/60 rounded border border-field-line hover:border-focus text-left transition-colors">
                    <span class="block font-medium text-link">Admin</span>
                    <span class="text-[11px] text-muted truncate block">admin@driveease.test</span>
                </button>
                <button type="button" 
                        onclick="document.getElementById('email').value='staff@driveease.test'; document.getElementById('password').value='password123';"
                        class="p-2 bg-navigation/60 rounded border border-field-line hover:border-focus text-left transition-colors">
                    <span class="block font-medium text-link">Staf</span>
                    <span class="text-[11px] text-muted truncate block">staff@driveease.test</span>
                </button>
                <button type="button" 
                        onclick="document.getElementById('email').value='customer@driveease.test'; document.getElementById('password').value='password123';"
                        class="p-2 bg-navigation/60 rounded border border-field-line hover:border-focus text-left transition-colors">
                    <span class="block font-medium text-link">Pelanggan</span>
                    <span class="text-[11px] text-muted truncate block">customer@driveease.test</span>
                </button>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
