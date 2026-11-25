@if ($errors->any())
    <ul role="alert" class="rounded-xl border border-danger-line bg-danger-surface p-3.5 text-sm text-danger space-y-1">
        @foreach ($errors->all() as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif
{{-- isset($member) memilih route pembaruan staf atau pembuatan staf baru, dan menyisipkan @method('PATCH') hanya saat mengubah profil. --}}
<form method="POST" action="{{ isset($member) ? route('admin.staff.update', $member) : route('admin.staff.store') }}" class="max-w-2xl space-y-4 rounded-xl border border-line bg-surface p-6 shadow-sm">
    @csrf
    @isset($member)
        @method('PATCH')
    @endisset
    <h3 class="text-sm font-bold text-ink border-b border-line pb-3">{{ isset($member) ? 'Perbarui Profil Staf' : 'Tambah Akun Staf Baru' }}</h3>
    <div>
        <label for="name" class="block text-xs font-semibold text-ink mb-1">Nama</label>
        <input id="name" name="name" value="{{ old('name', $member->name ?? '') }}" required class="w-full rounded-lg border {{ $errors->has('name') ? 'border-danger-line' : 'border-field-line' }} bg-surface-raised p-2.5 text-sm">
        @error('name')
            <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
        @enderror
    </div>
    <div>
        <label for="email" class="block text-xs font-semibold text-ink mb-1">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email', $member->email ?? '') }}" required class="w-full rounded-lg border {{ $errors->has('email') ? 'border-danger-line' : 'border-field-line' }} bg-surface-raised p-2.5 text-sm">
        @error('email')
            <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
        @enderror
    </div>
    <div>
        <label for="phone" class="block text-xs font-semibold text-ink mb-1">Nomor telepon (opsional)</label>
        <input id="phone" name="phone" type="tel" value="{{ old('phone', $member->phone ?? '') }}" class="w-full rounded-lg border {{ $errors->has('phone') ? 'border-danger-line' : 'border-field-line' }} bg-surface-raised p-2.5 text-sm">
        @error('phone')
            <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
        @enderror
    </div>
    @if (!isset($member))
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="password" class="block text-xs font-semibold text-ink mb-1">Kata sandi awal</label>
                <input id="password" name="password" type="password" autocomplete="new-password" minlength="8" required class="w-full rounded-lg border {{ $errors->has('password') ? 'border-danger-line' : 'border-field-line' }} bg-surface-raised p-2.5 text-sm">
                @error('password')
                    <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="password_confirmation" class="block text-xs font-semibold text-ink mb-1">Konfirmasi kata sandi</label>
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required class="w-full rounded-lg border border-field-line bg-surface-raised p-2.5 text-sm">
            </div>
        </div>
    @endif
    <button type="submit" class="rounded-lg bg-action hover:bg-action-hover px-5 py-2.5 text-xs font-semibold text-ink shadow-sm transition-colors">{{ isset($member) ? 'Simpan Profil' : 'Tambah Staf' }}</button>
</form>
