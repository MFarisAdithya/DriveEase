@extends('layouts.app')

@section('title', 'Daftar Akun Baru - DriveEase')

@section('body')
<div class="min-h-screen flex items-center justify-center p-4 bg-slate-900 py-12">
    <div class="w-full max-w-md">
        <!-- Logo & Header -->
        <div class="text-center mb-8">
            <a href="{{ route('cars.index') }}" class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-teal-600 text-white font-bold text-2xl shadow-lg mb-3 hover:bg-teal-500 transition-colors">
                DE
            </a>
            <h1 class="text-2xl font-bold text-white tracking-tight">Daftar Akun Pelanggan</h1>
            <p class="text-sm text-slate-400 mt-1">Bergabung bersama DriveEase untuk kemudahan sewa armada</p>
        </div>

        <!-- Register Card -->
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 p-8">
            <form action="{{ route('register') }}" method="POST" class="space-y-4">
                @csrf

                <!-- Name Field -->
                <div>
                    <label for="name" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                        Nama Lengkap <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" 
                           id="name" 
                           name="name" 
                           value="{{ old('name') }}" 
                           required 
                           autofocus
                           placeholder="Contoh: Budi Santoso"
                           class="w-full px-3.5 py-2.5 rounded-lg border {{ $errors->has('name') ? 'border-rose-400 focus:ring-rose-200' : 'border-slate-300 focus:border-teal-600 focus:ring-teal-100' }} text-sm focus:outline-none focus:ring-2 transition-colors">
                    @error('name')
                        <p class="mt-1.5 text-xs text-rose-600 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Email Field -->
                <div>
                    <label for="email" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                        Alamat Email <span class="text-rose-500">*</span>
                    </label>
                    <input type="email" 
                           id="email" 
                           name="email" 
                           value="{{ old('email') }}" 
                           required 
                           placeholder="nama@email.com"
                           class="w-full px-3.5 py-2.5 rounded-lg border {{ $errors->has('email') ? 'border-rose-400 focus:ring-rose-200' : 'border-slate-300 focus:border-teal-600 focus:ring-teal-100' }} text-sm focus:outline-none focus:ring-2 transition-colors">
                    @error('email')
                        <p class="mt-1.5 text-xs text-rose-600 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Phone Field -->
                <div>
                    <label for="phone" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                        Nomor Telepon / WhatsApp <span class="text-rose-500">*</span>
                    </label>
                    <input type="tel" 
                           id="phone" 
                           name="phone" 
                           value="{{ old('phone') }}" 
                           required 
                           placeholder="Contoh: 081234567890"
                           class="w-full px-3.5 py-2.5 rounded-lg border {{ $errors->has('phone') ? 'border-rose-400 focus:ring-rose-200' : 'border-slate-300 focus:border-teal-600 focus:ring-teal-100' }} text-sm focus:outline-none focus:ring-2 transition-colors">
                    @error('phone')
                        <p class="mt-1.5 text-xs text-rose-600 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Password Field -->
                <div>
                    <label for="password" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                        Kata Sandi <span class="text-rose-500">*</span>
                    </label>
                    <input type="password" 
                           id="password" 
                           name="password" 
                           required 
                           placeholder="Minimal 8 karakter"
                           class="w-full px-3.5 py-2.5 rounded-lg border {{ $errors->has('password') ? 'border-rose-400 focus:ring-rose-200' : 'border-slate-300 focus:border-teal-600 focus:ring-teal-100' }} text-sm focus:outline-none focus:ring-2 transition-colors">
                    @error('password')
                        <p class="mt-1.5 text-xs text-rose-600 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Password Confirmation Field -->
                <div>
                    <label for="password_confirmation" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                        Ulangi Kata Sandi <span class="text-rose-500">*</span>
                    </label>
                    <input type="password" 
                           id="password_confirmation" 
                           name="password_confirmation" 
                           required 
                           placeholder="Masukkan kembali kata sandi"
                           class="w-full px-3.5 py-2.5 rounded-lg border border-slate-300 focus:border-teal-600 focus:ring-teal-100 text-sm focus:outline-none focus:ring-2 transition-colors">
                </div>

                <!-- Submit Button -->
                <div class="pt-3">
                    <button type="submit" class="w-full py-2.5 px-4 bg-teal-600 hover:bg-teal-700 text-white font-semibold text-sm rounded-lg shadow-sm hover:shadow transition-colors">
                        Buat Akun Pelanggan
                    </button>
                </div>
            </form>

            <div class="mt-6 pt-4 border-t border-slate-100 text-center text-xs text-slate-500">
                Sudah memiliki akun?
                <a href="{{ route('login') }}" class="font-semibold text-teal-700 hover:text-teal-800 ml-1">
                    Masuk ke Sini
                </a>
            </div>
        </div>

        <div class="mt-4 text-center">
            <a href="{{ route('cars.index') }}" class="text-xs text-slate-400 hover:text-white transition-colors">
                &larr; Kembali ke Katalog Armada
            </a>
        </div>
    </div>
</div>
@endsection
