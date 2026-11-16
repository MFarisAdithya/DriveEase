@extends('layouts.app')

@section('body')
<div class="min-h-screen bg-canvas text-ink flex flex-col">
    <!-- Top Navbar -->
    <header class="bg-surface border-b border-line text-ink sticky top-0 z-30">
        <div class="max-w-[1200px] mx-auto px-4 sm:px-6 lg:px-8 min-h-16 py-2 flex items-center justify-between gap-4">
            <!-- Brand & Desktop Navigation -->
            <div class="flex items-center gap-6">
                <a href="{{ route('cars.index') }}" class="flex items-center gap-3 min-h-11 rounded-lg pr-2">
                    <div class="w-9 h-9 rounded-lg bg-brand-navy flex items-center justify-center font-bold text-white text-sm">
                        DE
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="font-bold text-lg text-ink tracking-tight">DriveEase</span>
                        @auth
                            <span class="hidden sm:inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-semibold bg-surface-raised text-ink border border-line">
                                {{ auth()->user()->role?->display_name ?? 'Pengguna' }}
                            </span>
                        @endauth
                    </div>
                </a>

                <nav aria-label="Navigasi utama" class="hidden md:flex items-center gap-1.5 text-sm font-semibold">
                    <a href="{{ route('cars.index') }}"
                       class="min-h-11 px-3.5 inline-flex items-center rounded-lg transition-colors {{ request()->routeIs('cars.*') ? 'bg-brand-soft text-brand-navy' : 'text-muted hover:text-ink hover:bg-surface-raised' }}">
                        Katalog mobil
                    </a>
                    @auth
                        @if (auth()->user()->isAdmin())
                            <a href="{{ route('admin.dashboard') }}"
                               class="min-h-11 px-3.5 inline-flex items-center rounded-lg transition-colors {{ request()->routeIs('admin.*') ? 'bg-brand-soft text-brand-navy' : 'text-muted hover:text-ink hover:bg-surface-raised' }}">
                                Ringkasan
                            </a>
                        @elseif (auth()->user()->isStaff())
                            <a href="{{ route('staff.dashboard') }}"
                               class="min-h-11 px-3.5 inline-flex items-center rounded-lg transition-colors {{ request()->routeIs('staff.dashboard') ? 'bg-brand-soft text-brand-navy' : 'text-muted hover:text-ink hover:bg-surface-raised' }}">
                                Status armada
                            </a>
                        @elseif (auth()->user()->isCustomer())
                            <a href="{{ route('customer.dashboard') }}"
                               class="min-h-11 px-3.5 inline-flex items-center rounded-lg transition-colors {{ request()->routeIs('customer.dashboard') ? 'bg-brand-soft text-brand-navy' : 'text-muted hover:text-ink hover:bg-surface-raised' }}">
                                Dasbor saya
                            </a>
                            <a href="{{ route('customer.verification.create') }}"
                               class="min-h-11 px-3.5 inline-flex items-center rounded-lg transition-colors {{ request()->routeIs('customer.verification.*') ? 'bg-brand-soft text-brand-navy' : 'text-muted hover:text-ink hover:bg-surface-raised' }}">
                                Identitas saya
                            </a>
                        @endif
                    @endauth
                </nav>
            </div>

            <!-- Right Controls + Mobile Menu -->
            <div class="flex items-center gap-2 sm:gap-3">
                @auth
                    <div class="text-right hidden sm:block mr-1">
                        <p class="text-sm font-semibold text-ink leading-5">{{ auth()->user()->name }}</p>
                        <p class="text-xs text-muted leading-4">{{ auth()->user()->email }}</p>
                    </div>
                    <form action="{{ route('logout') }}" method="POST" class="hidden md:block">
                        @csrf
                        <button type="submit"
                                class="min-h-11 px-3.5 inline-flex items-center gap-1.5 text-sm font-medium text-ink bg-surface hover:bg-surface-raised rounded-lg transition-colors border border-field-line cursor-pointer">
                            <svg class="w-4 h-4 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                            </svg>
                            Keluar
                        </button>
                    </form>
                @else
                    <a href="{{ route('login') }}"
                       class="min-h-11 px-3.5 inline-flex items-center justify-center text-sm font-semibold text-ink hover:bg-surface-raised rounded-lg transition-colors">
                        Masuk
                    </a>
                    <a href="{{ route('register') }}"
                       class="min-h-11 px-4 inline-flex items-center justify-center text-sm font-semibold bg-action hover:bg-action-hover text-brand-navy rounded-lg transition-colors">
                        Daftar
                    </a>
                @endauth

                <!-- Mobile Navigation Menu -->
                <details id="portal-mobile-menu" class="relative md:hidden">
                    <summary class="min-h-11 px-3.5 inline-flex items-center gap-1.5 rounded-lg border border-field-line bg-surface hover:bg-surface-raised text-sm font-semibold text-ink cursor-pointer list-none">
                        <svg class="w-4 h-4 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                        Menu
                    </summary>
                    <nav aria-label="Navigasi ponsel" class="absolute right-0 top-full mt-2 z-40 w-64 rounded-xl border border-line bg-surface p-2 shadow-lg space-y-1">
                        <a href="{{ route('cars.index') }}"
                           class="min-h-11 px-3 flex items-center rounded-lg text-sm font-semibold {{ request()->routeIs('cars.*') ? 'bg-brand-soft text-brand-navy' : 'text-ink hover:bg-surface-raised' }}">
                            Katalog mobil
                        </a>
                        @auth
                            @if (auth()->user()->isAdmin())
                                <a href="{{ route('admin.dashboard') }}"
                                   class="min-h-11 px-3 flex items-center rounded-lg text-sm font-semibold {{ request()->routeIs('admin.*') ? 'bg-brand-soft text-brand-navy' : 'text-ink hover:bg-surface-raised' }}">
                                    Ringkasan
                                </a>
                            @elseif (auth()->user()->isStaff())
                                <a href="{{ route('staff.dashboard') }}"
                                   class="min-h-11 px-3 flex items-center rounded-lg text-sm font-semibold {{ request()->routeIs('staff.dashboard') ? 'bg-brand-soft text-brand-navy' : 'text-ink hover:bg-surface-raised' }}">
                                    Status armada
                                </a>
                            @elseif (auth()->user()->isCustomer())
                                <a href="{{ route('customer.dashboard') }}"
                                   class="min-h-11 px-3 flex items-center rounded-lg text-sm font-semibold {{ request()->routeIs('customer.dashboard') ? 'bg-brand-soft text-brand-navy' : 'text-ink hover:bg-surface-raised' }}">
                                    Dasbor saya
                                </a>
                                <a href="{{ route('customer.verification.create') }}"
                                   class="min-h-11 px-3 flex items-center rounded-lg text-sm font-semibold {{ request()->routeIs('customer.verification.*') ? 'bg-brand-soft text-brand-navy' : 'text-ink hover:bg-surface-raised' }}">
                                    Identitas saya
                                </a>
                            @endif
                            <div class="pt-1 mt-1 border-t border-line">
                                <form action="{{ route('logout') }}" method="POST">
                                    @csrf
                                    <button type="submit" class="w-full min-h-11 px-3 flex items-center gap-2 rounded-lg text-sm font-semibold text-danger hover:bg-danger-surface cursor-pointer text-left">
                                        Keluar
                                    </button>
                                </form>
                            </div>
                        @else
                            <div class="pt-1 mt-1 border-t border-line space-y-1">
                                <a href="{{ route('login') }}" class="min-h-11 px-3 flex items-center rounded-lg text-sm font-semibold text-ink hover:bg-surface-raised">
                                    Masuk
                                </a>
                                <a href="{{ route('register') }}" class="min-h-11 px-3 flex items-center rounded-lg text-sm font-semibold bg-action text-brand-navy hover:bg-action-hover">
                                    Daftar
                                </a>
                            </div>
                        @endauth
                    </nav>
                </details>
            </div>
        </div>
    </header>

    <!-- Page Content -->
    <main class="flex-1 max-w-[1200px] w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
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

    <!-- Footer -->
    <footer class="bg-surface border-t border-line py-6 text-sm text-muted">
        <div class="max-w-[1200px] mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-2">
            <span>&copy; {{ date('Y') }} DriveEase &mdash; Sistem Informasi Manajemen Rental Kendaraan.</span>
            <span>Transparan, rapi, dan mudah dipakai.</span>
        </div>
    </footer>
</div>

<script>
    (function () {
        const menu = document.getElementById('portal-mobile-menu');
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
