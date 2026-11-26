@extends('layouts.admin')
@section('theme_class', 'theme-refresh')
@section('title', 'Verifikasi Identitas Pelanggan — DriveEase')
@section('page_title', 'Verifikasi pelanggan')

@section('content')
<div class="mx-auto max-w-6xl space-y-6">
    <header>
        <p class="text-sm font-semibold text-muted">Daftar pemeriksaan</p>
        <h2 class="mt-2 text-2xl font-bold tracking-tight md:text-3xl">{{ $counts['ready'] }} pengajuan siap ditinjau</h2>
        <p class="mt-3 max-w-2xl text-sm leading-6 text-muted">Mulai dari pelanggan dengan berkas lengkap. Periksa KTP dan SIM sebelum memberikan keputusan.</p>
    </header>
    <div class="grid grid-cols-2 gap-3">
        <a href="{{ route('admin.verifications.index', ['status' => 'ready']) }}" class="rounded-xl border border-field-line bg-accent p-5 hover:underline">
            <span class="text-sm font-semibold">Siap ditinjau</span>
            <strong class="mt-2 block text-3xl tabular-nums">{{ $counts['ready'] }}</strong>
            <span class="mt-2 hidden text-sm sm:block">Nomor dan foto KTP serta SIM sudah diisi →</span>
        </a>
        <a href="{{ route('admin.verifications.index', ['status' => 'incomplete']) }}" class="ui-panel p-5 hover:underline">
            <span class="text-sm font-semibold">Menunggu kelengkapan</span>
            <strong class="mt-2 block text-3xl tabular-nums">{{ $counts['incomplete'] }}</strong>
            <span class="mt-2 hidden text-sm text-muted sm:block">Pelanggan masih perlu melengkapi identitas →</span>
        </a>
    </div>
    <section class="ui-panel" aria-label="Antrean verifikasi">
        <nav aria-label="Filter status verifikasi" class="flex flex-wrap gap-2 border-b border-line p-4">
            @foreach (['all' => 'Semua', 'ready' => 'Siap ditinjau', 'incomplete' => 'Belum lengkap', 'verified' => 'Disetujui', 'rejected' => 'Ditolak'] as $key => $label)
                <a href="{{ route('admin.verifications.index', array_filter(['status' => $key === 'all' ? null : $key, 'search' => ($search ?? '') !== '' ? $search : null])) }}"
                   @if ($status === $key) aria-current="page" @endif
                   class="inline-flex min-h-11 items-center gap-2 rounded-lg border px-3 text-sm {{ $status === $key ? 'border-field-line bg-accent font-bold' : 'border-line hover:bg-surface-raised' }}">
                    {{ $label }} <span class="tabular-nums">{{ $counts[$key] }}</span>
                </a>
            @endforeach
        </nav>
        <form method="GET" action="{{ route('admin.verifications.index') }}" class="flex flex-wrap items-center gap-2 border-b border-line px-4 py-3">
            @if ($status !== 'all')
                <input type="hidden" name="status" value="{{ $status }}">
            @endif
            <label for="verification-search" class="sr-only">Cari nama atau email pelanggan</label>
            <input id="verification-search" name="search" value="{{ $search ?? '' }}" placeholder="Cari nama atau email pelanggan..." class="min-w-[220px] flex-1 rounded-lg border border-field-line bg-surface-raised px-3 py-2 text-xs">
            <button type="submit" class="rounded-lg bg-action hover:bg-action-hover px-4 py-2 text-xs font-semibold text-ink transition-colors">Cari Pelanggan</button>
            @if (($search ?? '') !== '')
                <a href="{{ route('admin.verifications.index', $status === 'all' ? [] : ['status' => $status]) }}" class="text-xs font-semibold text-danger hover:underline">Reset</a>
            @endif
        </form>
        <p class="px-4 py-3 text-sm text-muted">{{ $customers->total() }} pelanggan pada daftar ini. Berkas lengkap belum berarti identitas disetujui.</p>
        <table class="queue-table w-full table-fixed text-left text-sm">
            <caption class="sr-only">Pelanggan, kelengkapan berkas, status akun, waktu pembaruan, dan tindakan pemeriksaan</caption>
            <thead class="hidden border-y border-line bg-surface-raised xl:table-header-group">
                <tr>
                    <th scope="col" class="w-[26%] px-4 py-3">Pelanggan</th>
                    <th scope="col" class="px-4 py-3">Berkas</th>
                    <th scope="col" class="px-4 py-3">Status akun</th>
                    <th scope="col" class="px-4 py-3">Diperbarui</th>
                    <th scope="col" class="px-4 py-3">Tindakan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($customers as $customer)
                    <tr class="hover:bg-surface-raised/60">
                        <td class="px-4 py-5"><p class="font-semibold">{{ $customer->name }}</p><p class="mt-1 text-muted">{{ $customer->email ?? 'Tanpa akun login' }}</p></td>
                        <td class="px-4 py-5"><span class="mb-1 block text-xs text-muted xl:hidden">Berkas</span>{{ $customer->hasSubmittedDocuments() ? 'Berkas lengkap' : 'Berkas belum lengkap' }}</td>
                        <td class="px-4 py-5">
                            <span class="mb-1 block text-xs text-muted xl:hidden">Status akun</span>
                            <span class="inline-flex rounded-md px-2 py-1 font-medium {{ $customer->isVerified() ? 'bg-success-surface text-success' : ($customer->isRejectedVerification() ? 'bg-danger-surface text-danger' : 'bg-warning-surface text-warning') }}">{{ $customer->isVerified() ? 'Disetujui' : ($customer->isRejectedVerification() ? 'Ditolak' : 'Menunggu peninjauan') }}</span>
                            @if ($customer->isPendingVerification() && $customer->identity_submitted_at?->lte($reviewCutoff))
                                <span class="block mt-1 font-semibold text-warning">SLA terlewati</span>
                            @endif
                        </td>
                        <td class="px-4 py-5 text-muted"><span class="mb-1 block text-xs xl:hidden">Diperbarui</span>{{ $customer->updated_at?->translatedFormat('d M Y, H:i') ?? 'Belum tersedia' }}</td>
                        <td class="px-4 py-5"><a class="ui-button ui-secondary w-full" href="{{ route('admin.verifications.show', $customer) }}">Periksa dokumen<span class="sr-only"> {{ $customer->name }}</span></a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="p-8 text-center"><p class="font-semibold">Tidak ada pelanggan pada daftar ini</p><p class="mt-2 text-muted">Pilih status lain untuk melihat pengajuan yang tersedia.</p><a class="ui-button ui-secondary mt-4" href="{{ route('admin.verifications.index') }}">Lihat semua pelanggan</a></td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($customers->hasPages())
            <div class="border-t border-line p-4">{{ $customers->links('layouts.pagination') }}</div>
        @endif
    </section>
</div>
@endsection
