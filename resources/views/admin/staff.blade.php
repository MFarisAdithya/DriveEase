@extends('layouts.admin')

@section('title', 'Manajemen Staf - DriveEase')
@section('page_title', 'Manajemen Staf')

@section('content')
<div class="max-w-5xl space-y-6">
    <div>
        <h2 class="text-xl font-bold text-ink">Manajemen Staf</h2>
        <p class="mt-1 text-xs text-muted">Tambahkan akun staf operasional, perbarui profil, atau nonaktifkan akses akun staf.</p>
    </div>

    @include('admin.staff-fields')

    <div class="bg-surface rounded-xl border border-line shadow-sm overflow-hidden">
        <div class="border-b border-line px-6 py-4">
            <h3 class="text-sm font-bold text-ink">Daftar Akun Staf</h3>
        </div>
        <ul class="divide-y divide-line">
            @forelse ($staff as $member)
                <li class="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between">
                    <div class="space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <strong class="text-sm font-bold text-ink">{{ $member->name }}</strong>
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $member->isDeactivated() ? 'bg-danger-surface text-danger' : 'bg-success-surface text-success' }}">
                                {{ $member->isDeactivated() ? 'Nonaktif' : 'Aktif' }}
                            </span>
                        </div>
                        <p class="text-xs text-muted">{{ $member->email }}{{ $member->phone ? ' · '.$member->phone : '' }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-3 text-xs font-semibold">
                        <a class="rounded-lg border border-field-line bg-surface-raised px-3 py-2 text-ink hover:bg-surface-hover transition-colors" href="{{ route('admin.staff.edit', $member) }}">Ubah profil</a>
                        @if (!$member->isDeactivated())
                            <form method="POST" action="{{ route('admin.staff.deactivate', $member) }}" onsubmit="return confirm('Nonaktifkan akun staf {{ $member->name }}?');">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="rounded-lg border border-danger-line bg-danger-surface px-3 py-2 text-danger hover:bg-danger-surface/80 transition-colors">Nonaktifkan akun</button>
                            </form>
                        @endif
                    </div>
                </li>
            @empty
                <li class="p-6 text-center text-sm text-muted">Belum ada akun staf.</li>
            @endforelse
        </ul>
        @if ($staff->hasPages())
            <div class="border-t border-line p-4">
                {{ $staff->links('layouts.pagination') }}
            </div>
        @endif
    </div>
</div>
@endsection
