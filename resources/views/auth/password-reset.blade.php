@extends('layouts.app')

@section('title', 'Pemulihan Akun - DriveEase')

@section('body')
<main class="relative min-h-screen flex items-center justify-center bg-canvas px-4 py-10">
    @include('partials.driveease-backdrop')
    <div class="relative z-10 w-full max-w-md rounded-3xl border border-field-line bg-white p-8">
        <h1 class="mb-4 text-lg font-bold text-ink">{{ isset($token) ? 'Buat Kata Sandi Baru' : 'Lupa Kata Sandi' }}</h1>
        @if (session('success'))
            <p role="status" class="mb-4 text-sm">{{ session('success') }}</p>
        @endif
        @if ($errors->any())
            <ul role="alert" class="mb-4 text-sm">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        @endif
        {{-- isset() membedakan formulir permintaan email dan penggantian kata sandi
             dari keberadaan token yang dikirim controller. @csrf menyisipkan token
             sesi agar Laravel dapat memeriksa asal pengiriman formulir. --}}
        <form method="POST" action="{{ route(isset($token) ? 'password.update' : 'password.email') }}" class="space-y-4">
            @csrf
            <label for="email" class="block text-sm font-semibold">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email', is_string($email ?? null) ? $email : '') }}" autocomplete="email" required class="w-full rounded-lg border border-field-line p-2">
            @isset($token)
                <input type="hidden" name="token" value="{{ $token }}">
                <label for="password" class="block text-sm font-semibold">Kata sandi baru</label>
                <input id="password" name="password" type="password" autocomplete="new-password" minlength="8" required class="w-full rounded-lg border border-field-line p-2">
                <label for="password_confirmation" class="block text-sm font-semibold">Ulangi kata sandi baru</label>
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required class="w-full rounded-lg border border-field-line p-2">
            @endisset
            <button type="submit" class="w-full rounded-lg bg-[#002941] p-3 text-sm font-semibold text-white">{{ isset($token) ? 'Simpan Kata Sandi' : 'Kirim Tautan Pemulihan' }}</button>
        </form>
        <a href="{{ route('login') }}" class="mt-4 inline-block text-sm underline">Kembali ke halaman masuk</a>
    </div>
</main>
@endsection
