@if ($paginator->hasPages())
    <nav aria-label="Navigasi halaman" class="flex flex-wrap items-center justify-between gap-4 text-sm text-ink">
        @if ($paginator->onFirstPage())
            <span class="min-h-11 px-4 inline-flex items-center rounded-lg border border-line bg-canvas text-muted cursor-not-allowed" aria-disabled="true">
                Sebelumnya
            </span>
        @else
            <a rel="prev" href="{{ $paginator->previousPageUrl() }}" class="min-h-11 px-4 inline-flex items-center rounded-lg border border-field-line bg-surface font-semibold text-ink hover:bg-surface-raised transition-colors">
                Sebelumnya
            </a>
        @endif

        <span class="font-medium text-muted">
            Halaman <strong class="text-ink">{{ $paginator->currentPage() }}</strong> dari <strong class="text-ink">{{ $paginator->lastPage() }}</strong>
        </span>

        @if ($paginator->hasMorePages())
            <a rel="next" href="{{ $paginator->nextPageUrl() }}" class="min-h-11 px-4 inline-flex items-center rounded-lg border border-field-line bg-surface font-semibold text-ink hover:bg-surface-raised transition-colors">
                Berikutnya
            </a>
        @else
            <span class="min-h-11 px-4 inline-flex items-center rounded-lg border border-line bg-canvas text-muted cursor-not-allowed" aria-disabled="true">
                Berikutnya
            </span>
        @endif
    </nav>
@endif
