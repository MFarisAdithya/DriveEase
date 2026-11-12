@extends('layouts.app')

@section('body')
<div class="flex h-screen bg-slate-100 overflow-hidden">
    <!-- Sidebar -->
    <aside class="hidden md:flex w-64 bg-slate-900 text-slate-200 flex-col shrink-0 border-r border-slate-800">
        <!-- Brand -->
        <div class="h-16 flex items-center px-6 border-b border-slate-800 gap-3">
            <div class="w-9 h-9 rounded-lg bg-teal-600 flex items-center justify-center font-bold text-white shadow-sm">
                DE
            </div>
            <div>
                <span class="font-bold text-lg text-white tracking-tight">DriveEase</span>
                <span class="block text-xs text-slate-400">Admin Console</span>
            </div>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 px-4 py-6 space-y-1 overflow-y-auto">
            <a href="{{ route('admin.dashboard') }}" 
               class="flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-sm font-medium transition-colors {{ request()->routeIs('admin.dashboard') ? 'bg-teal-700 text-white shadow-sm' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                Dashboard
            </a>

            <a href="{{ route('admin.cars.index') }}" 
               class="flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-sm font-medium transition-colors {{ request()->routeIs('admin.cars.*') ? 'bg-teal-700 text-white shadow-sm' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 19H5V8h14m-3-5H8L5 6v13a2 2 0 002 2h10a2 2 0 002-2V6l-3-3z"/>
                </svg>
                Kelola Armada
            </a>

            <a href="{{ route('admin.car-types.index') }}" 
               class="flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-sm font-medium transition-colors {{ request()->routeIs('admin.car-types.*') ? 'bg-teal-700 text-white shadow-sm' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                </svg>
                Kategori Kendaraan
            </a>

            @php
                $pendingVerificationCount = \App\Models\User::where('verification_status', 'pending')
                    ->whereHas('role', fn($q) => $q->where('name', 'customer'))
                    ->count();
            @endphp
            <a href="{{ route('admin.verifications.index') }}" 
               class="flex items-center justify-between px-3.5 py-2.5 rounded-lg text-sm font-medium transition-colors {{ request()->routeIs('admin.verifications.*') ? 'bg-teal-700 text-white shadow-sm' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                <div class="flex items-center gap-3">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                    Verifikasi Customer
                </div>
                @if ($pendingVerificationCount > 0)
                    <span class="inline-flex items-center justify-center px-2 py-0.5 text-xs font-bold leading-none text-amber-900 bg-amber-400 rounded-full">
                        {{ $pendingVerificationCount }}
                    </span>
                @endif
            </a>
        </nav>

        <!-- User info & Logout -->
        <div class="p-4 border-t border-slate-800">
            <div class="flex items-center justify-between mb-3">
                <div class="truncate">
                    <p class="text-sm font-semibold text-white truncate">{{ auth()->user()->name }}</p>
                    <p class="text-xs text-teal-400 capitalize">{{ auth()->user()->role?->display_name ?? 'Admin' }}</p>
                </div>
            </div>
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="w-full flex items-center justify-center gap-2 px-3 py-2 text-xs font-medium text-slate-300 bg-slate-800 hover:bg-rose-900/60 hover:text-rose-200 rounded-lg transition-colors border border-slate-700">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                    Keluar Sistem
                </button>
            </form>
        </div>
    </aside>

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col min-w-0 overflow-y-auto">
        <!-- Top Navbar -->
        <header class="h-16 bg-white border-b border-slate-200 flex items-center gap-3 px-4 md:justify-between md:px-8 shrink-0">
            <details class="relative md:hidden">
                <summary class="cursor-pointer py-3 font-semibold">Menu</summary>
                <nav aria-label="Navigasi admin" class="absolute left-0 top-full z-40 grid w-56 gap-1 border bg-white p-3 shadow-lg">
                    @foreach (['admin.dashboard' => 'Dasbor', 'admin.cars.index' => 'Kelola armada', 'admin.car-types.index' => 'Kategori kendaraan', 'admin.verifications.index' => 'Verifikasi pelanggan'] as $destination => $label)
                        <a class="p-2 hover:underline" href="{{ route($destination) }}">{{ $label }}</a>
                    @endforeach
                    <form method="POST" action="{{ route('logout') }}">@csrf<button class="p-2">Keluar</button></form>
                </nav>
            </details>
            <div>
                <h1 class="text-lg font-bold text-slate-900">@yield('page_title', 'Admin Portal')</h1>
            </div>
            <div class="hidden md:flex items-center gap-4 text-xs text-slate-500">
                <span>{{ now()->translatedFormat('l, d F Y') }}</span>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
                    Sistem Aktif
                </span>
            </div>
        </header>

        <!-- Page Body -->
        <main class="flex-1 p-4 md:p-8">
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
    </div>
</div>
@endsection
