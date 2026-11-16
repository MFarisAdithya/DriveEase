@extends('layouts.app')

@section('body')
@php
    $readyVerificationCount = \App\Models\User::where('verification_status', 'pending')
        ->whereHas('role', fn ($q) => $q->where('name', 'customer'))
        ->whereRaw("COALESCE(NULLIF(ktp_number, ''), nik_ktp, '') <> '' AND COALESCE(sim_number, '') <> '' AND COALESCE(ktp_image, '') <> '' AND COALESCE(sim_image, '') <> ''")
        ->count();
@endphp
<div class="flex h-screen bg-canvas text-ink overflow-hidden">
    <!-- Sidebar Desktop -->
    <aside class="hidden md:flex w-64 bg-brand-navy text-white flex-col shrink-0 border-r border-brand-navy">
        <!-- Brand -->
        <div class="h-16 flex items-center px-6 border-b border-white/15 gap-3">
            <div class="w-9 h-9 rounded-lg bg-action flex items-center justify-center font-bold text-brand-navy text-sm shrink-0">
                DE
            </div>
            <div>
                <span class="font-bold text-lg text-white tracking-tight">DriveEase</span>
                <span class="block text-xs text-brand-soft">Area admin</span>
            </div>
        </div>

        <!-- Navigation -->
        <nav aria-label="Navigasi area admin" class="flex-1 px-3 py-5 space-y-1.5 overflow-y-auto">
            <a href="{{ route('admin.dashboard') }}"
               class="min-h-11 flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-sm font-semibold transition-colors {{ request()->routeIs('admin.dashboard') ? 'bg-brand-soft text-brand-navy' : 'text-slate-100 hover:bg-white/10 hover:text-white' }}">
                <svg class="w-5 h-5 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                Ringkasan
            </a>

            <a href="{{ route('admin.cars.index') }}"
               class="min-h-11 flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-sm font-semibold transition-colors {{ request()->routeIs('admin.cars.*') ? 'bg-brand-soft text-brand-navy' : 'text-slate-100 hover:bg-white/10 hover:text-white' }}">
                <svg class="w-5 h-5 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 19H5V8h14m-3-5H8L5 6v13a2 2 0 002 2h10a2 2 0 002-2V6l-3-3z"/>
                </svg>
                Kendaraan
            </a>

            <a href="{{ route('admin.car-types.index') }}"
               class="min-h-11 flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-sm font-semibold transition-colors {{ request()->routeIs('admin.car-types.*') ? 'bg-brand-soft text-brand-navy' : 'text-slate-100 hover:bg-white/10 hover:text-white' }}">
                <svg class="w-5 h-5 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                </svg>
                Kategori
            </a>

            <a href="{{ route('admin.verifications.index') }}"
               class="min-h-11 flex items-center justify-between px-3.5 py-2.5 rounded-lg text-sm font-semibold transition-colors {{ request()->routeIs('admin.verifications.*') ? 'bg-brand-soft text-brand-navy' : 'text-slate-100 hover:bg-white/10 hover:text-white' }}">
                <span class="flex items-center gap-3">
                    <svg class="w-5 h-5 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                    Verifikasi pelanggan
                </span>
                @if ($readyVerificationCount > 0)
                    <span class="inline-flex items-center justify-center min-w-6 px-2 py-0.5 text-xs font-bold leading-none text-brand-navy bg-action rounded-full">
                        {{ $readyVerificationCount }}
                    </span>
                @endif
            </a>

            <div class="pt-4 mt-4 border-t border-white/15">
                <a href="{{ route('cars.index') }}"
                   class="min-h-11 flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-sm font-medium text-brand-soft hover:bg-white/10 hover:text-white transition-colors">
                    <svg class="w-5 h-5 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                    </svg>
                    Lihat katalog mobil
                </a>
            </div>
        </nav>

        <!-- User info & Logout -->
        <div class="p-4 border-t border-white/15">
            <div class="mb-3">
                <p class="text-sm font-semibold text-white truncate">{{ auth()->user()->name }}</p>
                <p class="text-xs text-brand-soft">{{ auth()->user()->role?->display_name ?? 'Admin' }}</p>
            </div>
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit"
                        class="w-full min-h-11 flex items-center justify-center gap-2 px-3 py-2 text-sm font-semibold text-white bg-white/10 hover:bg-action hover:text-brand-navy rounded-lg transition-colors border border-white/20 cursor-pointer">
                    <svg class="w-4 h-4 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                    Keluar
                </button>
            </form>
        </div>
    </aside>

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col min-w-0 overflow-y-auto">
        <!-- Top Navbar -->
        <header class="min-h-16 bg-surface text-ink border-b border-line flex items-center gap-3 px-4 md:justify-between md:px-8 shrink-0">
            <details id="admin-mobile-menu" class="relative md:hidden">
                <summary class="min-h-11 px-3.5 inline-flex items-center gap-1.5 rounded-lg border border-field-line bg-surface hover:bg-surface-raised text-sm font-semibold text-ink cursor-pointer list-none">
                    <svg class="w-4 h-4 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                    Menu
                </summary>
                <nav aria-label="Navigasi admin ponsel" class="absolute left-0 top-full mt-2 z-40 grid w-64 gap-1 rounded-xl border border-line bg-surface p-2 shadow-lg">
                    <a class="min-h-11 px-3 flex items-center rounded-lg text-sm font-semibold {{ request()->routeIs('admin.dashboard') ? 'bg-brand-soft text-brand-navy' : 'text-ink hover:bg-surface-raised' }}" href="{{ route('admin.dashboard') }}">Ringkasan</a>
                    <a class="min-h-11 px-3 flex items-center rounded-lg text-sm font-semibold {{ request()->routeIs('admin.cars.*') ? 'bg-brand-soft text-brand-navy' : 'text-ink hover:bg-surface-raised' }}" href="{{ route('admin.cars.index') }}">Kendaraan</a>
                    <a class="min-h-11 px-3 flex items-center rounded-lg text-sm font-semibold {{ request()->routeIs('admin.car-types.*') ? 'bg-brand-soft text-brand-navy' : 'text-ink hover:bg-surface-raised' }}" href="{{ route('admin.car-types.index') }}">Kategori</a>
                    <a class="min-h-11 px-3 flex items-center justify-between rounded-lg text-sm font-semibold {{ request()->routeIs('admin.verifications.*') ? 'bg-brand-soft text-brand-navy' : 'text-ink hover:bg-surface-raised' }}" href="{{ route('admin.verifications.index') }}">
                        <span>Verifikasi pelanggan</span>
                        @if ($readyVerificationCount > 0)
                            <span class="px-2 py-0.5 text-xs font-bold bg-action text-brand-navy rounded-full">{{ $readyVerificationCount }}</span>
                        @endif
                    </a>
                    <a class="min-h-11 px-3 flex items-center rounded-lg text-sm font-medium text-muted hover:bg-surface-raised" href="{{ route('cars.index') }}">Lihat katalog mobil</a>
                    <div class="pt-1 mt-1 border-t border-line">
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="w-full min-h-11 px-3 flex items-center rounded-lg text-sm font-semibold text-danger hover:bg-danger-surface cursor-pointer text-left">
                                Keluar
                            </button>
                        </form>
                    </div>
                </nav>
            </details>

            <div>
                <h1 class="text-lg font-bold text-ink">@yield('page_title', 'Area admin')</h1>
            </div>

            <div class="hidden md:flex items-center gap-4 text-sm text-muted">
                <span>{{ now()->locale('id')->translatedFormat('l, d F Y') }}</span>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-success-surface text-success border border-success-line">
                    Sistem aktif
                </span>
            </div>
        </header>

        <!-- Page Body -->
        <main class="flex-1 p-4 md:p-8 max-w-[1200px] w-full">
            <!-- Flash Messages -->
            @if (session('success'))
                <div role="status" class="mb-6 p-4 rounded-xl bg-success-surface border border-success-line flex items-start gap-3 text-success">
                    <svg class="w-5 h-5 text-success shrink-0 mt-0.5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <div class="text-sm font-medium leading-5">{{ session('success') }}</div>
                </div>
            @endif

            @if (session('error'))
                <div role="alert" class="mb-6 p-4 rounded-xl bg-danger-surface border border-danger-line flex items-start gap-3 text-danger">
                    <svg class="w-5 h-5 text-danger shrink-0 mt-0.5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <div class="text-sm font-medium leading-5">{{ session('error') }}</div>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>

<script>
    (function () {
        const menu = document.getElementById('admin-mobile-menu');
        if (!menu) return;
        const summary = menu.querySelector('summary');
        menu.addEventListener('toggle', function () {
            if (menu.open) {
                const firstLink = menu.querySelector('nav a, nav button');
                if (firstLink) firstLink.focus();
            }
        });
        menu.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && menu.open) {
                menu.open = false;
                if (summary) summary.focus();
            }
        });
    })();
</script>
@endsection
