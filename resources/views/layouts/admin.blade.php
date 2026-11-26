@extends('layouts.app')

@section('body')
@php
    // Array yang sama menentukan urutan menu desktop dan seluler. Pola route
    // bertanda * menjaga penanda aktif saat admin membuka subhalaman seperti detail.
    $adminNavigation = [
        'Operasional' => [
            ['admin.dashboard', 'admin.dashboard', 'Ringkasan', 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
            ['admin.bookings.index', 'admin.bookings.*', 'Pemesanan', 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
            ['admin.verifications.index', 'admin.verifications.*', 'Verifikasi pelanggan', 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z'],
        ],
        'Armada' => [
            ['admin.cars.index', 'admin.cars.*', 'Kendaraan', 'M19 19H5V8h14m-3-5H8L5 6v13a2 2 0 002 2h10a2 2 0 002-2V6l-3-3z'],
            ['admin.car-types.index', 'admin.car-types.*', 'Kategori kendaraan', 'M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z'],
        ],
        'Pengelolaan' => [
            ['admin.reports.index', 'admin.reports.*', 'Laporan rental', 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
            ['admin.staff.index', 'admin.staff.*', 'Kelola staf', 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
            ['admin.rental-settings.edit', 'admin.rental-settings.*', 'Pengaturan rental', 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z'],
        ],
    ];

    // Hitung berkas siap ditinjau sekali agar kedua menu memakai angka antrean yang sama.
    $pendingVerificationCount = app(\App\Services\VerificationQueue::class)->counts()['ready'];
@endphp

<div class="flex h-screen bg-canvas text-ink [color-scheme:dark] overflow-hidden">
    <a href="#admin-content" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-2 focus:z-50 focus:rounded-lg focus:bg-action focus:px-4 focus:py-2 focus:font-bold">Lewati ke konten utama</a>
    <!-- Sidebar -->
    <aside class="hidden md:flex w-64 bg-navigation text-subtle flex-col shrink-0 border-r border-line">
        <!-- Brand Header (Transparent logo seamlessly integrated into sky-blue coastal backdrop) -->
        <div class="relative overflow-hidden px-5 pt-4 pb-6 shrink-0"
             style="background: linear-gradient(135deg, #FFFFFF 0%, #E7F5FC 58%, #C8E7F8 100%);">
            <div class="relative z-10 flex flex-col gap-1.5">
                <a href="{{ route('admin.dashboard') }}" class="inline-flex items-center rounded">
                    @include('partials.driveease-logo', [
                        'sizeClass' => 'h-11',
                        'haloSize' => 'w-11 h-11',
                    ])
                </a>
                <div class="flex items-center gap-1.5 pl-1.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-[#FF6454]"></span>
                    <span class="text-[11px] font-bold uppercase tracking-[0.12em] text-[#0B2840]/80">Area Admin</span>
                </div>
            </div>

            <!-- Smooth Coastal Wave Divider into #002941 Sidebar -->
            <svg class="pointer-events-none absolute bottom-0 left-0 right-0 w-full h-4 block" viewBox="0 0 256 20" preserveAspectRatio="none" aria-hidden="true">
                <path d="M0,10 C75,20 175,0 256,12 L256,20 L0,20 Z" fill="#002941"/>
                <path d="M165,20 C200,14 230,6 256,2 L256,12 C232,15 202,18 165,20 Z" fill="#FF6454" fill-opacity="0.9"/>
            </svg>
        </div>

        <nav aria-label="Navigasi admin" class="flex-1 space-y-4 overflow-y-auto px-4 py-4">
            @include('admin.navigation-links', ['showIcon' => true])
        </nav>

        <!-- User info & Logout -->
        <div class="p-4 border-t border-line">
            <div class="flex items-center justify-between mb-3">
                <div class="truncate">
                    <p class="text-sm font-semibold text-ink truncate">{{ auth()->user()->name }}</p>
                    <p class="text-xs text-link capitalize">{{ auth()->user()->role?->display_name ?? 'Admin' }}</p>
                </div>
            </div>
            <a href="{{ route('account.edit') }}" class="mb-2 flex items-center justify-center rounded-lg border border-field-line px-3 py-2 text-xs font-medium text-subtle hover:bg-surface-hover hover:text-ink transition-colors">Akun Saya</a>
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="w-full flex items-center justify-center gap-2 px-3 py-2 text-xs font-medium text-subtle bg-surface-hover hover:bg-danger-surface/60 hover:text-danger rounded-lg transition-colors border border-field-line">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                    Keluar
                </button>
            </form>
        </div>
    </aside>

    <!-- Main Content Area -->
    {{-- Lapis luar menahan backdrop agar tetap diam (stay) mengikuti viewport admin saat konten digulir. --}}
    <div class="relative flex-1 flex flex-col min-w-0 overflow-hidden">
        @include('partials.driveease-backdrop')
        <div class="relative z-10 flex-1 flex flex-col min-w-0 overflow-y-auto overflow-x-clip">
            {{-- Gelombang dan latar ditempatkan pada pembungkus luar yang tetap diam. --}}

            <!-- Top Navbar -->
            <header class="sticky top-0 z-30 h-16 bg-surface/90 backdrop-blur-md text-ink border-b border-line flex items-center gap-3 px-4 md:justify-between md:px-8 shrink-0">
            <details class="relative md:hidden">
                <summary class="cursor-pointer py-3 font-semibold">Menu</summary>
                <nav aria-label="Navigasi admin" class="absolute left-0 top-full z-40 grid max-h-[calc(100dvh-5rem)] w-64 gap-3 overflow-y-auto rounded-lg border border-line bg-surface-raised p-3 shadow-lg">
                    @include('admin.navigation-links', ['showIcon' => false])
                    <a href="{{ route('account.edit') }}" class="mb-2 flex items-center justify-center rounded-lg border border-field-line px-3 py-2 text-xs font-medium text-subtle hover:bg-surface-hover hover:text-ink transition-colors">Akun Saya</a>
                    <form method="POST" action="{{ route('logout') }}">@csrf<button class="p-2">Keluar</button></form>
                </nav>
            </details>
            <div>
                <h1 class="text-lg font-bold text-ink">@yield('page_title', 'Admin Portal')</h1>
            </div>
            <div class="hidden md:flex items-center gap-4 text-xs text-muted">
                <span>{{ now()->locale('id')->translatedFormat('l, d F Y') }}</span>
            </div>
        </header>

        <!-- Page Body -->
        <main id="admin-content" tabindex="-1" class="relative z-10 flex-1 p-4 md:p-8 pb-28 sm:pb-36">
            <!-- Flash Messages -->
            @if (session('success'))
                <div role="status" class="mb-6 p-4 rounded-lg bg-success-surface border border-success-line flex items-start gap-3 text-success">
                    <svg class="w-5 h-5 text-success shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <div class="text-sm font-medium">{{ session('success') }}</div>
                </div>
            @endif

            @if (session('error'))
                <div role="alert" class="mb-6 p-4 rounded-lg bg-danger-surface border border-danger-line flex items-start gap-3 text-danger">
                    <svg class="w-5 h-5 text-danger shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <div class="text-sm font-medium">{{ session('error') }}</div>
                </div>
            @endif

            @yield('content')
        </main>
        </div>
    </div>
</div>
@endsection
