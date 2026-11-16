@extends('layouts.app')

@section('body')
<div class="min-h-screen bg-canvas text-ink flex flex-col">
    <!-- Top Navbar -->
    <header class="bg-navigation border-b border-field-line text-ink sticky top-0 z-30">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <!-- Brand & Navigation -->
            <div class="flex items-center gap-6">
                <a href="{{ route('cars.index') }}" class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-action flex items-center justify-center font-bold text-ink shadow-sm">
                        DE
                    </div>
                    <div>
                        <span class="font-bold text-lg text-ink tracking-tight">DriveEase</span>
                        @auth
                            <span class="ml-2 px-2 py-0.5 rounded text-xs font-medium bg-surface-hover text-ink border border-field-line">
                                {{ auth()->user()->role?->display_name ?? 'User' }}
                            </span>
                        @endauth
                    </div>
                </a>

                <!-- Navigation Links -->
                <nav class="hidden md:flex items-center gap-4 text-xs font-semibold">
                    <a href="{{ route('cars.index') }}" class="px-3 py-1.5 rounded-lg {{ request()->routeIs('cars.*') ? 'bg-surface-hover text-ink' : 'text-subtle hover:text-ink' }} transition-colors">
                        Katalog mobil
                    </a>
                    @auth
                        @if(auth()->user()->isAdmin())
                            <a href="{{ route('admin.dashboard') }}" class="px-3 py-1.5 rounded-lg text-subtle hover:text-ink transition-colors">
                                Ringkasan admin
                            </a>
                        @elseif(auth()->user()->isStaff())
                            <a href="{{ route('staff.dashboard') }}" class="px-3 py-1.5 rounded-lg {{ request()->routeIs('staff.dashboard') ? 'bg-surface-hover text-ink' : 'text-subtle hover:text-ink' }} transition-colors">
                                Dasbor staf
                            </a>
                        @elseif(auth()->user()->isCustomer())
                            <a href="{{ route('customer.dashboard') }}" class="px-3 py-1.5 rounded-lg {{ request()->routeIs('customer.dashboard') ? 'bg-surface-hover text-ink' : 'text-subtle hover:text-ink' }} transition-colors">
                                Dasbor saya
                            </a>
                            <a href="{{ route('customer.verification.create') }}" class="px-3 py-1.5 rounded-lg {{ request()->routeIs('customer.verification.*') ? 'bg-surface-hover text-ink' : 'text-subtle hover:text-ink' }} transition-colors">
                                Identitas saya
                            </a>
                        @endif
                    @endauth
                </nav>
            </div>

            <!-- User Menu / Auth Buttons -->
            <div class="flex items-center gap-3">
                @auth
                    <div class="text-right hidden sm:block">
                        <p class="text-xs font-semibold text-ink">{{ auth()->user()->name }}</p>
                        <p class="text-[11px] text-muted">{{ auth()->user()->email }}</p>
                    </div>
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-subtle bg-surface-hover hover:bg-danger-surface/60 hover:text-ink rounded-lg transition-colors border border-field-line">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                            </svg>
                            Keluar
                        </button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="px-3.5 py-1.5 text-xs font-semibold text-subtle hover:text-ink transition-colors">
                        Masuk
                    </a>
                    <a href="{{ route('register') }}" class="px-3.5 py-1.5 text-xs font-semibold bg-action hover:bg-action-hover text-ink rounded-lg transition-colors shadow-sm">
                        Daftar
                    </a>
                @endauth
            </div>
        </div>
    </header>

    <!-- Page Content -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Flash Messages -->
        @if (session('success'))
            <div class="mb-6 p-4 rounded-lg bg-success-surface border border-success-line flex items-start gap-3 text-success">
                <svg class="w-5 h-5 text-success shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <div class="text-sm font-medium">{{ session('success') }}</div>
            </div>
        @endif

        @if (session('error'))
            <div class="mb-6 p-4 rounded-lg bg-danger-surface border border-danger-line flex items-start gap-3 text-danger">
                <svg class="w-5 h-5 text-danger shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <div class="text-sm font-medium">{{ session('error') }}</div>
            </div>
        @endif

        @yield('content')
    </main>

    <!-- Footer -->
    <footer class="bg-navigation border-t border-line py-6 text-center text-xs text-muted">
        <div class="max-w-7xl mx-auto px-4 flex flex-col sm:flex-row items-center justify-between gap-2">
            <span>&copy; {{ date('Y') }} DriveEase — Sistem Informasi Manajemen Rental Kendaraan.</span>
            <span class="text-muted">Katalog mobil dan pengelolaan rental.</span>
        </div>
    </footer>
</div>
@endsection
