@extends('layouts.app')

@section('body')
<div class="min-h-screen {{ request()->routeIs('cars.index') ? 'bg-[#191410] [color-scheme:dark]' : 'bg-[#F2EDE4]' }} flex flex-col">
    <!-- Top Navbar -->
    <header class="bg-[#150F0B] border-b border-slate-800 text-white sticky top-0 z-30">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <!-- Brand & Navigation -->
            <div class="flex items-center gap-6">
                <a href="{{ route('cars.index') }}" class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-[#935831] flex items-center justify-center font-bold text-white shadow-sm">
                        DE
                    </div>
                    <div>
                        <span class="font-bold text-lg text-white tracking-tight">DriveEase</span>
                        @auth
                            <span class="ml-2 px-2 py-0.5 rounded text-xs font-medium bg-[#34271D] text-[#E7D6B8] border border-slate-700">
                                {{ auth()->user()->role?->display_name ?? 'User' }}
                            </span>
                        @endauth
                    </div>
                </a>

                <!-- Navigation Links -->
                <nav class="hidden md:flex items-center gap-4 text-xs font-semibold">
                    <a href="{{ route('cars.index') }}" class="px-3 py-1.5 rounded-lg {{ request()->routeIs('cars.*') ? 'bg-[#34271D] text-[#E7D6B8]' : 'text-slate-300 hover:text-white' }} transition-colors">
                        Katalog Armada
                    </a>
                    @auth
                        @if(auth()->user()->isAdmin())
                            <a href="{{ route('admin.dashboard') }}" class="px-3 py-1.5 rounded-lg text-slate-300 hover:text-white transition-colors">
                                Admin Dashboard
                            </a>
                        @elseif(auth()->user()->isStaff())
                            <a href="{{ route('staff.dashboard') }}" class="px-3 py-1.5 rounded-lg {{ request()->routeIs('staff.dashboard') ? 'bg-[#34271D] text-[#E7D6B8]' : 'text-slate-300 hover:text-white' }} transition-colors">
                                Dashboard Staf
                            </a>
                        @elseif(auth()->user()->isCustomer())
                            <a href="{{ route('customer.dashboard') }}" class="px-3 py-1.5 rounded-lg {{ request()->routeIs('customer.dashboard') ? 'bg-[#34271D] text-[#E7D6B8]' : 'text-slate-300 hover:text-white' }} transition-colors">
                                Dashboard Saya
                            </a>
                            <a href="{{ route('customer.verification.create') }}" class="px-3 py-1.5 rounded-lg {{ request()->routeIs('customer.verification.*') ? 'bg-[#34271D] text-[#E7D6B8]' : 'text-slate-300 hover:text-white' }} transition-colors">
                                Verifikasi Dokumen
                            </a>
                        @endif
                    @endauth
                </nav>
            </div>

            <!-- User Menu / Auth Buttons -->
            <div class="flex items-center gap-3">
                @auth
                    <div class="text-right hidden sm:block">
                        <p class="text-xs font-semibold text-white">{{ auth()->user()->name }}</p>
                        <p class="text-[11px] text-slate-400">{{ auth()->user()->email }}</p>
                    </div>
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-slate-200 bg-[#34271D] hover:bg-rose-900/60 hover:text-white rounded-lg transition-colors border border-slate-700">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                            </svg>
                            Keluar
                        </button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="px-3.5 py-1.5 text-xs font-semibold text-slate-300 hover:text-white transition-colors">
                        Masuk
                    </a>
                    <a href="{{ route('register') }}" class="px-3.5 py-1.5 text-xs font-semibold bg-[#935831] hover:bg-[#844F2C] text-white rounded-lg transition-colors shadow-sm">
                        Daftar Akun
                    </a>
                @endauth
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
    <footer class="bg-[#150F0B] border-t border-[#49392D] py-6 text-center text-xs text-[#CCBDAC]">
        <div class="max-w-7xl mx-auto px-4 flex flex-col sm:flex-row items-center justify-between gap-2">
            <span>&copy; {{ date('Y') }} DriveEase — Sistem Informasi Manajemen Rental Kendaraan.</span>
            <span class="text-slate-400">Armada Prima, Perjalanan Nyaman.</span>
        </div>
    </footer>
</div>
@endsection
