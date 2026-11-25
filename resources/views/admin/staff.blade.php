@extends('layouts.admin')

@section('title', 'Manajemen Staf - DriveEase')

@section('content')
<div class="space-y-6">
    <h1 class="text-xl font-bold">Manajemen Staf</h1>
    @include('admin.staff-fields')
    <ul class="space-y-3">
        @forelse ($staff as $member)
            <li class="rounded-xl border bg-white p-4">
                <strong>{{ $member->name }}</strong>
                <span>{{ $member->email }}</span>
                <span>{{ $member->deactivated_at ? 'Nonaktif' : 'Aktif' }}</span>
                <a class="underline" href="{{ route('admin.staff.edit', $member) }}">Ubah profil</a>
                @if (!$member->deactivated_at)
                    <form method="POST" action="{{ route('admin.staff.deactivate', $member) }}">
                        @csrf
                        @method('PATCH')
                        <button class="underline">Nonaktifkan akun</button>
                    </form>
                @endif
            </li>
        @empty
            <li>Belum ada akun staf.</li>
        @endforelse
    </ul>
    {{ $staff->links() }}
</div>
@endsection
