<x-layout titulo="Fila {{ $fila->numero }} de la encuesta">
    <h1>Fila {{ $fila->numero }} · {{ $fila->nombre ?? 'Sin nombre' }}</h1>
    <p>{{ \App\Models\FilaEncuesta::ESTADOS[$fila->estado] }}</p>

    @if ($fila->establecimiento_id)
        <a class="boton boton--secundario" href="{{ route('establecimientos.ficha', $fila->establecimiento_id) }}">Ver ficha en el directorio</a>
    @endif

    @if ($fila->estado === 'posible_duplicado')
        @if (!empty($fila->revision['candidatos']))
            <p>Coincidencias en el Excel</p>
            <ul>
                @foreach ($fila->revision['candidatos'] as $candidato)
                    <li>{{ $candidato['nombre'] }} · {{ implode(', ', $candidato['motivos']) }}</li>
                @endforeach
            </ul>
        @endif
        @if (!empty($fila->revision['candidatos_db']))
            <p>Coincidencias en la base de datos</p>
            <ul>
                @foreach ($fila->revision['candidatos_db'] as $candidato)
                    <li><a href="{{ route('establecimientos.ficha', $candidato['id']) }}">{{ $candidato['nombre'] ?: 'Ver ficha' }}</a> · {{ implode(', ', $candidato['motivos']) }}</li>
                @endforeach
            </ul>
        @endif
    @endif

    @if (!empty($fila->revision['coordenadas']['requiere_revision']))
        <p>{{ $fila->revision['coordenadas']['mensaje'] }}</p>
    @endif

    @if (!empty($fila->revision['avisos']) || !empty($fila->revision['errores']))
        <ul>
            @foreach (array_merge($fila->revision['avisos'], \Illuminate\Support\Arr::flatten($fila->revision['errores'])) as $aviso)
                <li>{{ $aviso }}</li>
            @endforeach
        </ul>
    @endif

    <section class="tarjeta">
        <h2>Datos originales del Excel</h2>
        <dl class="celdas-encuesta">
            @foreach ($campos as $campo)
                <div>
                    <dt>{{ $campo['nombre'] }}</dt>
                    <dd>{{ $campo['valor'] !== '' ? $campo['valor'] : '—' }}</dd>
                </div>
            @endforeach
        </dl>
    </section>

    @push('styles')
        <style>
            h1, .celdas-encuesta { overflow-wrap: anywhere; }
            .celdas-encuesta > div { padding: 0.6rem 0; border-top: 1px solid var(--borde); }
            .celdas-encuesta dt { color: var(--tinta-suave); font-size: 0.86rem; font-weight: 600; }
            .celdas-encuesta dd { margin: 0.2rem 0 0; white-space: pre-wrap; }
        </style>
    @endpush
</x-layout>
