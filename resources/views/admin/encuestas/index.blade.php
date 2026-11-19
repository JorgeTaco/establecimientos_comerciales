<x-layout titulo="Encuestas importadas">
    <h1>Encuestas importadas</h1>

    @forelse ($encuestas as $encuesta)
        <article class="tarjeta">
            <h2>{{ $encuesta->archivo }}</h2>
            <p>{{ $encuesta->resumen['total'] }} filas guardadas · {{ $encuesta->created_at->format('d/m/Y') }}</p>
            <a href="{{ route('admin.encuestas.show', $encuesta) }}">Ver filas de la encuesta</a>
        </article>
    @empty
        <p>Todavía no hay encuestas archivadas.</p>
    @endforelse

    <x-paginacion-encuesta :resultados="$encuestas" />
</x-layout>
