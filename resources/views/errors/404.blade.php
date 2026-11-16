@extends('layouts.app')

@section('title', '404 - Halaman Tidak Ditemukan | DriveEase')

@section('body')
<div class="min-h-screen flex items-center justify-center p-4 bg-canvas text-center">
    <div class="max-w-md w-full bg-surface rounded-2xl p-8 border border-line shadow-xl">
        <div class="w-16 h-16 rounded-full bg-warning-surface text-warning flex items-center justify-center mx-auto mb-4">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>
        <h1 class="text-3xl font-extrabold text-ink mb-2">404</h1>
        <h2 class="text-base font-bold text-ink mb-2">Halaman Tidak Ditemukan</h2>
        <p class="text-xs text-muted mb-6 leading-relaxed">
            Halaman atau data kendaraan yang Anda cari tidak tersedia atau tautan telah kedaluwarsa.
        </p>
        <div>
            <a href="{{ route('home') }}" class="px-5 py-2.5 bg-action hover:bg-action-hover text-ink text-xs font-semibold rounded-lg shadow-sm transition-colors inline-block">
                Kembali ke Beranda
            </a>
        </div>
    </div>
</div>
@endsection
