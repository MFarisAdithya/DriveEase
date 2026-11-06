@extends('layouts.admin')

@section('title', 'Kategori Kendaraan - DriveEase Admin')
@section('page_title', 'Kelola Kategori Kendaraan')

@section('content')
<div class="space-y-6">
    <!-- Top Action Bar -->
    <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <!-- Search Form -->
        <form action="{{ route('admin.car-types.index') }}" method="GET" class="flex items-center gap-2 flex-1 max-w-md">
            <div class="relative w-full">
                <input type="text" 
                       name="search" 
                       value="{{ request('search') }}" 
                       placeholder="Cari kategori atau deskripsi..." 
                       class="w-full pl-9 pr-3 py-2 text-xs rounded-lg border border-slate-300 focus:outline-none focus:border-teal-600 focus:ring-1 focus:ring-teal-500">
                <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>
            <button type="submit" class="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white text-xs font-semibold rounded-lg transition-colors">
                Cari
            </button>
            @if(request('search'))
                <a href="{{ route('admin.car-types.index') }}" class="text-xs text-rose-600 hover:underline">Reset</a>
            @endif
        </form>

        <!-- Add Button -->
        <a href="{{ route('admin.car-types.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white text-xs font-semibold rounded-lg shadow-sm transition-colors shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Tambah Kategori Baru
        </a>
    </div>

    <!-- Category Table -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 text-[11px] font-semibold text-slate-500 uppercase tracking-wider border-b border-slate-200">
                        <th class="py-3 px-6">Nama Kategori</th>
                        <th class="py-3 px-6">Deskripsi</th>
                        <th class="py-3 px-6">Jumlah Armada Terkait</th>
                        <th class="py-3 px-6 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    @forelse ($carTypes as $type)
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="py-3.5 px-6 font-bold text-slate-900">
                                {{ $type->name }}
                            </td>
                            <td class="py-3.5 px-6 text-slate-600 text-xs max-w-md">
                                {{ $type->description ?: 'Tidak ada deskripsi.' }}
                            </td>
                            <td class="py-3.5 px-6">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $type->cars_count > 0 ? 'bg-teal-50 text-teal-800 border border-teal-200' : 'bg-slate-100 text-slate-600' }}">
                                    {{ $type->cars_count }} Armada
                                </span>
                            </td>
                            <td class="py-3.5 px-6 text-right">
                                <div class="inline-flex items-center gap-2">
                                    <a href="{{ route('admin.car-types.edit', $type) }}" 
                                       class="p-1.5 text-teal-600 hover:text-teal-900 hover:bg-teal-50 rounded transition-colors"
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
                                                    class="p-1.5 text-rose-600 hover:text-rose-900 hover:bg-rose-50 rounded transition-colors"
                                                    title="Hapus Kategori">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                </svg>
                                            </button>
                                        </form>
                                    @else
                                        <span class="p-1.5 text-slate-300 cursor-not-allowed" title="Kategori sedang digunakan oleh armada">
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
                            <td colspan="4" class="py-10 text-center text-slate-500">
                                <p class="font-medium">Tidak ada kategori yang ditemukan.</p>
                                <a href="{{ route('admin.car-types.index') }}" class="text-xs text-teal-600 hover:underline mt-1 inline-block">Reset filter</a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($carTypes->hasPages())
            <div class="p-4 border-t border-slate-200">
                {{ $carTypes->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
