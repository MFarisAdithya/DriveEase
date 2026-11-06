@extends('layouts.portal')

@section('title', 'Verifikasi Identitas Customer - DriveEase')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <!-- Breadcrumb / Header -->
    <div class="flex items-center justify-between">
        <div>
            <a href="{{ route('customer.dashboard') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-teal-600 transition-colors mb-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Kembali ke Dashboard
            </a>
            <h2 class="text-2xl font-bold text-slate-900">Verifikasi Identitas Pelanggan</h2>
            <p class="text-sm text-slate-500 mt-1">Lengkapi dokumen KTP dan SIM Anda agar dapat menyewa kendaraan di DriveEase.</p>
        </div>

        <div>
            @if ($user->isVerified())
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 border border-emerald-200">
                    <svg class="w-3.5 h-3.5 text-emerald-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                    </svg>
                    Terverifikasi
                </span>
            @elseif ($user->isRejectedVerification())
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold bg-rose-100 text-rose-800 border border-rose-200">
                    <svg class="w-3.5 h-3.5 text-rose-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                    </svg>
                    Verifikasi Ditolak
                </span>
            @else
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 border border-amber-200">
                    <svg class="w-3.5 h-3.5 text-amber-600 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Menunggu Verifikasi
                </span>
            @endif
        </div>
    </div>

    <!-- Status Alerts -->
    @if ($user->isVerified())
        <div class="p-5 rounded-xl bg-emerald-50 border border-emerald-200 flex items-start gap-4">
            <div class="p-2 rounded-lg bg-emerald-100 text-emerald-700 shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
            </div>
            <div>
                <h4 class="text-base font-bold text-emerald-900">Identitas Anda Telah Diverifikasi</h4>
                <p class="text-sm text-emerald-800 mt-1">
                    Dokumen identitas Anda telah disetujui pada {{ $user->verified_at ? $user->verified_at->translatedFormat('d F Y H:i') : '-' }}. Akun Anda memiliki akses penuh untuk melakukan pemesanan kendaraan.
                </p>
                <div class="mt-3">
                    <a href="{{ route('customer.dashboard') }}" class="inline-flex items-center gap-2 text-xs font-semibold text-emerald-900 underline hover:text-emerald-700">
                        Kembali pilih armada kendaraan &rarr;
                    </a>
                </div>
            </div>
        </div>
    @elseif ($user->isRejectedVerification())
        <div class="p-5 rounded-xl bg-rose-50 border border-rose-200 flex items-start gap-4">
            <div class="p-2 rounded-lg bg-rose-100 text-rose-700 shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
            </div>
            <div>
                <h4 class="text-base font-bold text-rose-900">Pengajuan Verifikasi Identitas Ditolak</h4>
                <p class="text-sm text-rose-800 mt-1">
                    Alasan dari Admin: <span class="font-semibold text-rose-950">"{{ $user->rejection_reason }}"</span>
                </p>
                <p class="text-xs text-rose-700 mt-2">
                    Silakan periksa kembali data nomor identitas atau unggah foto KTP dan SIM baru yang lebih jelas, lalu kirim ulang melalui formulir di bawah.
                </p>
            </div>
        </div>
    @elseif ($user->hasSubmittedDocuments())
        <div class="p-5 rounded-xl bg-amber-50 border border-amber-200 flex items-start gap-4">
            <div class="p-2 rounded-lg bg-amber-100 text-amber-700 shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div>
                <h4 class="text-base font-bold text-amber-900">Dokumen Sedang Ditinjau Admin</h4>
                <p class="text-sm text-amber-800 mt-1">
                    Dokumen Anda telah kami terima dan sedang dalam proses verifikasi manual oleh petugas. Anda tetap dapat memperbarui informasi di bawah jika terdapat kesalahan.
                </p>
            </div>
        </div>
    @else
        <div class="p-5 rounded-xl bg-blue-50 border border-blue-200 flex items-start gap-4">
            <div class="p-2 rounded-lg bg-blue-100 text-blue-700 shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div>
                <h4 class="text-base font-bold text-blue-900">Lengkapi Identitas untuk Menyewa Mobil</h4>
                <p class="text-sm text-blue-800 mt-1">
                    Sesuai kebijakan keamanan rental DriveEase, customer wajib memiliki KTP (16 digit angka) dan SIM A/Umum yang valid dan terverifikasi sebelum dapat melakukan reservasi.
                </p>
            </div>
        </div>
    @endif

    <!-- Profile Summary Card -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
        <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider mb-4">Informasi Akun</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
            <div>
                <span class="text-slate-400 block font-medium">Nama Lengkap</span>
                <span class="text-sm font-semibold text-slate-800 mt-0.5 block">{{ $user->name }}</span>
            </div>
            <div>
                <span class="text-slate-400 block font-medium">Alamat Email</span>
                <span class="text-sm font-semibold text-slate-800 mt-0.5 block">{{ $user->email }}</span>
            </div>
            <div>
                <span class="text-slate-400 block font-medium">Nomor Telepon</span>
                <span class="text-sm font-semibold text-slate-800 mt-0.5 block">{{ $user->phone ?? '-' }}</span>
            </div>
            <div>
                <span class="text-slate-400 block font-medium">Status Akun</span>
                <span class="text-sm font-semibold capitalize text-slate-800 mt-0.5 block">{{ $user->verification_status }}</span>
            </div>
        </div>
    </div>

    <!-- Identity Submission Form -->
    @unless ($user->isVerified())
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
            <h3 class="text-base font-bold text-slate-900 mb-1">
                {{ $user->isRejectedVerification() ? 'Formulir Perbaikan & Pengiriman Ulang' : ($user->hasSubmittedDocuments() ? 'Perbarui Dokumen Identitas' : 'Unggah Dokumen Identitas') }}
            </h3>
            <p class="text-xs text-slate-500 mb-6">
                Pastikan foto dokumen tampak jelas, tidak buram, dan teks dapat terbaca dengan baik. Format didukung: JPG, JPEG, PNG, WEBP (Maksimal 2 MB per file).
            </p>

            <form action="{{ route('customer.verification.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
                @csrf

                <!-- KTP Section -->
                <div class="border-t border-slate-100 pt-5 space-y-4">
                    <h4 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                        <span class="w-6 h-6 rounded-full bg-slate-900 text-white inline-flex items-center justify-center text-xs">1</span>
                        Kartu Tanda Penduduk (KTP / NIK)
                    </h4>

                    <div>
                        <label for="ktp_number" class="block text-xs font-semibold text-slate-700 mb-1">
                            Nomor Induk Kependudukan (16 Digit) <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" 
                               name="ktp_number" 
                               id="ktp_number" 
                               maxlength="16"
                               placeholder="Contoh: 3578012304950001"
                               value="{{ old('ktp_number', $user->ktp_number ?? $user->nik_ktp) }}" 
                               class="w-full px-3.5 py-2.5 rounded-lg border {{ $errors->has('ktp_number') ? 'border-rose-400 bg-rose-50/30' : 'border-slate-300' }} text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent tracking-widest font-mono"
                               required>
                        @error('ktp_number')
                            <p class="text-xs text-rose-600 mt-1 font-medium">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="ktp_image" class="block text-xs font-semibold text-slate-700 mb-1">
                            Unggah Foto KTP Asli {{ $user->ktp_image ? '(Opsional jika tidak diganti)' : '*' }}
                        </label>
                        <input type="file" 
                               name="ktp_image" 
                               id="ktp_image" 
                               accept=".jpg,.jpeg,.png,.webp"
                               class="w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-teal-50 file:text-teal-700 hover:file:bg-teal-100 border border-slate-200 rounded-lg p-1.5 cursor-pointer">
                        <p class="text-[11px] text-slate-400 mt-1">Maksimal 2 MB. Format JPG, PNG, atau WEBP.</p>
                        @error('ktp_image')
                            <p class="text-xs text-rose-600 mt-1 font-medium">{{ $message }}</p>
                        @enderror

                        @if ($user->ktp_image)
                            <div class="mt-3 p-3 rounded-lg bg-slate-50 border border-slate-200 flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <svg class="w-5 h-5 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                    <div>
                                        <p class="text-xs font-semibold text-slate-700">Foto KTP telah tersimpan aman</p>
                                        <p class="text-[11px] text-slate-400">Tersimpan di sistem private storage</p>
                                    </div>
                                </div>
                                <a href="{{ route('documents.identity.show', [$user, 'ktp']) }}" target="_blank" class="px-2.5 py-1 text-xs font-medium text-teal-700 bg-white border border-teal-200 rounded hover:bg-teal-50">
                                    Lihat Pratinjau
                                </a>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- SIM Section -->
                <div class="border-t border-slate-100 pt-5 space-y-4">
                    <h4 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                        <span class="w-6 h-6 rounded-full bg-slate-900 text-white inline-flex items-center justify-center text-xs">2</span>
                        Surat Izin Mengemudi (SIM)
                    </h4>

                    <div>
                        <label for="sim_number" class="block text-xs font-semibold text-slate-700 mb-1">
                            Nomor SIM <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" 
                               name="sim_number" 
                               id="sim_number" 
                               placeholder="Contoh: 1234-5678-901234"
                               value="{{ old('sim_number', $user->sim_number) }}" 
                               class="w-full px-3.5 py-2.5 rounded-lg border {{ $errors->has('sim_number') ? 'border-rose-400 bg-rose-50/30' : 'border-slate-300' }} text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent font-mono"
                               required>
                        @error('sim_number')
                            <p class="text-xs text-rose-600 mt-1 font-medium">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="sim_image" class="block text-xs font-semibold text-slate-700 mb-1">
                            Unggah Foto SIM Asli {{ $user->sim_image ? '(Opsional jika tidak diganti)' : '*' }}
                        </label>
                        <input type="file" 
                               name="sim_image" 
                               id="sim_image" 
                               accept=".jpg,.jpeg,.png,.webp"
                               class="w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-teal-50 file:text-teal-700 hover:file:bg-teal-100 border border-slate-200 rounded-lg p-1.5 cursor-pointer">
                        <p class="text-[11px] text-slate-400 mt-1">Maksimal 2 MB. Format JPG, PNG, atau WEBP.</p>
                        @error('sim_image')
                            <p class="text-xs text-rose-600 mt-1 font-medium">{{ $message }}</p>
                        @enderror

                        @if ($user->sim_image)
                            <div class="mt-3 p-3 rounded-lg bg-slate-50 border border-slate-200 flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <svg class="w-5 h-5 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                    <div>
                                        <p class="text-xs font-semibold text-slate-700">Foto SIM telah tersimpan aman</p>
                                        <p class="text-[11px] text-slate-400">Tersimpan di sistem private storage</p>
                                    </div>
                                </div>
                                <a href="{{ route('documents.identity.show', [$user, 'sim']) }}" target="_blank" class="px-2.5 py-1 text-xs font-medium text-teal-700 bg-white border border-teal-200 rounded hover:bg-teal-50">
                                    Lihat Pratinjau
                                </a>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Privacy & Consent Notice -->
                <div class="p-4 bg-slate-50 rounded-lg border border-slate-200 text-xs text-slate-500 space-y-1">
                    <p class="font-semibold text-slate-700">Perlindungan Kerahasiaan Dokumen:</p>
                    <p>File KTP dan SIM Anda disimpan di direktori privat terenkripsi yang tidak dapat diakses secara publik. Hanya Administrator yang berwenang dan Anda sendiri yang dapat melihat dokumen ini untuk keperluan validasi rental.</p>
                </div>

                <!-- Submit Button -->
                <div class="flex items-center justify-end gap-3 pt-3">
                    <a href="{{ route('customer.dashboard') }}" class="px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 rounded-lg transition-colors">
                        Batal
                    </a>
                    <button type="submit" class="px-6 py-2.5 bg-teal-600 hover:bg-teal-700 text-white text-xs font-bold rounded-lg shadow-sm transition-colors flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                        </svg>
                        {{ $user->isRejectedVerification() ? 'Perbaiki dan Kirim Ulang' : ($user->hasSubmittedDocuments() ? 'Perbarui Dokumen' : 'Kirim Dokumen Verifikasi') }}
                    </button>
                </div>
            </form>
        </div>
    @endunless
</div>
@endsection
