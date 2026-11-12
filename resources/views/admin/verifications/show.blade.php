@extends('layouts.admin')

@section('title', 'Tinjau Verifikasi: ' . $user->name . ' - DriveEase Admin')
@section('page_title', 'Tinjau Dokumen Identitas Pelanggan')

@section('content')
<div class="max-w-5xl space-y-6">
    <!-- Back Link & Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <a href="{{ route('admin.verifications.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-[#CCBDAC] hover:text-[#E8AC80] transition-colors mb-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Kembali ke Daftar Verifikasi
            </a>
            <h2 class="text-xl font-bold text-[#E8DFD1] flex items-center gap-3">
                <span>{{ $user->name }}</span>
                @if ($user->isVerified())
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-950 text-emerald-200 border border-emerald-800">
                        Terverifikasi
                    </span>
                @elseif ($user->isRejectedVerification())
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-950 text-rose-200 border border-rose-800">
                        Verifikasi Ditolak
                    </span>
                @else
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-950 text-amber-200 border border-amber-800">
                        Menunggu Verifikasi
                    </span>
                @endif
            </h2>
            <p class="text-xs text-[#CCBDAC] mt-1">Terdaftar sejak {{ $user->created_at->translatedFormat('d F Y') }}</p>
        </div>
    </div>

    <!-- Rejection Alert if currently rejected -->
    @if ($user->isRejectedVerification())
        <div class="p-4 rounded-xl bg-rose-950 border border-rose-800 text-xs text-rose-200 flex items-start gap-3">
            <svg class="w-5 h-5 text-rose-200 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            <div>
                <p class="font-bold">Dokumen pelanggan ini sebelumnya ditolak:</p>
                <p class="mt-1 italic">"{{ $user->rejection_reason }}"</p>
                <p class="mt-1 text-[#CCBDAC]">Anda dapat menyetujuinya kembali jika pelanggan telah mengunggah perbaikan dokumen yang sesuai.</p>
            </div>
        </div>
    @elseif ($user->isVerified())
        <div class="p-4 rounded-xl bg-emerald-950 border border-emerald-800 text-xs text-emerald-200 flex items-start gap-3">
            <svg class="w-5 h-5 text-emerald-200 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <div>
                <p class="font-bold">Identitas pelanggan ini telah diverifikasi dan aktif.</p>
                <p class="mt-0.5 text-emerald-200">
                    Disetujui oleh: <span class="font-semibold">{{ $user->verifier?->name ?? 'Admin Sistem' }}</span> pada {{ $user->verified_at ? $user->verified_at->translatedFormat('d F Y, H:i') : '-' }}.
                </p>
            </div>
        </div>
    @endif

    <!-- Customer Basic Information Grid -->
    <div class="bg-[#1E1712] rounded-xl border border-[#49392D] shadow-sm p-6">
        <h3 class="text-xs font-bold uppercase tracking-wider text-[#CCBDAC] mb-4">Data Profil Customer</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 text-xs">
            <div>
                <span class="text-[#CCBDAC] block font-medium">Nama Lengkap</span>
                <span class="text-sm font-semibold text-[#E8DFD1] mt-0.5 block">{{ $user->name }}</span>
            </div>
            <div>
                <span class="text-[#CCBDAC] block font-medium">Email</span>
                <span class="text-sm font-semibold text-[#E8DFD1] mt-0.5 block">{{ $user->email }}</span>
            </div>
            <div>
                <span class="text-[#CCBDAC] block font-medium">Nomor Telepon</span>
                <span class="text-sm font-semibold text-[#E8DFD1] mt-0.5 block">{{ $user->phone ?? '-' }}</span>
            </div>
            <div>
                <span class="text-[#CCBDAC] block font-medium">Alamat Domisili</span>
                <span class="text-sm font-semibold text-[#E8DFD1] mt-0.5 block">{{ $user->address ?? '-' }}</span>
            </div>
        </div>
    </div>

    <!-- Documents Inspection Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- KTP Inspection -->
        <div class="bg-[#1E1712] rounded-xl border border-[#49392D] shadow-sm p-6 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between pb-3 border-b border-[#49392D] mb-4">
                    <h3 class="text-sm font-bold text-[#E8DFD1] flex items-center gap-2">
                        <span class="w-6 h-6 rounded-full bg-[#935831] text-[#E8DFD1] inline-flex items-center justify-center text-xs">1</span>
                        Kartu Tanda Penduduk (KTP)
                    </h3>
                    @if ($user->ktp_image)
                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-950 text-emerald-200 border border-emerald-800">Foto Tersedia</span>
                    @else
                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-rose-950 text-rose-200 border border-rose-800">Belum Ada</span>
                    @endif
                </div>

                <div class="space-y-3">
                    <div>
                        <span class="text-[11px] font-bold text-[#CCBDAC] uppercase tracking-wider block">Nomor NIK / KTP (16 Digit)</span>
                        <div class="font-mono text-base font-bold text-[#E8DFD1] mt-0.5">
                            {{ $user->ktp_number ?? $user->nik_ktp ?? 'Belum diisi' }}
                        </div>
                    </div>

                    <div class="mt-4">
                        <span class="text-[11px] font-bold text-[#CCBDAC] uppercase tracking-wider block mb-2">Pratinjau Foto KTP</span>
                        @if ($user->ktp_image)
                            <div class="rounded-lg border border-[#49392D] overflow-hidden bg-[#241C16] p-2 flex items-center justify-center">
                                <img src="{{ route('documents.identity.show', [$user, 'ktp']) }}" 
                                     alt="Foto KTP {{ $user->name }}" 
                                     class="max-h-64 object-contain rounded">
                            </div>
                            <div class="mt-2 text-right">
                                <a href="{{ route('documents.identity.show', [$user, 'ktp']) }}" target="_blank" class="inline-flex items-center gap-1.5 text-xs font-semibold text-[#E8AC80] hover:text-[#E8AC80]">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                    </svg>
                                    Buka Foto Resolusi Penuh
                                </a>
                            </div>
                        @else
                            <div class="h-44 rounded-lg border-2 border-dashed border-[#49392D] bg-[#241C16] flex flex-col items-center justify-center text-[#CCBDAC] text-xs">
                                <svg class="w-8 h-8 text-[#CCBDAC] mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                Pelanggan belum mengunggah foto KTP
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- SIM Inspection -->
        <div class="bg-[#1E1712] rounded-xl border border-[#49392D] shadow-sm p-6 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between pb-3 border-b border-[#49392D] mb-4">
                    <h3 class="text-sm font-bold text-[#E8DFD1] flex items-center gap-2">
                        <span class="w-6 h-6 rounded-full bg-[#935831] text-[#E8DFD1] inline-flex items-center justify-center text-xs">2</span>
                        Surat Izin Mengemudi (SIM)
                    </h3>
                    @if ($user->sim_image)
                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-950 text-emerald-200 border border-emerald-800">Foto Tersedia</span>
                    @else
                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-rose-950 text-rose-200 border border-rose-800">Belum Ada</span>
                    @endif
                </div>

                <div class="space-y-3">
                    <div>
                        <span class="text-[11px] font-bold text-[#CCBDAC] uppercase tracking-wider block">Nomor SIM</span>
                        <div class="font-mono text-base font-bold text-[#E8DFD1] mt-0.5">
                            {{ $user->sim_number ?? 'Belum diisi' }}
                        </div>
                    </div>

                    <div class="mt-4">
                        <span class="text-[11px] font-bold text-[#CCBDAC] uppercase tracking-wider block mb-2">Pratinjau Foto SIM</span>
                        @if ($user->sim_image)
                            <div class="rounded-lg border border-[#49392D] overflow-hidden bg-[#241C16] p-2 flex items-center justify-center">
                                <img src="{{ route('documents.identity.show', [$user, 'sim']) }}" 
                                     alt="Foto SIM {{ $user->name }}" 
                                     class="max-h-64 object-contain rounded">
                            </div>
                            <div class="mt-2 text-right">
                                <a href="{{ route('documents.identity.show', [$user, 'sim']) }}" target="_blank" class="inline-flex items-center gap-1.5 text-xs font-semibold text-[#E8AC80] hover:text-[#E8AC80]">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                    </svg>
                                    Buka Foto Resolusi Penuh
                                </a>
                            </div>
                        @else
                            <div class="h-44 rounded-lg border-2 border-dashed border-[#49392D] bg-[#241C16] flex flex-col items-center justify-center text-[#CCBDAC] text-xs">
                                <svg class="w-8 h-8 text-[#CCBDAC] mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                Pelanggan belum mengunggah foto SIM
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Verification Decision Action Card -->
    <div class="bg-[#1E1712] rounded-xl border border-[#49392D] shadow-sm p-6">
        <h3 class="text-sm font-bold text-[#E8DFD1] mb-1">Keputusan Verifikasi Administrator</h3>
        <p class="text-xs text-[#CCBDAC] mb-6">
            Pilih tindakan verifikasi setelah memeriksa keaslian dan kecocokan data nomor serta foto identitas.
        </p>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-2">
            <!-- Approve Action Box -->
            <div class="p-5 rounded-xl border {{ $user->hasSubmittedDocuments() ? 'border-emerald-800 bg-emerald-950/40' : 'border-[#49392D] bg-[#241C16]/60' }} flex flex-col justify-between">
                <div>
                    <div class="flex items-center gap-2 text-emerald-200 font-bold text-sm mb-2">
                        <svg class="w-5 h-5 text-emerald-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Setujui Verifikasi Identitas
                    </div>
                    <p class="text-xs text-[#CCBDAC] leading-relaxed">
                        Menyetujui dokumen akan mengaktifkan hak customer untuk melakukan pemesanan dan reservasi armada kendaraan.
                    </p>
                    @if (!$user->hasSubmittedDocuments())
                        <p class="text-xs text-rose-200 font-semibold mt-2">
                            * Dokumen belum lengkap (KTP atau SIM masih kosong). Persetujuan tidak dapat diproses.
                        </p>
                    @endif
                </div>

                <form action="{{ route('admin.verifications.approve', $user) }}" method="POST" class="mt-6">
                    @csrf
                    @method('PATCH')
                    <button type="submit" 
                            {{ !$user->hasSubmittedDocuments() ? 'disabled' : '' }}
                            onclick="return confirm('Apakah Anda yakin ingin menyetujui verifikasi identitas customer ini?')"
                            class="w-full px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 disabled:bg-[#241C16] disabled:cursor-not-allowed text-[#E8DFD1] text-xs font-bold rounded-lg shadow-sm transition-colors flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Setujui Verifikasi
                    </button>
                </form>
            </div>

            <!-- Reject Action Box -->
            <div class="p-5 rounded-xl border border-rose-800 bg-rose-950/30 flex flex-col justify-between">
                <form action="{{ route('admin.verifications.reject', $user) }}" method="POST">
                    @csrf
                    @method('PATCH')
                    <div>
                        <div class="flex items-center gap-2 text-rose-200 font-bold text-sm mb-2">
                            <svg class="w-5 h-5 text-rose-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Tolak Verifikasi &amp; Beri Alasan
                        </div>
                        <p class="text-xs text-[#CCBDAC] leading-relaxed mb-3">
                            Customer akan menerima alasan penolakan dan diminta mengunggah perbaikan dokumen.
                        </p>

                        <div>
                            <label for="rejection_reason" class="block text-xs font-semibold text-[#E8DFD1] mb-1">
                                Alasan Penolakan <span class="text-rose-200">*</span>
                            </label>
                            <textarea name="rejection_reason" 
                                      id="rejection_reason" 
                                      rows="3" 
                                      required
                                      placeholder="Contoh: Foto KTP buram dan nomor NIK tidak terbaca jelas. Mohon unggah ulang dengan pencahayaan yang cukup."
                                      class="w-full bg-[#241C16] px-3 py-2 text-xs rounded-lg border {{ $errors->has('rejection_reason') ? 'border-rose-400 bg-rose-950/30' : 'border-[#49392D]' }} focus:outline-none focus:ring-2 focus:ring-rose-500 focus:border-transparent">{{ old('rejection_reason', $user->rejection_reason) }}</textarea>
                            @error('rejection_reason')
                                <p class="text-xs text-rose-200 mt-1 font-medium">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" 
                                onclick="return confirm('Apakah Anda yakin ingin menolak verifikasi customer ini?')"
                                class="w-full px-4 py-2.5 bg-rose-600 hover:bg-rose-700 text-[#E8DFD1] text-xs font-bold rounded-lg shadow-sm transition-colors flex items-center justify-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                            Tolak dan Minta Perbaikan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
