@extends('layouts.admin')

@section('title', 'Verifikasi Identitas Pelanggan - DriveEase Admin')
@section('page_title', 'Verifikasi Identitas Pelanggan')

@section('content')
<div class="space-y-6">
    <!-- Header Summary -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-slate-900">Kelola Verifikasi Identitas Customer</h2>
            <p class="text-sm text-slate-500 mt-1">
                Tinjau dokumen KTP dan SIM pelanggan sebelum menyetujui hak reservasi armada kendaraan.
            </p>
        </div>
    </div>

    <!-- Filter Tabs & Stats -->
    <div class="flex flex-wrap items-center gap-2 border-b border-slate-200 pb-3">
        <a href="{{ route('admin.verifications.index') }}" 
           class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg text-xs font-semibold transition-colors {{ empty($status) ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100' }}">
            <span>Semua Pelanggan</span>
            <span class="px-1.5 py-0.5 rounded text-[10px] {{ empty($status) ? 'bg-slate-800 text-slate-200' : 'bg-slate-200 text-slate-700' }}">
                {{ $counts['all'] }}
            </span>
        </a>

        <a href="{{ route('admin.verifications.index', ['status' => 'pending']) }}" 
           class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg text-xs font-semibold transition-colors {{ $status === 'pending' ? 'bg-amber-600 text-white' : 'text-slate-600 hover:bg-slate-100' }}">
            <span>Menunggu Verifikasi</span>
            <span class="px-1.5 py-0.5 rounded text-[10px] {{ $status === 'pending' ? 'bg-amber-700 text-amber-100' : 'bg-amber-100 text-amber-800' }}">
                {{ $counts['pending'] }}
            </span>
        </a>

        <a href="{{ route('admin.verifications.index', ['status' => 'verified']) }}" 
           class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg text-xs font-semibold transition-colors {{ $status === 'verified' ? 'bg-emerald-600 text-white' : 'text-slate-600 hover:bg-slate-100' }}">
            <span>Terverifikasi</span>
            <span class="px-1.5 py-0.5 rounded text-[10px] {{ $status === 'verified' ? 'bg-emerald-700 text-emerald-100' : 'bg-emerald-100 text-emerald-800' }}">
                {{ $counts['verified'] }}
            </span>
        </a>

        <a href="{{ route('admin.verifications.index', ['status' => 'rejected']) }}" 
           class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg text-xs font-semibold transition-colors {{ $status === 'rejected' ? 'bg-rose-600 text-white' : 'text-slate-600 hover:bg-slate-100' }}">
            <span>Ditolak</span>
            <span class="px-1.5 py-0.5 rounded text-[10px] {{ $status === 'rejected' ? 'bg-rose-700 text-rose-100' : 'bg-rose-100 text-rose-800' }}">
                {{ $counts['rejected'] }}
            </span>
        </a>
    </div>

    <!-- Customers Table -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-600">
                <thead class="bg-slate-50 text-slate-700 font-semibold border-b border-slate-200 uppercase tracking-wider text-[11px]">
                    <tr>
                        <th class="px-6 py-3.5">Pelanggan</th>
                        <th class="px-6 py-3.5">No. Kontak</th>
                        <th class="px-6 py-3.5">Identitas (KTP / SIM)</th>
                        <th class="px-6 py-3.5">Status</th>
                        <th class="px-6 py-3.5">Terakhir Diperbarui</th>
                        <th class="px-6 py-3.5 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($customers as $customer)
                        <tr class="hover:bg-slate-50/70 transition-colors">
                            <td class="px-6 py-4">
                                <div class="font-bold text-slate-900 text-sm">{{ $customer->name }}</div>
                                <div class="text-slate-400 mt-0.5">{{ $customer->email }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <span>{{ $customer->phone ?? '-' }}</span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">KTP:</span>
                                        @if ($customer->ktp_number || $customer->nik_ktp)
                                            <span class="font-mono text-slate-800 font-medium">{{ $customer->ktp_number ?? $customer->nik_ktp }}</span>
                                        @else
                                            <span class="text-slate-400 italic">Belum diisi</span>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">SIM:</span>
                                        @if ($customer->sim_number)
                                            <span class="font-mono text-slate-800 font-medium">{{ $customer->sim_number }}</span>
                                        @else
                                            <span class="text-slate-400 italic">Belum diisi</span>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-2 pt-0.5">
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] {{ $customer->ktp_image ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                                            Foto KTP: {{ $customer->ktp_image ? 'Ada' : 'Kosong' }}
                                        </span>
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] {{ $customer->sim_image ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                                            Foto SIM: {{ $customer->sim_image ? 'Ada' : 'Kosong' }}
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                @if ($customer->isVerified())
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span>
                                        Terverifikasi
                                    </span>
                                @elseif ($customer->isRejectedVerification())
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-rose-100 text-rose-800 border border-rose-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-rose-600"></span>
                                        Ditolak
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 border border-amber-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-600 animate-pulse"></span>
                                        Menunggu Verifikasi
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-slate-500">
                                <div>{{ $customer->updated_at ? $customer->updated_at->translatedFormat('d M Y, H:i') : '-' }}</div>
                                <div class="text-[10px] text-slate-400">Daftar: {{ $customer->created_at->translatedFormat('d M Y') }}</div>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('admin.verifications.show', $customer) }}" 
                                   class="inline-flex items-center gap-1 px-3 py-1.5 bg-slate-900 hover:bg-teal-700 text-white font-semibold rounded-lg text-xs transition-colors shadow-xs">
                                    <span>Tinjau Dokumen</span>
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-slate-400">
                                <svg class="w-10 h-10 text-slate-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <p class="font-semibold text-slate-600">Tidak ada data customer yang sesuai dengan filter.</p>
                                <p class="text-xs text-slate-400 mt-0.5">Semua permohonan telah ditindaklanjuti atau belum ada pendaftaran baru.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($customers->hasPages())
            <div class="p-4 border-t border-slate-200">
                {{ $customers->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
