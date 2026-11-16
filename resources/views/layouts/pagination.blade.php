@if ($paginator->hasPages())
    <nav aria-label="Navigasi halaman" class="flex flex-wrap items-center justify-between gap-4 text-sm text-ink">
        @if ($paginator->onFirstPage())
            <span class="text-subtle" aria-disabled="true">Sebelumnya</span>
        @else
            <a rel="prev" href="{{ $paginator->previousPageUrl() }}" class="rounded border border-field-line bg-surface-raised px-3 py-2 hover:bg-surface-hover">Sebelumnya</a>
        @endif
        <span>Halaman {{ $paginator->currentPage() }} dari {{ $paginator->lastPage() }}</span>
        @if ($paginator->hasMorePages())
            <a rel="next" href="{{ $paginator->nextPageUrl() }}" class="rounded border border-field-line bg-surface-raised px-3 py-2 hover:bg-surface-hover">Berikutnya</a>
        @else
            <span class="text-subtle" aria-disabled="true">Berikutnya</span>
        @endif
    </nav>
@endif
