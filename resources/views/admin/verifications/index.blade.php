@extends('layouts.admin')

@section('title', 'Verifikasi Identitas Pelanggan - DriveEase Admin')
@section('page_title', 'Verifikasi Identitas Pelanggan')

@section('content')
<div class="space-y-6">
    <header>
        <h2 class="text-3xl font-semibold tracking-tight">Daftar pemeriksaan</h2>
        <p class="mt-2 text-sm text-slate-600">{{ $counts['pending'] }} pelanggan berstatus menunggu verifikasi.</p>
    </header>
    <nav aria-label="Filter status verifikasi" class="flex flex-wrap gap-x-5 border-b border-slate-300">
        @foreach (['all' => 'Semua pelanggan', 'pending' => 'Menunggu', 'verified' => 'Terverifikasi', 'rejected' => 'Ditolak'] as $key => $label)
            <a href="{{ route('admin.verifications.index', $key === 'all' ? [] : ['status' => $key]) }}"
               @if (($status ?: 'all') === $key) aria-current="page" @endif
               class="border-b-2 py-3 text-sm {{ ($status ?: 'all') === $key ? 'border-teal-700 font-semibold text-teal-800' : 'border-transparent text-slate-600 hover:text-slate-900' }}">
                {{ $label }} ({{ $counts[$key] }})
            </a>
        @endforeach
    </nav>

    <!-- Customers Table -->
    <div class="bg-white border-y border-slate-200">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-600">
                <thead class="bg-slate-50 text-slate-700 font-semibold border-b border-slate-200 uppercase tracking-wider text-[11px]">
                    <tr>
                        <th class="px-3 py-3.5">Pelanggan</th>
                        <th class="px-3 py-3.5">No. Kontak</th>
                        <th class="px-3 py-3.5">Berkas KTP dan SIM</th>
                        <th class="px-3 py-3.5">Status</th>
                        <th class="px-3 py-3.5">Terakhir Diperbarui</th>
                        <th class="px-3 py-3.5 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($customers as $customer)
                        <tr class="hover:bg-slate-50/70 transition-colors">
                            <td class="px-3 py-4">
                                <div class="font-bold text-slate-900 text-sm">{{ $customer->name }}</div>
                                <div class="text-slate-400 mt-0.5">{{ $customer->email }}</div>
                            </td>
                            <td class="px-3 py-4">
                                <span>{{ $customer->phone ?? '-' }}</span>
                            </td>
                            <td class="px-3 py-4">
                                {{ $customer->hasSubmittedDocuments() ? 'Lengkap' : 'Belum lengkap' }}
                            </td>
                            <td class="px-3 py-4">
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
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-600 "></span>
                                        Menunggu Verifikasi
                                    </span>
                                @endif
                            </td>
                            <td class="px-3 py-4 text-slate-500">
                                <div>{{ $customer->updated_at ? $customer->updated_at->translatedFormat('d M Y, H:i') : '-' }}</div>
                                <div class="text-[10px] text-slate-400">Daftar: {{ $customer->created_at->translatedFormat('d M Y') }}</div>
                            </td>
                            <td class="px-3 py-4 text-right">
                                <a href="{{ route('admin.verifications.show', $customer) }}" 
                                   class="inline-flex items-center gap-1 px-3 py-1.5 text-teal-800 hover:underline font-semibold text-sm">
                                    <span>Tinjau Dokumen</span>
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-3 py-12 text-center text-slate-400">
                                <svg class="w-10 h-10 text-slate-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <p class="font-semibold text-slate-600">Tidak ada pelanggan dengan status ini.</p>
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
