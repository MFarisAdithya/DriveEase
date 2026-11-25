<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>DriveEase — Sewa Kendaraan Lebih Mudah dan Fleksibel</title>
    <link rel="icon" type="image/png" href="{{ asset('images/driveease-favicon.png') }}">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Vite CSS & JS -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                    }
                }
            }
        }
    </script>
</head>
<body class="font-sans antialiased bg-white text-[#0B2840] overflow-x-clip selection:bg-[#FF6454]/20 selection:text-[#0B2840]">

    <!-- Sticky Frosted Navigation Bar -->
    <header class="sticky top-0 z-40 w-full backdrop-blur-md bg-white/80 border-b border-[#D7EAF6]/80">
        <div class="max-w-7xl mx-auto px-4 sm:px-10 lg:px-14 h-20 flex items-center justify-between gap-2.5 sm:gap-6">
            <a href="{{ route('home') }}" class="inline-flex items-center rounded-lg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#FF6454] shrink-0">
                @include('partials.driveease-logo', [
                    'sizeClass' => 'h-9 sm:h-14 lg:h-16',
                    'haloSize' => 'w-9 h-9 sm:w-14 sm:h-14',
                ])
            </a>

            <nav class="hidden md:flex items-center gap-5 lg:gap-8 text-sm font-semibold text-[#3C5266] whitespace-nowrap" aria-label="Navigasi Utama">
                <a href="#beranda" class="hover:text-[#0B2840] transition-colors whitespace-nowrap rounded focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#FF6454]">Beranda</a>
                <a href="#keunggulan" class="hover:text-[#0B2840] transition-colors whitespace-nowrap rounded focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#FF6454]">Keunggulan</a>
                <a href="#armada" class="hover:text-[#0B2840] transition-colors whitespace-nowrap rounded focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#FF6454]">Armada Pilihan</a>
                <a href="#cara-sewa" class="hover:text-[#0B2840] transition-colors whitespace-nowrap rounded focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#FF6454]">Cara Sewa</a>
            </nav>

            <div class="flex items-center gap-1.5 sm:gap-3.5 shrink-0">
                <a href="{{ route('login') }}"
                   class="text-xs sm:text-sm font-semibold text-[#0B2840] hover:text-[#FF6454] px-2.5 sm:px-3.5 py-2 whitespace-nowrap rounded-lg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#FF6454] transition-colors">
                    Masuk
                </a>
                <a href="{{ route('register') }}"
                   class="text-xs sm:text-sm font-semibold bg-[#0B2840] hover:bg-[#133b5c] text-white px-3.5 sm:px-5 py-2 sm:py-2.5 rounded-full shadow-sm whitespace-nowrap focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#FF6454] transition-all active:scale-[0.98]">
                    <span class="sm:hidden">Daftar</span>
                    <span class="hidden sm:inline">Daftar Akun</span>
                </a>

                {{-- Elemen details menyediakan menu navigasi mobile tanpa JavaScript agar ramah keyboard (Tab + Enter). --}}
                <details class="relative md:hidden">
                    <summary class="list-none inline-flex items-center gap-1 rounded-full border border-[#D7EAF6] bg-white px-3 py-2 text-xs font-semibold text-[#0B2840] cursor-pointer focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#FF6454]"
                             aria-label="Buka menu navigasi">
                        Menu
                    </summary>
                    <nav aria-label="Navigasi Mobile" class="absolute right-0 top-full z-50 mt-2 w-52 rounded-2xl border border-[#D7EAF6] bg-white p-2 shadow-lg grid gap-1 text-xs font-semibold text-[#3C5266]">
                        <a href="#beranda" class="rounded-lg px-3 py-2.5 hover:bg-[#F4FAFE] hover:text-[#0B2840]">Beranda</a>
                        <a href="#keunggulan" class="rounded-lg px-3 py-2.5 hover:bg-[#F4FAFE] hover:text-[#0B2840]">Keunggulan</a>
                        <a href="#armada" class="rounded-lg px-3 py-2.5 hover:bg-[#F4FAFE] hover:text-[#0B2840]">Armada Pilihan</a>
                        <a href="#cara-sewa" class="rounded-lg px-3 py-2.5 hover:bg-[#F4FAFE] hover:text-[#0B2840]">Cara Sewa</a>
                        <a href="{{ route('cars.index') }}" class="rounded-lg px-3 py-2.5 hover:bg-[#F4FAFE] hover:text-[#0B2840]">Katalog Mobil</a>
                    </nav>
                </details>
            </div>
        </div>
    </header>

    <!-- BAND 1: Signature Hero Showcase (Matches Reference Visual & Smoothly Animates) -->
    <section id="beranda"
             class="relative overflow-hidden min-h-[calc(100vh-5rem)] flex flex-col justify-between pb-24 sm:pb-32"
             style="background: linear-gradient(122deg, #FFFFFF 0%, #F4FAFE 36%, #DFF1FC 70%, #CBE8F8 100%);">

        @include('partials.driveease-backdrop')

        <div class="relative z-10 flex-1 flex items-center">
            <div class="max-w-7xl w-full mx-auto px-5 sm:px-10 lg:px-14 py-10 lg:py-14">
                <div class="grid grid-cols-1 md:grid-cols-12 items-center gap-10 md:gap-6">

                    <!-- Left Column: Copy, Dual CTA, 4 Highlights -->
                    <div class="md:col-span-6 lg:col-span-5">
                        <h1 class="text-[2.45rem] sm:text-[3rem] md:text-[2.75rem] lg:text-[3.4rem] xl:text-[3.7rem] font-extrabold text-[#0B2840] leading-[1.07] tracking-[-0.03em] text-balance">
                            Sewa Kendaraan<br>
                            Lebih Mudah<br>
                            dan
                            <span class="relative inline-block text-[#FF6454]">
                                Fleksibel
                                <svg class="absolute -top-3.5 -right-7 w-7 h-7 text-[#FF6454] animate-driveease-spark" viewBox="0 0 32 32" fill="none" aria-hidden="true">
                                    <path d="M6 12L12 4" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"/>
                                    <path d="M11 18L22 13" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"/>
                                    <path d="M12 24L21 24" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"/>
                                </svg>
                            </span>
                        </h1>

                        <p class="mt-5 text-[15px] sm:text-base text-[#4A5E72] leading-relaxed max-w-[410px]">
                            Temukan berbagai pilihan mobil dan motor, proses cepat, aman, dan terpercaya.
                        </p>

                        <!-- Action Buttons -->
                        <div class="mt-8 flex flex-wrap items-center gap-3.5">
                            <a href="{{ route('login') }}"
                               class="driveease-btn-sheen inline-flex items-center gap-2.5 px-7 py-3.5 rounded-full bg-[#FF6454] hover:bg-[#EE5242] active:scale-[0.98] text-white text-sm font-semibold shadow-[0_10px_24px_rgba(255,100,84,0.32)] transition-all duration-150">
                                <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                    <path d="M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11c-.66 0-1.21.42-1.42 1.01L3 12v8c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h12v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-8l-2.08-5.99zM6.5 16c-.83 0-1.5-.67-1.5-1.5S5.67 13 6.5 13s1.5.67 1.5 1.5S7.33 16 6.5 16zm11 0c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zM5 11l1.5-4.5h11L19 11H5z"/>
                                </svg>
                                <span>Sewa Sekarang</span>
                                <span aria-hidden="true">&rarr;</span>
                            </a>

                            <a href="{{ route('cars.index') }}"
                               class="inline-flex items-center justify-center px-7 py-3.5 rounded-full border-[1.5px] border-[#0B2840] bg-white/75 hover:bg-white active:scale-[0.98] text-[#0B2840] text-sm font-semibold shadow-xs transition-all duration-150">
                                Lihat Kendaraan
                            </a>
                        </div>

                        <!-- 4 Trust Highlights -->
                        <div class="mt-11 grid grid-cols-2 sm:grid-cols-4 gap-x-4 gap-y-5 max-w-[540px]">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 shrink-0 flex items-center justify-center">
                                    <svg class="w-7 h-7" viewBox="0 0 28 28" fill="none" aria-hidden="true">
                                        <rect x="3" y="5" width="18" height="17" rx="3" stroke="#0B2840" stroke-width="1.8"/>
                                        <path d="M3 10.5H21" stroke="#0B2840" stroke-width="1.8"/>
                                        <path d="M8 3V6.5M16 3V6.5" stroke="#0B2840" stroke-width="1.8" stroke-linecap="round"/>
                                        <circle cx="20.5" cy="20.5" r="4.5" fill="#F4FAFE"/>
                                        <path d="M17.5 20.5L19.5 22.5L23.5 18.5" stroke="#FF6454" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </div>
                                <span class="text-xs font-bold leading-tight text-[#0B2840]">Booking<br>Mudah</span>
                            </div>

                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 shrink-0 flex items-center justify-center">
                                    <svg class="w-7 h-7" viewBox="0 0 28 28" fill="none" aria-hidden="true">
                                        <path d="M14 3L5 6.8V13.4C5 19.2 8.84 24.42 14 25.8C19.16 24.42 23 19.2 23 13.4V6.8L14 3Z" stroke="#0B2840" stroke-width="1.8" stroke-linejoin="round"/>
                                        <path d="M10.5 14L13 16.5L18 11.5" stroke="#FF6454" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </div>
                                <span class="text-xs font-bold leading-tight text-[#0B2840]">Aman &amp;<br>Terpercaya</span>
                            </div>

                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 shrink-0 flex items-center justify-center">
                                    <svg class="w-7 h-7" viewBox="0 0 28 28" fill="none" aria-hidden="true">
                                        <path d="M15.5 3L7 15.5H14L12.5 25L21 12.5H14L15.5 3Z" fill="#FF6454"/>
                                    </svg>
                                </div>
                                <span class="text-xs font-bold leading-tight text-[#0B2840]">Proses<br>Cepat</span>
                            </div>

                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 shrink-0 flex items-center justify-center">
                                    <svg class="w-7 h-7" viewBox="0 0 28 28" fill="none" aria-hidden="true">
                                        <path d="M14.6 4.5H21.5C22.6 4.5 23.5 5.4 23.5 6.5V13.4C23.5 14 23.26 14.58 22.83 15L13.6 24.23C12.82 25.01 11.55 25.01 10.77 24.23L3.77 17.23C2.99 16.45 2.99 15.18 3.77 14.4L13 5.17C13.42 4.74 14 4.5 14.6 4.5Z" stroke="#0B2840" stroke-width="1.8" stroke-linejoin="round"/>
                                        <circle cx="18.5" cy="9.5" r="1.4" fill="#0B2840"/>
                                        <path d="M10.5 17.5L15.5 12.5" stroke="#FF6454" stroke-width="1.8" stroke-linecap="round"/>
                                        <circle cx="11" cy="13" r="1" fill="#FF6454"/>
                                        <circle cx="15" cy="17" r="1" fill="#FF6454"/>
                                    </svg>
                                </div>
                                <span class="text-xs font-bold leading-tight text-[#0B2840]">Harga<br>Fleksibel</span>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Floating White SUV + White Scooter Visual -->
                    <div class="md:col-span-6 lg:col-span-7 relative flex items-center justify-center md:justify-end">
                        <!-- Subtle Soft Radial Spotlight Behind Vehicles -->
                        <div class="pointer-events-none absolute inset-x-10 top-6 bottom-6 rounded-full opacity-70 blur-3xl"
                             style="background: radial-gradient(circle, rgba(255,255,255,0.95) 0%, rgba(182,223,237,0.35) 60%, transparent 100%);"
                             aria-hidden="true"></div>

                        <img src="{{ asset('images/hero-vehicles.png') }}"
                             alt="Pilihan Mobil SUV dan Motor Matic DriveEase"
                             class="relative z-10 w-full max-w-[760px] md:scale-105 lg:scale-100 h-auto object-contain select-none pointer-events-none animate-driveease-float drop-shadow-[0_22px_32px_rgba(11,40,64,0.12)]">
                    </div>

                </div>
            </div>
        </div>
    </section>

    <!-- BAND 2: Architectural Value Bento Grid -->
    <section id="keunggulan" class="relative bg-white py-20 sm:py-24">
        <div class="max-w-7xl mx-auto px-5 sm:px-10 lg:px-14">
            <div class="max-w-2xl mb-12">
                <span class="text-xs font-bold uppercase tracking-[0.14em] text-[#FF6454]">Keunggulan DriveEase</span>
                <h2 class="mt-2.5 text-3xl sm:text-4xl font-extrabold text-[#0B2840] tracking-[-0.025em] leading-[1.15] text-balance">
                    Standar Rental Modern yang Aman, Jelas, dan Terstruktur
                </h2>
                <p class="mt-3 text-base text-[#4A5E72] leading-relaxed">
                    Setiap unit kendaraan, dokumen pelanggan, hingga status ketersediaan terkelola secara rapi dalam satu sistem terintegrasi.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-12 gap-5">
                <!-- Wide Bento Card -->
                <div class="md:col-span-7 rounded-3xl bg-[#0B2840] text-white p-8 sm:p-10 relative overflow-hidden flex flex-col justify-between shadow-[0_18px_45px_rgba(11,40,64,0.12)]">
                    <div class="pointer-events-none absolute -right-12 -bottom-12 w-64 h-64 rounded-full opacity-25"
                         style="background: radial-gradient(circle, #369CC7 0%, transparent 70%);" aria-hidden="true"></div>
                    <div class="relative z-10">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white/10 border border-white/15 text-xs font-semibold text-[#9ADAF6]">
                            Armada Prima
                        </span>
                        <h3 class="mt-4 text-2xl sm:text-3xl font-bold tracking-tight">
                            Katalog Armada
                        </h3>
                        <p class="mt-3 text-sm sm:text-base text-[#C4DFEE] leading-relaxed max-w-lg">
                            Cari kendaraan berdasarkan kategori, transmisi otomatis maupun manual, kapasitas penumpang, serta pantau status ketersediaan unit secara akurat.
                        </p>
                    </div>
                    <div class="relative z-10 mt-8 pt-6 border-t border-white/15 flex flex-wrap items-center gap-6 text-xs font-semibold text-[#E2F2F8]">
                        <span class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-[#FF6454]"></span> Pengecekan Unit Rutin</span>
                        <span class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-[#369CC7]"></span> Spesifikasi Lengkap</span>
                        <span class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-emerald-400"></span> Tarif Transparan</span>
                    </div>
                </div>

                <!-- Security Bento Card -->
                <div class="md:col-span-5 rounded-3xl bg-[#F4FAFE] border border-[#CFE4F2] p-8 flex flex-col justify-between hover:border-[#8BC4E6] transition-colors">
                    <div>
                        <div class="w-12 h-12 rounded-2xl bg-white border border-[#D4E8F5] shadow-xs flex items-center justify-center text-[#0B2840]">
                            <svg class="w-6 h-6 text-[#FF6454]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                        </div>
                        <h3 class="mt-5 text-xl font-bold text-[#0B2840]">Penyimpanan KTP &amp; SIM Privat</h3>
                        <p class="mt-2.5 text-sm text-[#4A5E72] leading-relaxed">
                            Foto dokumen identitas pelanggan (KTP 16 digit &amp; SIM 14 digit) disimpan terisolasi di private storage dengan otorisasi akses ketat.
                        </p>
                    </div>
                    <div class="mt-6 text-xs font-bold text-[#0B2840] flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-[#FF6454]"></span>
                        Terproteksi Otorisasi Role
                    </div>
                </div>

                <!-- Transparency Card -->
                <div class="md:col-span-5 rounded-3xl bg-[#F4FAFE] border border-[#CFE4F2] p-8 hover:border-[#8BC4E6] transition-colors">
                    <div class="w-12 h-12 rounded-2xl bg-white border border-[#D4E8F5] shadow-xs flex items-center justify-center text-[#0B2840]">
                        <svg class="w-6 h-6 text-[#369CC7]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <h3 class="mt-5 text-xl font-bold text-[#0B2840]">Tarif Harian Jelas Sejak Awal</h3>
                    <p class="mt-2.5 text-sm text-[#4A5E72] leading-relaxed">
                        Setiap kendaraan menampilkan rincian harga sewa harian yang terbuka sehingga Anda dapat membandingkan pilihan sesuai anggaran.
                    </p>
                </div>

                <!-- Workflow Card -->
                <div class="md:col-span-7 rounded-3xl bg-white border border-[#CFE4F2] p-8 shadow-xs hover:border-[#8BC4E6] transition-colors flex flex-col sm:flex-row sm:items-center justify-between gap-6">
                    <div class="max-w-md">
                        <div class="w-12 h-12 rounded-2xl bg-[#FFF0EE] border border-[#FFD6D1] flex items-center justify-center text-[#FF6454]">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <h3 class="mt-5 text-xl font-bold text-[#0B2840]">Proses Verifikasi &amp; Perbaikan Cepat</h3>
                        <p class="mt-2.5 text-sm text-[#4A5E72] leading-relaxed">
                            Status verifikasi langsung tampil di dasbor pelanggan. Jika terdapat kekurangan berkas, alasan penolakan ditampilkan jelas untuk dikirim ulang seketika.
                        </p>
                    </div>
                    <div class="shrink-0">
                        <a href="{{ route('register') }}"
                           class="inline-flex items-center gap-2 px-5 py-3 rounded-full bg-[#0B2840] hover:bg-[#143c5e] text-white text-xs font-bold transition-colors">
                            Buat Akun Gratis &rarr;
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- BAND 3: Live Featured Vehicles Showcase -->
    <section id="armada" class="relative py-20 sm:py-24 bg-[#F4FAFE] border-y border-[#D8EAF5]">
        <div class="max-w-7xl mx-auto px-5 sm:px-10 lg:px-14">
            <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-12">
                <div>
                    <span class="text-xs font-bold uppercase tracking-[0.14em] text-[#FF6454]">Armada Pilihan</span>
                    <h2 class="mt-2 text-3xl sm:text-4xl font-extrabold text-[#0B2840] tracking-[-0.025em]">
                        Koleksi Kendaraan DriveEase
                    </h2>
                    <p class="mt-2 text-sm sm:text-base text-[#4A5E72]">
                        Pilihan unit terpopuler yang tersedia untuk berbagai kebutuhan perjalanan Anda.
                    </p>
                </div>
                <a href="{{ route('cars.index') }}"
                   class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full border border-[#0B2840] text-[#0B2840] bg-white hover:bg-[#0B2840] hover:text-white text-xs font-bold transition-all shrink-0 self-start sm:self-auto">
                    Jelajahi Semua Katalog &rarr;
                </a>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                @forelse ($featuredCars as $car)
                    @php
                        $photo = $car->resolvePhoto();
                    @endphp
                    <article class="group rounded-3xl bg-white border border-[#CFE4F2] shadow-xs hover:shadow-[0_18px_40px_rgba(11,40,64,0.10)] hover:-translate-y-1 transition-all duration-200 overflow-hidden flex flex-col">
                        <figure class="relative aspect-[16/10] bg-[#E8F4FA] overflow-hidden flex items-center justify-center">
                            @if ($photo['url'])
                                <img src="{{ $photo['url'] }}"
                                     alt="{{ $car->name }}"
                                     loading="lazy"
                                     class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                            @else
                                <span class="text-xs font-medium text-[#5B748B]">Foto armada</span>
                            @endif

                            @php
                                $statusLabel = match ($car->status) {
                                    'available' => 'Tersedia',
                                    'maintenance' => 'Dalam Perawatan',
                                    default => 'Sedang Disewa',
                                };
                                $statusBadgeClass = $car->status === 'available'
                                    ? 'bg-emerald-500/90 text-white'
                                    : ($car->status === 'maintenance' ? 'bg-amber-600/90 text-white' : 'bg-[#0B2840]/85 text-white');
                            @endphp
                            <span class="absolute top-3.5 left-3.5 px-3 py-1 rounded-full text-[11px] font-bold backdrop-blur-sm {{ $statusBadgeClass }}">
                                {{ $statusLabel }}
                            </span>
                        </figure>

                        <div class="p-6 flex-1 flex flex-col">
                            <p class="text-xs font-bold uppercase tracking-wider text-[#369CC7]">
                                {{ $car->brand }} &bull; {{ $car->carType?->name ?? 'Mobil' }} &bull; {{ $car->model_year }}
                            </p>
                            <h3 class="mt-1.5 text-lg font-extrabold text-[#0B2840] leading-snug">
                                {{ $car->name }}
                            </h3>

                            <div class="mt-4 flex items-center gap-2 text-xs text-[#4A5E72] font-medium">
                                <span class="px-2.5 py-1 rounded-lg bg-[#F4FAFE] border border-[#DCECF6] capitalize">
                                    {{ $car->transmission === 'automatic' ? 'Otomatis' : 'Manual' }}
                                </span>
                                <span class="px-2.5 py-1 rounded-lg bg-[#F4FAFE] border border-[#DCECF6]">
                                    {{ $car->capacity }} Kursi
                                </span>
                            </div>
                        </div>

                        <div class="px-6 py-4 bg-[#0B2840] text-white flex items-center justify-between">
                            <div>
                                <span class="text-[10px] uppercase tracking-wider text-[#9ADAF6] block">Tarif Harian</span>
                                <span class="text-base font-extrabold">Rp {{ number_format($car->price_per_day, 0, ',', '.') }}</span>
                                <span class="text-xs text-[#9ADAF6]">/ hari</span>
                            </div>
                            <a href="{{ route('cars.show', $car) }}"
                               class="inline-flex items-center gap-1.5 px-4 py-2 rounded-full bg-[#FF6454] hover:bg-[#EE5242] text-white text-xs font-bold transition-colors">
                                Detail &rarr;
                            </a>
                        </div>
                    </article>
                @empty
                    <div class="col-span-full rounded-3xl bg-white border border-[#CFE4F2] p-10 text-center text-sm text-[#4A5E72]">
                        Katalog armada sedang disiapkan. Kunjungi <a href="{{ route('cars.index') }}" class="font-bold text-[#0B2840] underline">halaman katalog</a> untuk melihat daftar terbaru.
                    </div>
                @endforelse
            </div>
        </div>
    </section>

    <!-- BAND 4: 3-Step Rental Workflow -->
    <section id="cara-sewa" class="bg-white py-20 sm:py-24">
        <div class="max-w-7xl mx-auto px-5 sm:px-10 lg:px-14">
            <div class="text-center max-w-2xl mx-auto mb-14">
                <span class="text-xs font-bold uppercase tracking-[0.14em] text-[#FF6454]">Alur Cepat</span>
                <h2 class="mt-2.5 text-3xl sm:text-4xl font-extrabold text-[#0B2840] tracking-[-0.025em]">
                    Mulai Berkendara dalam 3 Langkah
                </h2>
                <p class="mt-3 text-sm sm:text-base text-[#4A5E72]">
                    Dari pendaftaran akun hingga verifikasi identitas dirancang ringkas dan jelas.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="rounded-3xl border border-[#D8EAF5] bg-[#F8FCFF] p-8 relative">
                    <span class="inline-flex items-center justify-center w-11 h-11 rounded-2xl bg-[#0B2840] text-white font-extrabold text-sm">01</span>
                    <h3 class="mt-5 text-lg font-bold text-[#0B2840]">Daftar &amp; Unggah Dokumen</h3>
                    <p class="mt-2 text-sm text-[#4A5E72] leading-relaxed">
                        Buat akun pelanggan dan lengkapi NIK KTP (16 digit) beserta nomor SIM (14 digit) serta foto dokumen asli.
                    </p>
                </div>

                <div class="rounded-3xl border border-[#D8EAF5] bg-[#F8FCFF] p-8 relative">
                    <span class="inline-flex items-center justify-center w-11 h-11 rounded-2xl bg-[#369CC7] text-white font-extrabold text-sm">02</span>
                    <h3 class="mt-5 text-lg font-bold text-[#0B2840]">Verifikasi Tim Admin</h3>
                    <p class="mt-2 text-sm text-[#4A5E72] leading-relaxed">
                        Administrator memeriksa kecocokan data secara privat. Status akun langsung terbarui di dasbor pelanggan Anda.
                    </p>
                </div>

                <div class="rounded-3xl border border-[#D8EAF5] bg-[#F8FCFF] p-8 relative">
                    <span class="inline-flex items-center justify-center w-11 h-11 rounded-2xl bg-[#FF6454] text-white font-extrabold text-sm">03</span>
                    <h3 class="mt-5 text-lg font-bold text-[#0B2840]">Pilih Unit &amp; Siap Jalan</h3>
                    <p class="mt-2 text-sm text-[#4A5E72] leading-relaxed">
                        Setelah terverifikasi, pilih armada yang tersedia sesuai kapasitas dan transmisi yang Anda butuhkan.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- BAND 5: Grand Deep-Navy CTA Banner & Footer -->
    <footer class="relative overflow-hidden bg-[#0B2840] text-white">
        <!-- Translucent Architectural Highlight on Dark Navy -->
        <div class="pointer-events-none absolute -top-28 -right-28 w-96 h-96 rounded-full opacity-20 animate-driveease-aurora"
             style="background: radial-gradient(circle, #369CC7 0%, transparent 70%);" aria-hidden="true"></div>

        <div class="max-w-7xl mx-auto px-5 sm:px-10 lg:px-14 pt-16 pb-12 relative z-10">
            <div class="rounded-3xl bg-white/[0.06] border border-white/10 p-8 sm:p-12 flex flex-col lg:flex-row lg:items-center justify-between gap-8 backdrop-blur-sm">
                <div class="max-w-xl">
                    <span class="text-xs font-bold uppercase tracking-[0.14em] text-[#FF6454]">Mulai Perjalanan Anda</span>
                    <h2 class="mt-2 text-2xl sm:text-4xl font-extrabold tracking-tight leading-tight">
                        Siap Sewa Kendaraan Tanpa Ribet Bersama DriveEase?
                    </h2>
                    <p class="mt-3 text-sm sm:text-base text-[#B6DFED]">
                        Masuk ke portal pelanggan atau lihat seluruh katalog kendaraan yang siap disewa hari ini.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-3.5 shrink-0">
                    <a href="{{ route('register') }}"
                       class="driveease-btn-sheen inline-flex items-center gap-2 px-7 py-3.5 rounded-full bg-[#FF6454] hover:bg-[#EE5242] text-white text-sm font-bold shadow-[0_10px_24px_rgba(255,100,84,0.35)] transition-all">
                        Daftar Sekarang &rarr;
                    </a>
                    <a href="{{ route('cars.index') }}"
                       class="inline-flex items-center px-6 py-3.5 rounded-full border border-white/30 hover:bg-white/10 text-white text-sm font-semibold transition-colors">
                        Lihat Katalog
                    </a>
                </div>
            </div>

            @if ($rentalInfo)
                <section id="informasi-usaha" class="mt-10 rounded-2xl border border-white/10 bg-white/[0.04] p-6 sm:p-8 space-y-4 text-sm text-[#B6DFED]">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <h2 class="text-lg font-bold text-white">{{ $rentalInfo->business_name }}</h2>
                            <p class="whitespace-pre-line leading-relaxed">{{ $rentalInfo->services }}</p>
                            <p>{{ $rentalInfo->address }}</p>
                            <p>{{ $rentalInfo->contact }}</p>
                            <p>Jam operasional {{ str_contains(strtoupper($rentalInfo->operating_hours), 'WIB') ? $rentalInfo->operating_hours : $rentalInfo->operating_hours.' WIB' }}</p>
                        </div>
                        <div class="space-y-2">
                            <h3 class="font-semibold text-white">Ketentuan rental</h3>
                            <p class="whitespace-pre-line leading-relaxed">{{ $rentalInfo->terms }}</p>
                        </div>
                    </div>
                </section>
            @endif
            <div class="mt-12 pt-8 border-t border-white/10 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-[#9ADAF6]">
                <div class="flex items-center gap-3">
                    <span class="font-bold text-white text-sm tracking-tight">DriveEase</span>
                    <span class="text-white/30">&bull;</span>
                    <span>&copy; {{ date('Y') }} Sistem Informasi Manajemen Rental Kendaraan.</span>
                </div>
                <div class="flex items-center gap-5 font-medium">
                    <a href="{{ route('cars.index') }}" class="hover:text-white transition-colors">Katalog</a>
                    <a href="{{ route('login') }}" class="hover:text-white transition-colors">Masuk</a>
                    <a href="{{ route('register') }}" class="hover:text-white transition-colors">Daftar</a>
                </div>
            </div>
        </div>

        <!-- Signature Bottom Coral Accent Bar -->
        <div class="h-1.5 w-full bg-gradient-to-r from-[#0B2840] via-[#369CC7] to-[#FF6454]"></div>
    </footer>

</body>
</html>
