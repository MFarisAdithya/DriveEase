@extends('layouts.admin')

@section('title', 'Ubah Profil Staf - DriveEase')

@section('content')
<div class="space-y-4">
    <a class="underline" href="{{ route('admin.staff.index') }}">Kembali ke daftar staf</a>
    <h1 class="text-xl font-bold">Ubah Profil Staf</h1>
    @include('admin.staff-fields')
</div>
@endsection
