@extends('layouts.app')

@section('title', '403 - Akses Ditolak | DriveEase')

@section('body')
<div class="min-h-screen flex items-center justify-center p-4 bg-slate-900 text-center">
    <div class="max-w-md w-full bg-white rounded-2xl p-8 border border-slate-200 shadow-xl">
        <div class="w-16 h-16 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center mx-auto mb-4">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
        </div>
        <h1 class="text-3xl font-extrabold text-slate-900 mb-2">403</h1>
        <h2 class="text-base font-bold text-slate-800 mb-2">Akses Ditolak (Wewenang Terbatas)</h2>
        <p class="text-xs text-slate-500 mb-6 leading-relaxed">
            {{ $exception->getMessage() ?: 'Akun Anda tidak memiliki izin atau wewenang untuk membuka halaman ini.' }}
        </p>
        <div class="flex items-center justify-center gap-3">
            @auth
                <a href="{{ route('home') }}" class="px-5 py-2.5 bg-slate-900 hover:bg-slate-800 text-white text-xs font-semibold rounded-lg shadow-sm transition-colors">
                    Kembali ke Dashboard Anda
                </a>
            @else
                <a href="{{ route('login') }}" class="px-5 py-2.5 bg-teal-600 hover:bg-teal-700 text-white text-xs font-semibold rounded-lg shadow-sm transition-colors">
                    Halaman Masuk (Login)
                </a>
            @endauth
        </div>
    </div>
</div>
@endsection
