{{-- Paginación del catálogo, con las clases de botón del panel --}}
@if ($paginator->hasPages())
    <nav class="panel-pagination" role="navigation" aria-label="Paginación">
        @if ($paginator->onFirstPage())
            <span class="tbl-btn" aria-disabled="true">
                <x-icon name="chevron-left" class="h-4 w-4" /> Anterior
            </span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" class="tbl-btn" rel="prev">
                <x-icon name="chevron-left" class="h-4 w-4" /> Anterior
            </a>
        @endif

        <span class="tbl-pageinfo">
            Página {{ $paginator->currentPage() }} de {{ $paginator->lastPage() }}
        </span>

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" class="tbl-btn" rel="next">
                Siguiente <x-icon name="chevron-right" class="h-4 w-4" />
            </a>
        @else
            <span class="tbl-btn" aria-disabled="true">
                Siguiente <x-icon name="chevron-right" class="h-4 w-4" />
            </span>
        @endif
    </nav>
@endif
