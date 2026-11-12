@extends('layouts.admin')

@section('title', 'Verifikasi Identitas Pelanggan - DriveEase Admin')
@section('page_title', 'Verifikasi Identitas Pelanggan')

@section('content')
<div class="space-y-6">
    <header>
        <h2 class="font-serif text-3xl font-normal tracking-tight">Daftar pemeriksaan</h2>
        <p class="mt-2 text-sm text-[#CCBDAC]">{{ $counts['ready'] }} pengajuan siap ditinjau.</p>
    </header>
    <nav aria-label="Filter status verifikasi" class="flex flex-wrap gap-x-5 border-b border-[#49392D]">
        @foreach (['all' => 'Semua pelanggan', 'ready' => 'Siap ditinjau', 'incomplete' => 'Menunggu kelengkapan', 'verified' => 'Terverifikasi', 'rejected' => 'Ditolak'] as $key => $label)
            <a href="{{ route('admin.verifications.index', $key === 'all' ? [] : ['status' => $key]) }}"
               @if (($status ?: 'all') === $key) aria-current="page" @endif
               class="border-b-2 py-3 text-sm {{ ($status ?: 'all') === $key ? 'border-[#C47F4E] font-semibold text-[#E8AC80]' : 'border-transparent text-[#CCBDAC] hover:text-[#E8DFD1]' }}">
                {{ $label }} ({{ $counts[$key] }})
            </a>
        @endforeach
    </nav>

    <!-- Customers Table -->
    <div class="bg-[#1E1712] border-y border-[#49392D]">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-[#CCBDAC]">
                <thead class="hidden lg:table-header-group bg-[#241C16] text-[#E8DFD1] font-semibold border-b border-[#49392D] uppercase tracking-wider text-[11px]">
                    <tr>
                        <th class="px-3 py-3.5">Pelanggan</th>
                        <th class="px-3 py-3.5">No. Kontak</th>
                        <th class="px-3 py-3.5">Berkas KTP dan SIM</th>
                        <th class="px-3 py-3.5">Status</th>
                        <th class="px-3 py-3.5">Terakhir Diperbarui</th>
                        <th class="px-3 py-3.5 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#49392D]">
                    @forelse ($customers as $customer)
                        <tr class="grid grid-cols-2 items-center lg:table-row hover:bg-[#241C16]/70 transition-colors">
                            <td class="px-3 py-4">
                                <div class="font-bold text-[#E8DFD1] text-sm">{{ $customer->name }}</div>
                                <div class="hidden lg:block text-[#CCBDAC] mt-0.5">{{ $customer->email }}</div>
                            </td>
                            <td class="hidden lg:table-cell px-3 py-4">
                                <span>{{ $customer->phone ?? '-' }}</span>
                            </td>
                            <td class="px-3 py-4">
                                {{ $customer->hasSubmittedDocuments() ? 'Berkas lengkap' : 'Berkas belum lengkap' }}
                            </td>
                            <td class="px-3 py-4">
                                @if ($customer->isVerified())
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-950 text-emerald-200 border border-emerald-800">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span>
                                        Terverifikasi
                                    </span>
                                @elseif ($customer->isRejectedVerification())
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-rose-950 text-rose-200 border border-rose-800">
                                        <span class="w-1.5 h-1.5 rounded-full bg-rose-600"></span>
                                        Ditolak
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-950 text-amber-200 border border-amber-800">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-600 "></span>
                                        Menunggu Verifikasi
                                    </span>
                                @endif
                            </td>
                            <td class="hidden lg:table-cell px-3 py-4 text-[#CCBDAC]">
                                <div>{{ $customer->updated_at ? $customer->updated_at->translatedFormat('d M Y, H:i') : '-' }}</div>
                                <div class="text-[10px] text-[#CCBDAC]">Daftar: {{ $customer->created_at->translatedFormat('d M Y') }}</div>
                            </td>
                            <td class="px-3 py-4 text-right">
                                <a href="{{ route('admin.verifications.show', $customer) }}" 
                                   class="inline-flex items-center gap-1 px-3 py-1.5 text-[#E8AC80] hover:underline font-semibold text-sm">
                                    <span>Lihat detail</span>
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-3 py-12 text-center text-[#CCBDAC]">
                                <svg class="w-10 h-10 text-[#CCBDAC] mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <p class="font-semibold text-[#CCBDAC]">Tidak ada pelanggan dengan status ini.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($customers->hasPages())
            <div class="p-4 border-t border-[#49392D]">
                {{ $customers->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
