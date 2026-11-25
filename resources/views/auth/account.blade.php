@extends('layouts.app')

@section('title', 'Akun Saya - DriveEase')

@section('body')
<main class="min-h-screen bg-canvas px-4 py-10">
    <div class="mx-auto max-w-lg space-y-6 rounded-3xl border border-field-line bg-white p-6">
        <a href="{{ route('home') }}" class="text-sm underline">Kembali ke dasbor</a>
        <h1 class="text-lg font-bold text-ink">Akun Saya</h1>

        @if (session('success'))
            <p role="status">{{ session('success') }}</p>
        @endif
        @if ($errors->any())
            <ul role="alert" class="space-y-1 text-sm">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        @endif

        {{-- @method mengirim field tersembunyi agar Laravel membaca POST ini
             sebagai PATCH. old() menampilkan isian terakhir setelah validasi gagal. --}}
        <form method="POST" action="{{ route('account.profile') }}" class="space-y-3">
            @csrf
            @method('PATCH')
            <h2 class="font-semibold">Profil</h2>
            <label for="name" class="block text-sm">Nama</label>
            <input id="name" name="name" value="{{ old('name', $account->name) }}" required class="w-full rounded-lg border border-field-line p-2">
            <label for="email" class="block text-sm">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email', $account->email) }}" required class="w-full rounded-lg border border-field-line p-2">
            <label for="phone" class="block text-sm">Nomor telepon</label>
            <input id="phone" name="phone" type="tel" value="{{ old('phone', $account->phone) }}" @required($account->isCustomer()) class="w-full rounded-lg border border-field-line p-2">
            <button class="rounded-lg bg-[#002941] px-4 py-2 text-white">Simpan Profil</button>
        </form>
        <form method="POST" action="{{ route('account.password') }}" class="space-y-3 border-t border-field-line pt-4">
            @csrf
            @method('PATCH')
            <h2 class="font-semibold">Ubah Kata Sandi</h2>
            <label for="current_password" class="block text-sm">Kata sandi saat ini</label>
            <input id="current_password" name="current_password" type="password" autocomplete="current-password" required class="w-full rounded-lg border border-field-line p-2">
            <label for="password" class="block text-sm">Kata sandi baru</label>
            <input id="password" name="password" type="password" autocomplete="new-password" required minlength="8" class="w-full rounded-lg border border-field-line p-2">
            <label for="password_confirmation" class="block text-sm">Ulangi kata sandi baru</label>
            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required class="w-full rounded-lg border border-field-line p-2">
            <button class="rounded-lg bg-[#002941] px-4 py-2 text-white">Simpan Kata Sandi</button>
        </form>
    </div>
</main>
@endsection
