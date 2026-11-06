@extends('layouts.portal')

@section('title', 'Dashboard Pelanggan - DriveEase')

@section('content')
<div class="space-y-6">
    <!-- Welcome Banner -->
    <div class="bg-white rounded-xl p-6 border border-slate-200 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-slate-900">Selamat Datang, {{ auth()->user()->name }}!</h2>
            <p class="text-sm text-slate-500 mt-1">
                Pilihan armada kendaraan rental prima yang siap menemani perjalanan dinas, keluarga, atau liburan Anda.
            </p>
        </div>
        <div>
            <a href="{{ route('customer.verification.create') }}" class="inline-flex items-center gap-2 px-4 py-2 text-xs font-semibold rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 shadow-xs transition-colors">
                <svg class="w-4 h-4 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Status Identitas
            </a>
        </div>
    </div>

    <!-- Verification Status Banner -->
    @php
        $user = auth()->user();
    @endphp

    @if ($user->isVerified())
        <div class="p-5 rounded-xl bg-emerald-50 border border-emerald-200 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-start sm:items-center gap-3.5">
                <div class="w-10 h-10 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="text-sm font-bold text-emerald-900">Akun Terverifikasi</h3>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-200 text-emerald-900 uppercase tracking-wider">Aktif</span>
                    </div>
                    <p class="text-xs text-emerald-800 mt-0.5">
                        Identitas KTP &amp; SIM Anda telah disetujui. Anda memenuhi syarat penuh untuk melakukan reservasi rental kendaraan.
                    </p>
                </div>
            </div>
            <a href="{{ route('customer.verification.create') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-emerald-900 bg-white border border-emerald-300 rounded-lg hover:bg-emerald-50 transition-colors shrink-0">
                Lihat Detail Dokumen
            </a>
        </div>
    @elseif ($user->isRejectedVerification())
        <div class="p-5 rounded-xl bg-rose-50 border border-rose-200 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-start gap-3.5">
                <div class="w-10 h-10 rounded-lg bg-rose-100 text-rose-700 flex items-center justify-center shrink-0 mt-0.5">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="text-sm font-bold text-rose-900">Verifikasi Identitas Ditolak</h3>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-200 text-rose-900 uppercase tracking-wider">Ditolak</span>
                    </div>
                    <p class="text-xs text-rose-800 mt-1">
                        Alasan penolakan: <strong class="text-rose-950 font-bold">"{{ $user->rejection_reason }}"</strong>
                    </p>
                    <p class="text-[11px] text-rose-700 mt-0.5">Silakan perbaiki data atau unggah ulang foto identitas yang lebih jelas untuk melanjutkan.</p>
                </div>
            </div>
            <a href="{{ route('customer.verification.create') }}" class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold rounded-lg shadow-sm transition-colors shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                Perbaiki dan Kirim Ulang
            </a>
        </div>
    @elseif ($user->hasSubmittedDocuments())
        <div class="p-5 rounded-xl bg-amber-50 border border-amber-200 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-start sm:items-center gap-3.5">
                <div class="w-10 h-10 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="text-sm font-bold text-amber-900">Menunggu Verifikasi</h3>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-200 text-amber-900 uppercase tracking-wider">Dalam Antrean</span>
                    </div>
                    <p class="text-xs text-amber-800 mt-0.5">
                        Dokumen identitas Anda sedang ditinjau manual oleh tim administrator kami. Mohon tunggu proses validasi.
                    </p>
                </div>
            </div>
            <a href="{{ route('customer.verification.create') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-amber-900 bg-white border border-amber-300 rounded-lg hover:bg-amber-50 transition-colors shrink-0">
                Periksa Dokumen
            </a>
        </div>
    @else
        <div class="p-5 rounded-xl bg-blue-50 border border-blue-200 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-start sm:items-center gap-3.5">
                <div class="w-10 h-10 rounded-lg bg-blue-100 text-blue-700 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-blue-900">Lengkapi Dokumen Identitas Anda</h3>
                    <p class="text-xs text-blue-800 mt-0.5">
                        Unggah KTP dan SIM Anda agar akun terverifikasi dan dapat segera melakukan pemesanan kendaraan impian.
                    </p>
                </div>
            </div>
            <a href="{{ route('customer.verification.create') }}" class="inline-flex items-center justify-center gap-1.5 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-lg shadow-sm transition-colors shrink-0">
                Unggah Dokumen Verifikasi &rarr;
            </a>
        </div>
    @endif

    <!-- Section Header -->
    <div class="flex items-center justify-between pt-2">
        <div>
            <h3 class="text-base font-bold text-slate-900">Armada Siap Disewa Hari Ini</h3>
            <p class="text-xs text-slate-500 mt-0.5">Semua unit bergaransi kondisi bersih, terawat, dan siap jalan.</p>
        </div>
        <span class="text-xs font-semibold px-3 py-1 bg-emerald-50 text-emerald-700 rounded-full border border-emerald-200">
            {{ $availableCars->count() }} Unit Tersedia
        </span>
    </div>

    <!-- Available Cars Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse ($availableCars as $car)
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden flex flex-col hover:border-slate-300 transition-colors">
                <!-- Card Header -->
                <div class="p-5 border-b border-slate-100 flex items-start justify-between">
                    <div>
                        <span class="text-xs font-bold uppercase tracking-wider text-teal-700">
                            {{ $car->brand }} &bull; {{ $car->carType?->name ?? 'Mobil' }}
                        </span>
                        <h4 class="text-base font-bold text-slate-900 mt-0.5">{{ $car->name }}</h4>
                    </div>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-100 text-emerald-800">
                        Tersedia
                    </span>
                </div>

                <!-- Specs -->
                <div class="p-5 flex-1 space-y-4">
                    <div class="grid grid-cols-3 gap-2 text-center">
                        <div class="p-2.5 rounded-lg bg-slate-50 border border-slate-100">
                            <span class="text-[10px] text-slate-400 uppercase font-semibold block">Tahun</span>
                            <span class="text-xs font-bold text-slate-700 block mt-0.5">{{ $car->model_year }}</span>
                        </div>
                        <div class="p-2.5 rounded-lg bg-slate-50 border border-slate-100">
                            <span class="text-[10px] text-slate-400 uppercase font-semibold block">Transmisi</span>
                            <span class="text-xs font-bold text-slate-700 block mt-0.5 capitalize">{{ $car->transmission }}</span>
                        </div>
                        <div class="p-2.5 rounded-lg bg-slate-50 border border-slate-100">
                            <span class="text-[10px] text-slate-400 uppercase font-semibold block">Kapasitas</span>
                            <span class="text-xs font-bold text-slate-700 block mt-0.5">{{ $car->capacity }} Seat</span>
                        </div>
                    </div>

                    @if ($car->description)
                        <p class="text-xs text-slate-600 line-clamp-2 leading-relaxed">
                            {{ $car->description }}
                        </p>
                    @endif
                </div>

                <!-- Card Footer (Pricing & Link) -->
                <div class="p-5 bg-slate-50/70 border-t border-slate-100 flex items-center justify-between">
                    <div>
                        <span class="text-[10px] text-slate-400 uppercase font-semibold block">Tarif Sewa</span>
                        <div class="flex items-baseline gap-1">
                            <span class="text-base font-extrabold text-slate-900">
                                Rp {{ number_format($car->price_per_day, 0, ',', '.') }}
                            </span>
                            <span class="text-[11px] text-slate-500">/ hari</span>
                        </div>
                    </div>
                    <a href="{{ route('cars.show', $car) }}" class="inline-flex items-center gap-1 text-xs font-semibold text-teal-700 hover:text-teal-900 bg-white px-3 py-1.5 rounded-lg border border-slate-200 hover:border-teal-500 transition-colors shadow-xs">
                        Lihat Detail
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                </div>
            </div>
        @empty
            <div class="col-span-full bg-white rounded-xl p-12 text-center border border-slate-200 text-slate-500">
                <svg class="w-12 h-12 text-slate-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 19H5V8h14m-3-5H8L5 6v13a2 2 0 002 2h10a2 2 0 002-2V6l-3-3z"/>
                </svg>
                <p class="font-semibold text-slate-700">Saat ini belum ada armada yang tersedia.</p>
                <p class="text-xs text-slate-400 mt-1">Silakan periksa kembali beberapa saat lagi.</p>
            </div>
        @endforelse
    </div>
</div>
@endsection
