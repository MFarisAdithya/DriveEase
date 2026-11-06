@extends('layouts.app')

@section('body')
<div class="min-h-screen bg-slate-100 flex flex-col">
    <!-- Top Navbar -->
    <header class="bg-slate-900 border-b border-slate-800 text-white sticky top-0 z-30">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <!-- Brand -->
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-teal-600 flex items-center justify-center font-bold text-white shadow-sm">
                    DE
                </div>
                <div>
                    <span class="font-bold text-lg text-white tracking-tight">DriveEase</span>
                    <span class="ml-2 px-2 py-0.5 rounded text-xs font-medium bg-slate-800 text-teal-300 border border-slate-700">
                        {{ auth()->user()->role?->display_name ?? 'User' }}
                    </span>
                </div>
            </div>

            <!-- User Menu -->
            <div class="flex items-center gap-4">
                <div class="text-right hidden sm:block">
                    <p class="text-sm font-semibold text-white">{{ auth()->user()->name }}</p>
                    <p class="text-xs text-slate-400">{{ auth()->user()->email }}</p>
                </div>
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-slate-200 bg-slate-800 hover:bg-rose-900/60 hover:text-white rounded-lg transition-colors border border-slate-700">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                        Keluar
                    </button>
                </form>
            </div>
        </div>
    </header>

    <!-- Page Content -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Flash Messages -->
        @if (session('success'))
            <div class="mb-6 p-4 rounded-lg bg-emerald-50 border border-emerald-200 flex items-start gap-3 text-emerald-800">
                <svg class="w-5 h-5 text-emerald-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <div class="text-sm font-medium">{{ session('success') }}</div>
            </div>
        @endif

        @if (session('error'))
            <div class="mb-6 p-4 rounded-lg bg-rose-50 border border-rose-200 flex items-start gap-3 text-rose-800">
                <svg class="w-5 h-5 text-rose-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <div class="text-sm font-medium">{{ session('error') }}</div>
            </div>
        @endif

        @yield('content')
    </main>

    <!-- Footer -->
    <footer class="bg-white border-t border-slate-200 py-4 text-center text-xs text-slate-500">
        &copy; {{ date('Y') }} DriveEase — Sistem Informasi Manajemen Rental Kendaraan.
    </footer>
</div>
@endsection
