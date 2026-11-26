@extends('layouts.admin')

@section('title', 'Pengaturan Rental - DriveEase')
@section('page_title', 'Pengaturan Rental')

@section('content')
<form method="POST" action="{{ route('admin.rental-settings.update') }}" class="mx-auto max-w-3xl space-y-6">
    @csrf
    @method('PATCH')

    <div class="rounded-xl border border-line bg-surface p-6 shadow-sm space-y-4">
        <div class="border-b border-line pb-3">
            <h2 class="text-base font-bold text-ink">Informasi Usaha &amp; Ketentuan</h2>
            <p class="mt-1 text-xs text-muted">Data berikut ditampilkan kepada pengunjung pada halaman beranda DriveEase.</p>
        </div>
        {{-- Key array adalah nama input dan kolom database. Labelnya dibaca admin. --}}
        @foreach ([
            'business_name' => 'Nama usaha',
            'services' => 'Layanan rental',
            'address' => 'Alamat',
            'contact' => 'Kontak',
            'operating_hours' => 'Jam operasional (WIB)',
            'terms' => 'Ketentuan rental',
        ] as $field => $label)
            <div>
                <label for="{{ $field }}" class="block text-xs font-semibold text-ink mb-1">{{ $label }}</label>
                @if (in_array($field, ['services', 'address', 'terms'], true))
                    <textarea id="{{ $field }}" name="{{ $field }}" rows="3" required class="w-full rounded-lg border {{ $errors->has($field) ? 'border-danger-line' : 'border-field-line' }} bg-surface-raised p-2.5 text-sm">{{ old($field, $settings->$field ?? '') }}</textarea>
                @else
                    <input id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $settings->$field ?? '') }}" required class="w-full rounded-lg border {{ $errors->has($field) ? 'border-danger-line' : 'border-field-line' }} bg-surface-raised p-2.5 text-sm">
                @endif
                @error($field)
                    <p role="alert" class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                @enderror
            </div>
        @endforeach
    </div>

    <div class="rounded-xl border border-line bg-surface p-6 shadow-sm space-y-4">
        <div class="border-b border-line pb-3">
            <h2 class="text-base font-bold text-ink">Rekening Tujuan Pembayaran</h2>
            <p class="mt-1 text-xs text-muted">Nomor rekening hanya digunakan pada halaman transaksi terautentikasi dan tidak ditampilkan di beranda publik.</p>
        </div>
        @foreach ([
            'bank_name' => 'Nama bank',
            'account_number' => 'Nomor rekening',
            'account_holder' => 'Nama pemilik rekening',
        ] as $field => $label)
            <div>
                <label for="{{ $field }}" class="block text-xs font-semibold text-ink mb-1">{{ $label }}</label>
                <input id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $settings->$field ?? '') }}" required class="w-full rounded-lg border {{ $errors->has($field) ? 'border-danger-line' : 'border-field-line' }} bg-surface-raised p-2.5 text-sm">
                @error($field)
                    <p role="alert" class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                @enderror
            </div>
        @endforeach
        <div class="flex justify-end pt-2">
            <button type="submit" class="rounded-lg bg-action hover:bg-action-hover px-5 py-2.5 text-xs font-semibold text-ink shadow-sm transition-colors">Simpan Pengaturan</button>
        </div>
    </div>
</form>
@endsection
