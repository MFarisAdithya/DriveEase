@if ($errors->any())
    <ul role="alert" class="text-sm">
        @foreach ($errors->all() as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif
<form method="POST" action="{{ isset($member) ? route('admin.staff.update', $member) : route('admin.staff.store') }}" class="max-w-lg space-y-2 rounded-xl border bg-white p-4">
    @csrf
    @isset($member)
        @method('PATCH')
    @endisset
    <label for="name" class="block">Nama</label>
    <input id="name" name="name" value="{{ old('name', $member->name ?? '') }}" required class="w-full rounded-lg border p-2">
    <label for="email" class="block">Email</label>
    <input id="email" name="email" type="email" value="{{ old('email', $member->email ?? '') }}" required class="w-full rounded-lg border p-2">
    <label for="phone" class="block">Nomor telepon</label>
    <input id="phone" name="phone" type="tel" value="{{ old('phone', $member->phone ?? '') }}" class="w-full rounded-lg border p-2">
    @if (!isset($member))
        <label for="password" class="block">Kata sandi awal</label>
        <input id="password" name="password" type="password" autocomplete="new-password" minlength="8" required class="w-full rounded-lg border p-2">
        <label for="password_confirmation" class="block">Konfirmasi kata sandi</label>
        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required class="w-full rounded-lg border p-2">
    @endif
    <button class="rounded-lg bg-[#002941] px-4 py-2 text-white">{{ isset($member) ? 'Simpan Profil' : 'Tambah Staf' }}</button>
</form>
