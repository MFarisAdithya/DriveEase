@extends('layouts.admin')

@section('title', 'Tinjau Verifikasi: ' . $user->name . ' - DriveEase Admin')
@section('page_title', 'Tinjau Dokumen Identitas Pelanggan')

@section('content')
<div class="mx-auto max-w-5xl space-y-6">
    <!-- Back Link & Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <a href="{{ route('admin.verifications.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-muted hover:text-link transition-colors mb-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Kembali ke Daftar Verifikasi
            </a>
            <h2 class="text-xl font-bold text-ink flex items-center gap-3">
                <span>{{ $user->name }}</span>
                @if ($user->isVerified())
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-success-surface text-success border border-success-line">
                        Terverifikasi
                    </span>
                @elseif ($user->isRejectedVerification())
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-danger-surface text-danger border border-danger-line">
                        Verifikasi Ditolak
                    </span>
                @else
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-warning-surface text-warning border border-warning-line">
                        Menunggu Verifikasi
                    </span>
                @endif
            </h2>
            <p class="text-xs text-muted mt-1">Terdaftar sejak {{ $user->created_at->translatedFormat('d F Y') }}</p>
        </div>
    </div>

    <!-- Rejection Alert if currently rejected -->
    @if ($user->isRejectedVerification())
        <div class="p-4 rounded-xl bg-danger-surface border border-danger-line text-xs text-danger flex items-start gap-3">
            <svg class="w-5 h-5 text-danger shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            <div>
                <p class="font-bold">Dokumen pelanggan ini sebelumnya ditolak:</p>
                <p class="mt-1 italic">"{{ $user->rejection_reason }}"</p>
                <p class="mt-1 text-muted">Anda dapat menyetujuinya kembali jika pelanggan telah mengunggah perbaikan dokumen yang sesuai.</p>
            </div>
        </div>
    @elseif ($user->isVerified())
        <div class="p-4 rounded-xl bg-success-surface border border-success-line text-xs text-success flex items-start gap-3">
            <svg class="w-5 h-5 text-success shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <div>
                <p class="font-bold">Identitas pelanggan ini telah diverifikasi dan aktif.</p>
                <p class="mt-0.5 text-success">
                    Disetujui oleh: <span class="font-semibold">{{ $user->verifier?->name ?? 'Admin Sistem' }}</span> pada {{ $user->verified_at ? $user->verified_at->translatedFormat('d F Y, H:i') : '-' }}.
                </p>
            </div>
        </div>
    @endif

    <!-- Customer Basic Information Grid -->
    <div class="bg-surface rounded-xl border border-line shadow-sm p-6">
        <h3 class="text-xs font-bold uppercase tracking-wider text-muted mb-4">Data Profil Pelanggan</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 text-xs">
            <div>
                <span class="text-muted block font-medium">Nama Lengkap</span>
                <span class="text-sm font-semibold text-ink mt-0.5 block">{{ $user->name }}</span>
            </div>
            <div>
                <span class="text-muted block font-medium">Email</span>
                <span class="text-sm font-semibold text-ink mt-0.5 block">{{ $user->email }}</span>
            </div>
            <div>
                <span class="text-muted block font-medium">Nomor Telepon</span>
                <span class="text-sm font-semibold text-ink mt-0.5 block">{{ $user->phone ?? '-' }}</span>
            </div>
            <div>
                <span class="text-muted block font-medium">Alamat Domisili</span>
                <span class="text-sm font-semibold text-ink mt-0.5 block">{{ $user->address ?? '-' }}</span>
            </div>
        </div>
    </div>

    <!-- Documents Inspection Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- KTP Inspection -->
        <div class="bg-surface rounded-xl border border-line shadow-sm p-6 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between pb-3 border-b border-line mb-4">
                    <h3 class="text-sm font-bold text-ink flex items-center gap-2">
                        <span class="w-6 h-6 rounded-full bg-action text-ink inline-flex items-center justify-center text-xs">1</span>
                        Kartu Tanda Penduduk (KTP)
                    </h3>
                    @if (app(\App\Services\IdentityDocuments::class)->exists($user->ktp_image))
                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-success-surface text-success border border-success-line">Foto Tersedia</span>
                    @else
                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-danger-surface text-danger border border-danger-line">Belum Ada</span>
                    @endif
                </div>

                <div class="space-y-3">
                    <div>
                        <span class="text-[11px] font-bold text-muted uppercase tracking-wider block">Nomor NIK / KTP (16 Digit)</span>
                        <div class="font-mono text-base font-bold text-ink mt-0.5">
                            {{ $user->ktp_number ?? $user->nik_ktp ?? 'Belum diisi' }}
                        </div>
                    </div>

                    <div class="mt-4">
                        <span class="text-[11px] font-bold text-muted uppercase tracking-wider block mb-2">Pratinjau Foto KTP</span>
                        @if (app(\App\Services\IdentityDocuments::class)->exists($user->ktp_image))
                            <div class="rounded-lg border border-line overflow-hidden bg-surface-raised p-2 flex items-center justify-center">
                                <img src="{{ route('documents.identity.show', [$user, 'ktp']) }}" 
                                     alt="Foto KTP {{ $user->name }}" 
                                     class="max-h-64 object-contain rounded">
                            </div>
                            <div class="mt-2 text-right">
                                <a href="{{ route('documents.identity.show', [$user, 'ktp']) }}" target="_blank" class="inline-flex items-center gap-1.5 text-xs font-semibold text-link hover:text-link">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                    </svg>
                                    Buka Foto Resolusi Penuh
                                </a>
                            </div>
                        @else
                            <div class="h-44 rounded-lg border-2 border-dashed border-line bg-surface-raised flex flex-col items-center justify-center text-muted text-xs">
                                <svg class="w-8 h-8 text-muted mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
        <div class="bg-surface rounded-xl border border-line shadow-sm p-6 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between pb-3 border-b border-line mb-4">
                    <h3 class="text-sm font-bold text-ink flex items-center gap-2">
                        <span class="w-6 h-6 rounded-full bg-action text-ink inline-flex items-center justify-center text-xs">2</span>
                        Surat Izin Mengemudi (SIM)
                    </h3>
                    @if (app(\App\Services\IdentityDocuments::class)->exists($user->sim_image))
                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-success-surface text-success border border-success-line">Foto Tersedia</span>
                    @else
                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-danger-surface text-danger border border-danger-line">Belum Ada</span>
                    @endif
                </div>

                <div class="space-y-3">
                    <div>
                        <span class="text-[11px] font-bold text-muted uppercase tracking-wider block">Nomor SIM</span>
                        <div class="font-mono text-base font-bold text-ink mt-0.5">
                            {{ $user->sim_number ?? 'Belum diisi' }}
                        </div>
                    </div>

                    <div class="mt-4">
                        <span class="text-[11px] font-bold text-muted uppercase tracking-wider block mb-2">Pratinjau Foto SIM</span>
                        @if (app(\App\Services\IdentityDocuments::class)->exists($user->sim_image))
                            <div class="rounded-lg border border-line overflow-hidden bg-surface-raised p-2 flex items-center justify-center">
                                <img src="{{ route('documents.identity.show', [$user, 'sim']) }}" 
                                     alt="Foto SIM {{ $user->name }}" 
                                     class="max-h-64 object-contain rounded">
                            </div>
                            <div class="mt-2 text-right">
                                <a href="{{ route('documents.identity.show', [$user, 'sim']) }}" target="_blank" class="inline-flex items-center gap-1.5 text-xs font-semibold text-link hover:text-link">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                    </svg>
                                    Buka Foto Resolusi Penuh
                                </a>
                            </div>
                        @else
                            <div class="h-44 rounded-lg border-2 border-dashed border-line bg-surface-raised flex flex-col items-center justify-center text-muted text-xs">
                                <svg class="w-8 h-8 text-muted mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
    <div class="bg-surface rounded-xl border border-line shadow-sm p-6">
        <h3 class="text-sm font-bold text-ink mb-1">Keputusan Verifikasi Administrator</h3>
        <p class="text-xs text-muted mb-6">
            Pilih tindakan verifikasi setelah memeriksa keaslian dan kecocokan data nomor serta foto identitas.
        </p>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-2">
            <!-- Approve Action Box -->
            <div class="p-5 rounded-xl border {{ $user->hasSubmittedDocuments() ? 'border-success-line bg-success-surface/40' : 'border-line bg-surface-raised/60' }} flex flex-col justify-between">
                <div>
                    <div class="flex items-center gap-2 text-success font-bold text-sm mb-2">
                        <svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Setujui Verifikasi Identitas
                    </div>
                    <p class="text-xs text-muted leading-relaxed">
                        Menyetujui dokumen akan mengaktifkan hak pelanggan untuk melakukan pemesanan dan reservasi armada kendaraan.
                    </p>
                    @if (!$user->hasSubmittedDocuments())
                        <p class="text-xs text-danger font-semibold mt-2">
                            * Dokumen belum lengkap atau berkas tidak ditemukan. Persetujuan tidak dapat diproses.
                        </p>
                    @endif
                </div>

                <form action="{{ route('admin.verifications.approve', $user) }}" method="POST" class="mt-6">
                    @csrf
                    @method('PATCH')
                    <button type="submit" 
                            {{ !$user->hasSubmittedDocuments() ? 'disabled' : '' }}
                            onclick="return confirm('Apakah Anda yakin ingin menyetujui verifikasi identitas pelanggan ini?')"
                            class="w-full px-4 py-2.5 bg-success-action hover:bg-success-action disabled:bg-surface-raised disabled:cursor-not-allowed text-ink text-xs font-bold rounded-lg shadow-sm transition-colors flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Setujui Verifikasi
                    </button>
                </form>
            </div>

            <!-- Reject Action Box -->
            <div class="p-5 rounded-xl border border-danger-line bg-danger-surface/30 flex flex-col justify-between">
                <form action="{{ route('admin.verifications.reject', $user) }}" method="POST">
                    @csrf
                    @method('PATCH')
                    <div>
                        <div class="flex items-center gap-2 text-danger font-bold text-sm mb-2">
                            <svg class="w-5 h-5 text-danger" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Tolak Verifikasi &amp; Beri Alasan
                        </div>
                        <p class="text-xs text-muted leading-relaxed mb-3">
                            Pelanggan akan menerima alasan penolakan dan diminta mengunggah perbaikan dokumen.
                        </p>

                        <div>
                            <label for="rejection_reason" class="block text-xs font-semibold text-ink mb-1">
                                Alasan Penolakan <span class="text-danger">*</span>
                            </label>
                            <textarea name="rejection_reason" 
                                      id="rejection_reason" 
                                      rows="3" 
                                      required
                                      placeholder="Contoh: Foto KTP buram dan nomor NIK tidak terbaca jelas. Mohon unggah ulang dengan pencahayaan yang cukup."
                                      class="w-full bg-surface-raised px-3 py-2 text-xs rounded-lg border {{ $errors->has('rejection_reason') ? 'border-danger-line bg-danger-surface/30' : 'border-line' }} focus:outline-none focus:ring-2 focus:ring-danger-line focus:border-transparent">{{ old('rejection_reason', $user->rejection_reason) }}</textarea>
                            @error('rejection_reason')
                                <p class="text-xs text-danger mt-1 font-medium">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" 
                                onclick="return confirm('Apakah Anda yakin ingin menolak verifikasi pelanggan ini?')"
                                class="w-full px-4 py-2.5 bg-danger-action hover:bg-danger-action text-ink text-xs font-bold rounded-lg shadow-sm transition-colors flex items-center justify-center gap-2">
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
    <!-- Customer Rental History -->
    <section class="bg-surface rounded-xl border border-line shadow-sm p-6">
        <h3 class="text-sm font-bold text-ink mb-3">Riwayat Rental Pelanggan</h3>
        <ul class="divide-y divide-line text-sm">
            @forelse ($rentals as $rental)
                <li class="py-3 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <span class="font-mono font-bold text-ink">{{ $rental->rental_code }}</span>
                        <span class="text-ink"> &middot; {{ $rental->car_name }}</span>
                        <span class="block text-xs text-muted">{{ $rental->start_date }} sampai {{ $rental->end_date }}</span>
                    </div>
                    <div class="text-right text-xs">
                        <span class="font-semibold text-ink block">Rp {{ number_format((float) $rental->total_amount, 0, ',', '.') }}</span>
                        <span class="text-muted">{{ $rental->rental_status }} / {{ $rental->payment_status }}</span>
                    </div>
                </li>
            @empty
                <li class="py-3 text-muted">Belum ada riwayat rental.</li>
            @endforelse
        </ul>
        @if ($rentals->hasPages())
            <div class="mt-4 pt-3 border-t border-line">
                {{ $rentals->links('layouts.pagination') }}
            </div>
        @endif
    </section>
</div>
@endsection
