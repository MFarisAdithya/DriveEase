@foreach ($adminNavigation as $group => $links)
    <div>
        <p class="px-3 pb-1 text-xs font-semibold text-muted">{{ $group }}</p>
        <ul class="space-y-0.5">
            @foreach ($links as [$destination, $pattern, $label, $icon])
                <li>
                    <a href="{{ route($destination) }}"
                       @if (request()->routeIs($pattern)) aria-current="{{ request()->routeIs($destination) ? 'page' : 'location' }}" @endif
                       class="flex min-h-10 items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs($pattern) ? 'bg-action-hover text-ink' : 'text-subtle hover:bg-surface-hover hover:text-ink' }}">
                        @if ($showIcon)
                            <svg aria-hidden="true" class="size-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $icon }}"/>
                            </svg>
                        @endif
                        <span>{{ $label }}</span>
                        @if ($destination === 'admin.verifications.index' && $pendingVerificationCount > 0)
                            <span class="ml-auto rounded-full bg-warning-surface px-2 py-0.5 text-xs font-bold text-warning">
                                {{ $pendingVerificationCount }}<span class="sr-only"> siap ditinjau</span>
                            </span>
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
@endforeach
