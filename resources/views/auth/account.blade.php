@extends('layouts.app')

@section('title', 'Akun Saya - DriveEase')

@section('body')
<main class="relative min-h-screen bg-canvas px-4 py-10 overflow-hidden">
    @include('partials.driveease-backdrop')
    <div class="relative z-10 mx-auto max-w-2xl space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('home') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-ink underline hover:text-focus">
                &larr; Kembali ke dasbor
            </a>
            <span class="inline-flex items-center rounded-full border border-field-line bg-surface px-3 py-1 text-xs font-semibold text-ink">
                {{ $account->role?->display_name ?? 'Pengguna' }}
            </span>
        </div>

        <div class="space-y-6 rounded-3xl border border-[#C6DFEE] bg-white/95 p-6 sm:p-8 shadow-[0_24px_60px_rgba(11,40,64,0.09)] backdrop-blur-md">
            <div class="border-b border-line pb-4">
                <h1 class="text-xl font-bold text-ink">Akun Saya</h1>
                <p class="mt-1 text-sm text-muted">Perbarui data profil, ubah kata sandi secara berkala, atau keluar dari sesi aktif Anda.</p>
            </div>

            @if (session('success'))
                <p role="status" class="rounded-xl border border-success-line bg-success-surface p-3.5 text-sm font-medium text-success">{{ session('success') }}</p>
            @endif
            @if ($errors->any())
                <ul role="alert" class="space-y-1 rounded-xl border border-danger-line bg-danger-surface p-3.5 text-sm text-danger">
                    @foreach ($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            @endif

            {{-- @method mengirim field tersembunyi agar Laravel membaca POST ini
                 sebagai PATCH. old() menampilkan isian terakhir setelah validasi gagal. --}}
            <form method="POST" action="{{ route('account.profile') }}" class="space-y-4">
                @csrf
                @method('PATCH')
                <h2 class="text-base font-semibold text-ink">Profil</h2>
                <div>
                    <label for="name" class="block text-xs font-semibold text-ink mb-1">Nama</label>
                    <input id="name" name="name" value="{{ old('name', $account->name) }}" required class="w-full rounded-lg border {{ $errors->has('name') ? 'border-danger-line' : 'border-field-line' }} bg-surface-raised p-2.5 text-sm">
                    @error('name')
                        <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="email" class="block text-xs font-semibold text-ink mb-1">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email', $account->email) }}" required class="w-full rounded-lg border {{ $errors->has('email') ? 'border-danger-line' : 'border-field-line' }} bg-surface-raised p-2.5 text-sm">
                    @error('email')
                        <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="phone" class="block text-xs font-semibold text-ink mb-1">Nomor telepon {{ $account->isCustomer() ? '(wajib)' : '(opsional)' }}</label>
                    <input id="phone" name="phone" type="tel" value="{{ old('phone', $account->phone) }}" @required($account->isCustomer()) class="w-full rounded-lg border {{ $errors->has('phone') ? 'border-danger-line' : 'border-field-line' }} bg-surface-raised p-2.5 text-sm">
                    @error('phone')
                        <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="address" class="block text-xs font-semibold text-ink mb-1">Alamat domisili (opsional)</label>
                    <textarea id="address" name="address" rows="2" class="w-full rounded-lg border {{ $errors->has('address') ? 'border-danger-line' : 'border-field-line' }} bg-surface-raised p-2.5 text-sm">{{ old('address', $account->address) }}</textarea>
                    @error('address')
                        <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                    @enderror
                </div>
                <button type="submit" class="rounded-lg bg-action hover:bg-action-hover px-5 py-2.5 text-sm font-semibold text-ink shadow-sm transition-colors">Simpan Profil</button>
            </form>

            <form method="POST" action="{{ route('account.password') }}" class="space-y-4 border-t border-line pt-6">
                @csrf
                @method('PATCH')
                <h2 class="text-base font-semibold text-ink">Ubah Kata Sandi</h2>
                <div>
                    <label for="current_password" class="block text-xs font-semibold text-ink mb-1">Kata sandi saat ini</label>
                    <input id="current_password" name="current_password" type="password" autocomplete="current-password" required class="w-full rounded-lg border {{ $errors->has('current_password') ? 'border-danger-line' : 'border-field-line' }} bg-surface-raised p-2.5 text-sm">
                    @error('current_password')
                        <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="password" class="block text-xs font-semibold text-ink mb-1">Kata sandi baru</label>
                    <input id="password" name="password" type="password" autocomplete="new-password" required minlength="8" class="w-full rounded-lg border {{ $errors->has('password') ? 'border-danger-line' : 'border-field-line' }} bg-surface-raised p-2.5 text-sm">
                    @error('password')
                        <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="password_confirmation" class="block text-xs font-semibold text-ink mb-1">Ulangi kata sandi baru</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required class="w-full rounded-lg border border-field-line bg-surface-raised p-2.5 text-sm">
                </div>
                <button type="submit" class="rounded-lg bg-action hover:bg-action-hover px-5 py-2.5 text-sm font-semibold text-ink shadow-sm transition-colors">Simpan Kata Sandi</button>
            </form>

            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line pt-6">
                <div>
                    <h2 class="text-sm font-semibold text-ink">Keluar dari Akun</h2>
                    <p class="text-xs text-muted">Akhiri sesi pada perangkat ini dengan aman setelah selesai menggunakan aplikasi.</p>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="rounded-lg border border-danger-line bg-danger-surface px-4 py-2 text-xs font-semibold text-danger hover:bg-danger-surface/80 transition-colors">
                        Keluar Sekarang
                    </button>
                </form>
            </div>
        </div>
    </div>
</main>
@endsection
