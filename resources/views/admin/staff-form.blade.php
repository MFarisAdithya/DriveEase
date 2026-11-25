@extends('layouts.admin')

@section('title', 'Ubah Profil Staf - DriveEase')
@section('page_title', 'Ubah Profil Staf')

@section('content')
<div class="mx-auto max-w-2xl space-y-4">
    <a class="inline-flex items-center gap-1.5 text-xs font-semibold text-muted hover:text-ink underline" href="{{ route('admin.staff.index') }}">&larr; Kembali ke daftar staf</a>
    <h2 class="text-xl font-bold text-ink">Ubah Profil Staf</h2>
    @include('admin.staff-fields')
</div>
@endsection
