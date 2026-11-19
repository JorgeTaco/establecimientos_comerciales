@props(['resultados'])

@if ($resultados->hasPages())
    <nav class="fila" aria-label="Paginación">
        @if ($resultados->previousPageUrl())
            <a class="boton boton--secundario" rel="prev" href="{{ $resultados->previousPageUrl() }}">Anterior</a>
        @endif
        <span>Página {{ $resultados->currentPage() }}</span>
        @if ($resultados->nextPageUrl())
            <a class="boton boton--secundario" rel="next" href="{{ $resultados->nextPageUrl() }}">Siguiente</a>
        @endif
    </nav>
@endif
