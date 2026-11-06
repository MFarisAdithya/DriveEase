@extends('layouts.app')

@section('title', 'Masuk ke Sistem - DriveEase')

@section('body')
<div class="min-h-screen flex items-center justify-center p-4 bg-slate-900">
    <div class="w-full max-w-md">
        <!-- Logo & Header -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-teal-600 text-white font-bold text-2xl shadow-lg mb-3">
                DE
            </div>
            <h1 class="text-2xl font-bold text-white tracking-tight">DriveEase Rental</h1>
            <p class="text-sm text-slate-400 mt-1">Sistem Informasi Manajemen Rental Kendaraan</p>
        </div>

        <!-- Login Card -->
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 p-8">
            <h2 class="text-lg font-bold text-slate-900 mb-6">Masuk ke Akun Anda</h2>

            @if (session('success'))
                <div class="mb-5 p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-xs text-emerald-800">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('error'))
                <div class="mb-5 p-3 rounded-lg bg-rose-50 border border-rose-200 text-xs text-rose-800">
                    {{ session('error') }}
                </div>
            @endif

            <form action="{{ route('login') }}" method="POST" class="space-y-4">
                @csrf

                <!-- Email Field -->
                <div>
                    <label for="email" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                        Alamat Email
                    </label>
                    <input type="email" 
                           id="email" 
                           name="email" 
                           value="{{ old('email') }}" 
                           required 
                           autofocus
                           placeholder="nama@email.com"
                           class="w-full px-3.5 py-2.5 rounded-lg border {{ $errors->has('email') ? 'border-rose-400 focus:ring-rose-200' : 'border-slate-300 focus:border-teal-600 focus:ring-teal-100' }} text-sm focus:outline-none focus:ring-2 transition-colors">
                    @error('email')
                        <p class="mt-1.5 text-xs text-rose-600 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Password Field -->
                <div>
                    <label for="password" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                        Kata Sandi
                    </label>
                    <input type="password" 
                           id="password" 
                           name="password" 
                           required 
                           placeholder="••••••••"
                           class="w-full px-3.5 py-2.5 rounded-lg border {{ $errors->has('password') ? 'border-rose-400 focus:ring-rose-200' : 'border-slate-300 focus:border-teal-600 focus:ring-teal-100' }} text-sm focus:outline-none focus:ring-2 transition-colors">
                    @error('password')
                        <p class="mt-1.5 text-xs text-rose-600 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Remember Me -->
                <div class="flex items-center justify-between text-xs pt-1">
                    <label class="flex items-center gap-2 cursor-pointer text-slate-600 select-none">
                        <input type="checkbox" name="remember" class="rounded border-slate-300 text-teal-600 focus:ring-teal-500">
                        <span>Ingat saya di perangkat ini</span>
                    </label>
                </div>

                <!-- Submit Button -->
                <div class="pt-2">
                    <button type="submit" class="w-full py-2.5 px-4 bg-slate-900 hover:bg-slate-800 text-white font-semibold text-sm rounded-lg shadow-sm hover:shadow transition-colors">
                        Masuk ke Dashboard
                    </button>
                </div>
            </form>
        </div>

        <!-- Demo Accounts Helper -->
        <div class="mt-6 bg-slate-800/80 rounded-xl border border-slate-700/80 p-4 text-xs text-slate-300">
            <p class="font-semibold text-slate-200 mb-2 flex items-center gap-1.5">
                <svg class="w-4 h-4 text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Akun Demo Uji Coba (Password: password123)
            </p>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 mt-2">
                <button type="button" 
                        onclick="document.getElementById('email').value='admin@driveease.test'; document.getElementById('password').value='password123';"
                        class="p-2 bg-slate-900/60 rounded border border-slate-700 hover:border-teal-500 text-left transition-colors">
                    <span class="block font-medium text-teal-300">Admin</span>
                    <span class="text-[11px] text-slate-400 truncate block">admin@driveease.test</span>
                </button>
                <button type="button" 
                        onclick="document.getElementById('email').value='staff@driveease.test'; document.getElementById('password').value='password123';"
                        class="p-2 bg-slate-900/60 rounded border border-slate-700 hover:border-teal-500 text-left transition-colors">
                    <span class="block font-medium text-teal-300">Staf</span>
                    <span class="text-[11px] text-slate-400 truncate block">staff@driveease.test</span>
                </button>
                <button type="button" 
                        onclick="document.getElementById('email').value='customer@driveease.test'; document.getElementById('password').value='password123';"
                        class="p-2 bg-slate-900/60 rounded border border-slate-700 hover:border-teal-500 text-left transition-colors">
                    <span class="block font-medium text-teal-300">Customer</span>
                    <span class="text-[11px] text-slate-400 truncate block">customer@driveease.test</span>
                </button>
            </div>
        </div>
    </div>
</div>
@endsection
