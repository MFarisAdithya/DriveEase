@extends('layouts.admin')

@section('title', 'Verifikasi pelanggan - DriveEase')
@section('page_title', 'Verifikasi pelanggan')

@section('content')
<div class="space-y-6">
    <!-- Header & Summary -->
    <header>
        <h2 class="text-[28px] leading-[36px] font-bold tracking-tight text-ink">
            Verifikasi pelanggan
        </h2>
        <p class="mt-1 text-base text-muted">
            <strong class="font-semibold text-ink tabular-nums">{{ $counts['ready'] }}</strong> pengajuan siap ditinjau
        </p>
    </header>

    <!-- Status Filter Tabs -->
    <nav aria-label="Filter status verifikasi" class="flex flex-wrap gap-2 border-b border-line pb-px">
        @foreach ([
            'all' => 'Semua',
            'ready' => 'Siap ditinjau',
            'incomplete' => 'Menunggu kelengkapan',
            'verified' => 'Disetujui',
            'rejected' => 'Ditolak',
        ] as $key => $label)
            @php
                $isActive = ($status ?: 'all') === $key;
            @endphp
            <a href="{{ route('admin.verifications.index', $key === 'all' ? [] : ['status' => $key]) }}"
               @if ($isActive) aria-current="page" @endif
               class="min-h-11 px-3.5 py-2 inline-flex items-center gap-2 rounded-t-lg border-b-2 text-sm font-semibold transition-colors {{ $isActive ? 'border-brand-navy bg-brand-soft/50 text-brand-navy' : 'border-transparent text-muted hover:text-ink hover:bg-surface-raised' }}">
                <span>{{ $label }}</span>
                <span class="px-2 py-0.5 rounded-full text-xs tabular-nums {{ $isActive ? 'bg-brand-navy text-white' : 'bg-surface border border-line text-ink' }}">
                    {{ $counts[$key] }}
                </span>
            </a>
        @endforeach
    </nav>

    <!-- Customers Queue Table / Stacked Cards -->
    <div class="bg-surface rounded-xl border border-line overflow-hidden shadow-xs">
        <table class="w-full text-left text-sm text-ink">
            <thead class="hidden lg:table-header-group bg-surface-raised text-ink font-semibold border-b border-line text-sm">
                <tr>
                    <th scope="col" class="px-4 py-3.5">Pelanggan</th>
                    <th scope="col" class="px-4 py-3.5">Berkas</th>
                    <th scope="col" class="px-4 py-3.5">Status</th>
                    <th scope="col" class="px-4 py-3.5">Pembaruan</th>
                    <th scope="col" class="px-4 py-3.5 text-right">Tindakan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($customers as $customer)
                    <tr class="grid grid-cols-1 gap-3 p-4 lg:p-0 lg:table-row hover:bg-surface-raised/60 transition-colors">
                        <!-- Pelanggan -->
                        <td class="lg:px-4 lg:py-4 align-middle">
                            <div class="font-semibold text-base text-ink break-words">{{ $customer->name }}</div>
                            <div class="text-sm text-muted break-all">
                                {{ $customer->email }}{{ $customer->phone ? ' · '.$customer->phone : '' }}
                            </div>
                        </td>

                        <!-- Berkas -->
                        <td class="lg:px-4 lg:py-4 align-middle">
                            @if ($customer->hasSubmittedDocuments())
                                <span class="font-medium text-ink">Berkas lengkap</span>
                                <span class="block text-xs text-muted">KTP &amp; SIM terunggah</span>
                            @else
                                <span class="font-medium text-muted">Berkas belum lengkap</span>
                                <span class="block text-xs text-muted">Menunggu unggahan KTP/SIM</span>
                            @endif
                        </td>

                        <!-- Status -->
                        <td class="lg:px-4 lg:py-4 align-middle">
                            @if ($customer->isVerified())
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-success-surface text-success border border-success-line">
                                    <svg class="w-3.5 h-3.5 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    Disetujui
                                </span>
                            @elseif ($customer->isRejectedVerification())
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-danger-surface text-danger border border-danger-line">
                                    <svg class="w-3.5 h-3.5 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                    Ditolak
                                </span>
                            @elseif ($customer->hasSubmittedDocuments())
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-warning-surface text-warning border border-warning-line">
                                    <svg class="w-3.5 h-3.5 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    Siap ditinjau
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-surface-raised text-muted border border-line">
                                    <svg class="w-3.5 h-3.5 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                    Menunggu kelengkapan
                                </span>
                            @endif
                        </td>

                        <!-- Pembaruan -->
                        <td class="lg:px-4 lg:py-4 align-middle">
                            <div class="text-sm text-ink">
                                {{ $customer->updated_at ? $customer->updated_at->locale('id')->translatedFormat('d M Y, H:i') : '-' }}
                            </div>
                            <div class="text-xs text-muted">
                                Terdaftar {{ $customer->created_at->locale('id')->translatedFormat('d M Y') }}
                            </div>
                        </td>

                        <!-- Tindakan -->
                        <td class="lg:px-4 lg:py-4 lg:text-right align-middle">
                            <a href="{{ route('admin.verifications.show', $customer) }}"
                               class="w-full lg:w-auto min-h-11 px-4 inline-flex items-center justify-center gap-1.5 rounded-lg bg-brand-navy text-white hover:opacity-95 text-sm font-semibold transition-opacity">
                                <span>Periksa dokumen</span>
                                <svg class="w-4 h-4 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-12 text-center">
                            <svg class="w-10 h-10 text-muted mx-auto mb-3" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            <p class="text-base font-semibold text-ink">Belum ada pengajuan pada filter ini.</p>
                            @if (($status ?: 'all') !== 'all')
                                <a href="{{ route('admin.verifications.index') }}"
                                   class="mt-4 min-h-11 px-4 inline-flex items-center justify-center rounded-lg border border-field-line bg-surface hover:bg-surface-raised text-sm font-semibold text-ink transition-colors">
                                    Lihat semua pelanggan
                                </a>
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if ($customers->hasPages())
            <div class="p-4 border-t border-line">
                {{ $customers->links('layouts.pagination') }}
            </div>
        @endif
    </div>
</div>
@endsection
