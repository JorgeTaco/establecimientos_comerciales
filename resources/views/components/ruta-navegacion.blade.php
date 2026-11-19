@if ($titulo && $padres)
    @php($inmediato = $padres[array_key_last($padres)])
    <nav class="ruta-navegacion" aria-label="Ruta de navegación">
        <a class="ruta-navegacion__volver" href="{{ $inmediato['url'] }}" aria-label="Volver · {{ $inmediato['nombre'] }}">
            <span aria-hidden="true">←</span> Volver
        </a>
        <ol class="ruta-navegacion__lista">
            @foreach ($padres as $padre)
                <li><a href="{{ $padre['url'] }}">{{ $padre['nombre'] }}</a></li>
            @endforeach
            <li aria-current="page">{{ $titulo }}</li>
        </ol>
    </nav>
@endif
