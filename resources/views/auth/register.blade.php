@extends('layouts.app')

@section('title', 'Daftar Akun Baru - DriveEase')

@section('body')
<div class="min-h-screen flex items-center justify-center p-4 bg-canvas py-12">
    <div class="w-full max-w-md">
        <!-- Logo & Header -->
        <div class="text-center mb-8">
            <a href="{{ route('cars.index') }}" class="brand-lockup mx-auto"><img src="{{ asset('images/driveease-logo.png') }}" alt="DriveEase" width="220" height="72"></a>
            <h1 class="text-2xl font-bold text-ink tracking-tight">Daftar Akun Pelanggan</h1>
            <p class="text-sm text-muted mt-1">Bergabung bersama DriveEase untuk kemudahan sewa armada</p>
        </div>

        <!-- Register Card -->
        <div class="bg-surface rounded-2xl shadow-xl border border-line p-8">
            <form action="{{ route('register') }}" method="POST" class="space-y-4">
                @csrf

                <!-- Name Field -->
                <div>
                    <label for="name" class="block text-xs font-semibold text-ink uppercase tracking-wider mb-1.5">
                        Nama Lengkap <span class="text-danger">*</span>
                    </label>
                    <input type="text" 
                           id="name" 
                           name="name" 
                           value="{{ old('name') }}" 
                           required 
                           autofocus
                           placeholder="Contoh: Budi Santoso"
                           class="w-full px-3.5 py-2.5 rounded-lg border {{ $errors->has('name') ? 'border-danger-line focus:ring-danger-line' : 'border-field-line focus:border-focus focus:ring-focus' }} text-sm focus:outline-none focus:ring-2 transition-colors">
                    @error('name')
                        <p class="mt-1.5 text-xs text-danger font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Email Field -->
                <div>
                    <label for="email" class="block text-xs font-semibold text-ink uppercase tracking-wider mb-1.5">
                        Alamat Email <span class="text-danger">*</span>
                    </label>
                    <input type="email" 
                           id="email" 
                           name="email" 
                           value="{{ old('email') }}" 
                           required 
                           placeholder="nama@email.com"
                           class="w-full px-3.5 py-2.5 rounded-lg border {{ $errors->has('email') ? 'border-danger-line focus:ring-danger-line' : 'border-field-line focus:border-focus focus:ring-focus' }} text-sm focus:outline-none focus:ring-2 transition-colors">
                    @error('email')
                        <p class="mt-1.5 text-xs text-danger font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Phone Field -->
                <div>
                    <label for="phone" class="block text-xs font-semibold text-ink uppercase tracking-wider mb-1.5">
                        Nomor Telepon / WhatsApp <span class="text-danger">*</span>
                    </label>
                    <input type="tel" 
                           id="phone" 
                           name="phone" 
                           value="{{ old('phone') }}" 
                           required 
                           placeholder="Contoh: 081234567890"
                           class="w-full px-3.5 py-2.5 rounded-lg border {{ $errors->has('phone') ? 'border-danger-line focus:ring-danger-line' : 'border-field-line focus:border-focus focus:ring-focus' }} text-sm focus:outline-none focus:ring-2 transition-colors">
                    @error('phone')
                        <p class="mt-1.5 text-xs text-danger font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Password Field -->
                <div>
                    <label for="password" class="block text-xs font-semibold text-ink uppercase tracking-wider mb-1.5">
                        Kata Sandi <span class="text-danger">*</span>
                    </label>
                    <input type="password" 
                           id="password" 
                           name="password" 
                           required 
                           placeholder="Minimal 8 karakter"
                           class="w-full px-3.5 py-2.5 rounded-lg border {{ $errors->has('password') ? 'border-danger-line focus:ring-danger-line' : 'border-field-line focus:border-focus focus:ring-focus' }} text-sm focus:outline-none focus:ring-2 transition-colors">
                    @error('password')
                        <p class="mt-1.5 text-xs text-danger font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Password Confirmation Field -->
                <div>
                    <label for="password_confirmation" class="block text-xs font-semibold text-ink uppercase tracking-wider mb-1.5">
                        Ulangi Kata Sandi <span class="text-danger">*</span>
                    </label>
                    <input type="password" 
                           id="password_confirmation" 
                           name="password_confirmation" 
                           required 
                           placeholder="Masukkan kembali kata sandi"
                           class="w-full px-3.5 py-2.5 rounded-lg border border-field-line focus:border-focus focus:ring-focus text-sm focus:outline-none focus:ring-2 transition-colors">
                </div>

                <!-- Submit Button -->
                <div class="pt-3">
                    <button type="submit" class="w-full py-2.5 px-4 bg-action hover:bg-action-hover text-ink font-semibold text-sm rounded-lg shadow-sm hover:shadow transition-colors">
                        Buat Akun Pelanggan
                    </button>
                </div>
            </form>

            <div class="mt-6 pt-4 border-t border-line text-center text-xs text-muted">
                Sudah memiliki akun?
                <a href="{{ route('login') }}" class="font-semibold text-link hover:text-link ml-1">
                    Masuk ke Sini
                </a>
            </div>
        </div>

        <div class="mt-4 text-center">
            <a href="{{ route('cars.index') }}" class="text-xs text-muted hover:text-ink transition-colors">
                &larr; Kembali ke Katalog Armada
            </a>
        </div>
    </div>
</div>
@endsection
