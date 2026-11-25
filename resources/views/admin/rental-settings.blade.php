@extends('layouts.admin')

@section('title', 'Pengaturan Rental - DriveEase')

@section('content')
<form method="POST" action="{{ route('admin.rental-settings.update') }}" class="max-w-xl space-y-4 rounded-xl border bg-white p-6">
    @csrf
    @method('PATCH')
    <h1 class="text-xl font-bold">Pengaturan Rental</h1>
    {{-- Key array adalah nama input dan kolom database. Labelnya dibaca admin. --}}
    @foreach ([
        'business_name' => 'Nama usaha',
        'services' => 'Layanan rental',
        'address' => 'Alamat',
        'contact' => 'Kontak',
        'operating_hours' => 'Jam operasional (WIB)',
        'terms' => 'Ketentuan rental',
        'bank_name' => 'Nama bank',
        'account_number' => 'Nomor rekening',
        'account_holder' => 'Nama pemilik rekening',
    ] as $field => $label)
        <div>
            <label for="{{ $field }}" class="block text-sm font-semibold">{{ $label }}</label>
            @if (in_array($field, ['services', 'address', 'terms'], true))
                <textarea id="{{ $field }}" name="{{ $field }}" required class="w-full rounded-lg border p-2">{{ old($field, $settings->$field ?? '') }}</textarea>
            @else
                <input id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $settings->$field ?? '') }}" required class="w-full rounded-lg border p-2">
            @endif
            @error($field)
                <p role="alert" class="text-sm text-danger">{{ $message }}</p>
            @enderror
        </div>
    @endforeach
    <button class="rounded-lg bg-[#002941] px-4 py-2 text-white">Simpan Pengaturan</button>
</form>
@endsection
