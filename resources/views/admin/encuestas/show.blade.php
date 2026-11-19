<x-layout titulo="Filas de la encuesta">
    <h1>Filas de la encuesta</h1>
    <p>{{ $encuesta->archivo }}</p>
    <p>{{ $encuesta->resumen['total'] }} filas · {{ $encuesta->resumen['importadas'] }} fichas publicadas · {{ $encuesta->resumen['posibles_duplicados'] }} posibles duplicados · {{ $encuesta->resumen['revisar'] }} ubicaciones por revisar</p>

    <form method="get" action="{{ route('admin.encuestas.show', $encuesta) }}" class="fila">
        <div class="campo">
            <label for="estado">Estado de importación</label>
            <select name="estado" id="estado">
                <option value="">Todos</option>
                @foreach (\App\Models\FilaEncuesta::ESTADOS as $clave => $nombre)
                    <option value="{{ $clave }}" @selected($estado === $clave)>{{ $nombre }}</option>
                @endforeach
            </select>
        </div>
        <button class="boton" type="submit">Filtrar</button>
    </form>

    <ul class="filas-encuesta">
        @forelse ($filas as $fila)
            <li class="tarjeta">
                <h2><a href="{{ route('admin.encuestas.filas.show', [$encuesta, $fila]) }}">Fila {{ $fila->numero }} · {{ \Illuminate\Support\Str::limit($fila->nombre ?? 'Sin nombre', 100) }}</a></h2>
                <p>{{ \App\Models\FilaEncuesta::ESTADOS[$fila->estado] }}</p>
            </li>
        @empty
            <li>Este filtro no tiene filas.</li>
        @endforelse
    </ul>

    <x-paginacion-encuesta :resultados="$filas" />

    @push('styles')
        <style>
            .filas-encuesta { display: grid; gap: 0.55rem; padding: 0; list-style: none; }
            .filas-encuesta .tarjeta { padding: 0.75rem 1rem; overflow-wrap: anywhere; }
            .filas-encuesta h2 { font: 600 1rem/1.4 Inter, system-ui, sans-serif; margin: 0; }
            .filas-encuesta p { color: var(--tinta-suave); margin: 0.15rem 0 0; }
        </style>
    @endpush
</x-layout>
