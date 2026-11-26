@extends('layouts.admin')

@section('title', 'Kategori Kendaraan - DriveEase Admin')
@section('page_title', 'Kelola Kategori Kendaraan')

@section('content')
<div class="space-y-6">
    <!-- Top Action Bar -->
    <div class="bg-surface rounded-xl p-5 border border-line shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <!-- Search Form -->
        <form action="{{ route('admin.car-types.index') }}" method="GET" class="flex items-center gap-2 flex-1 w-full">
            <div class="relative w-full">
                <label for="admin_car_type_search" class="sr-only">Cari kategori atau deskripsi kendaraan</label>
                <input type="text" 
                       id="admin_car_type_search"
                       name="search" 
                       value="{{ request('search') }}" 
                       placeholder="Cari kategori atau deskripsi..." 
                       class="bg-surface-raised w-full pl-9 pr-3 py-2 text-xs rounded-lg border border-field-line focus:outline-none focus:border-focus focus:ring-1 focus:ring-focus">
                <svg class="w-4 h-4 text-muted absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>
            <button type="submit" class="ml-auto min-h-10 shrink-0 px-4 py-2 bg-surface-hover hover:bg-line text-ink text-xs font-semibold rounded-lg transition-colors">
                Cari
            </button>
            @if(request('search'))
                <a href="{{ route('admin.car-types.index') }}" class="text-xs text-danger hover:underline">Reset</a>
            @endif
        </form>

        <!-- Add Button -->
        <a href="{{ route('admin.car-types.create') }}" class="inline-flex min-h-10 items-center gap-2 self-end px-4 py-2 bg-action hover:bg-action-hover text-ink text-xs font-semibold rounded-lg shadow-sm transition-colors shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Tambah Kategori Baru
        </a>
    </div>

    <!-- Category Table -->
    <div class="bg-surface rounded-xl border border-line shadow-sm overflow-hidden">
        <div role="region" aria-label="Daftar kategori kendaraan" tabindex="0" class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-surface-raised text-[11px] font-semibold text-muted uppercase tracking-wider border-b border-line">
                        <th scope="col" class="py-3 px-6">Nama Kategori</th>
                        <th scope="col" class="py-3 px-6">Deskripsi</th>
                        <th scope="col" class="py-3 px-6">Jumlah Armada Terkait</th>
                        <th scope="col" class="py-3 px-6 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line text-sm">
                    @forelse ($carTypes as $type)
                        <tr class="hover:bg-surface-raised/80 transition-colors">
                            <td class="py-3.5 px-6 font-bold text-ink">
                                {{ $type->name }}
                            </td>
                            <td class="py-3.5 px-6 text-muted text-xs max-w-md">
                                {{ $type->description ?: 'Tidak ada deskripsi.' }}
                            </td>
                            <td class="py-3.5 px-6">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $type->cars_count > 0 ? 'bg-surface-hover text-link border border-focus' : 'bg-surface-hover text-muted' }}">
                                    {{ $type->cars_count }} Armada
                                </span>
                            </td>
                            <td class="py-3.5 px-6 text-right">
                                <div class="inline-flex items-center gap-2">
                                    <a href="{{ route('admin.car-types.edit', $type) }}" 
                                       class="p-1.5 text-link hover:text-link hover:bg-surface-hover rounded transition-colors"
                                       title="Ubah Kategori">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </a>
                                    @if ($type->cars_count == 0)
                                        <form action="{{ route('admin.car-types.destroy', $type) }}" 
                                              method="POST" 
                                              class="inline-block"
                                              onsubmit="return confirm('Apakah Anda yakin ingin menghapus kategori {{ $type->name }}?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" 
                                                    class="p-1.5 text-danger hover:text-danger hover:bg-danger-surface rounded transition-colors"
                                                    title="Hapus Kategori">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                </svg>
                                            </button>
                                        </form>
                                    @else
                                        <span class="p-1.5 text-muted cursor-not-allowed" title="Kategori sedang digunakan oleh armada">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                            </svg>
                                        </span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-10 text-center text-muted">
                                <p class="font-medium">Tidak ada kategori yang ditemukan.</p>
                                <a href="{{ route('admin.car-types.index') }}" class="text-xs text-link hover:underline mt-1 inline-block">Hapus filter</a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($carTypes->hasPages())
            <div class="p-4 border-t border-line">
                {{ $carTypes->links('layouts.pagination') }}
            </div>
        @endif
    </div>
</div>
@endsection
