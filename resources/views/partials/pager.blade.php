{{-- Simple Previous / Next pager for client pages (2026-11-01). Our own markup, so it
     doesn't depend on Laravel's built-in pagination views (Laravel 13 no longer ships
     "pagination::simple-default", which broke My journal).
     Usage: @include('partials.pager', ['paginator' => $entries]) --}}
@if ($paginator->hasPages())
    <nav aria-label="More pages" style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:14px;">
        @if ($paginator->onFirstPage())
            <span class="btn ghost sm" style="border-radius:999px;opacity:.4;pointer-events:none;">Newer</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" class="btn ghost sm" style="border-radius:999px;" rel="prev">Newer</a>
        @endif
        <span style="font-size:12.5px;color:var(--ink-500);">Page {{ $paginator->currentPage() }}{{ method_exists($paginator, 'lastPage') ? ' of '.$paginator->lastPage() : '' }}</span>
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" class="btn ghost sm" style="border-radius:999px;" rel="next">Older</a>
        @else
            <span class="btn ghost sm" style="border-radius:999px;opacity:.4;pointer-events:none;">Older</span>
        @endif
    </nav>
@endif
