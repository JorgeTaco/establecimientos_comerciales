@php
    $directorio = 'establecimientos.index';
    $usuario = auth()->user();
    $secciones = [['Directorio', $directorio, ['inicio', 'establecimientos.*', 'catalogos.publico']]];

    if ($usuario?->suspendido_en === null) {
        $secciones = [...$secciones, ...match ($usuario?->rol) {
            'emprendedor' => [
                ['Mis productos', 'productos.index', ['productos.*']],
                ['Mis oportunidades', 'oportunidades.index', ['oportunidades.*']],
            ],
            'comercio' => [
                ['Mis establecimientos', 'comercio.establecimientos', ['comercio.*']],
                ['Propuestas recibidas', 'propuestas.recibidas', ['propuestas.*']],
            ],
            'administrador' => [['Panel administrativo', 'admin.indicadores', ['admin.*', 'comercio.*']]],
            default => [],
        }];
    }
    $secciones[] = ['Evaluación', 'evaluacion', ['evaluacion']];
@endphp

<div class="navegacion">
    <x-marca />

    <nav class="navegacion__principal" aria-label="Navegación principal">
        @foreach ($secciones as [$etiqueta, $ruta, $grupo])
            @php($actual = request()->routeIs($ruta) || ($ruta === $directorio && request()->routeIs('inicio')))
            <a href="{{ route($ruta) }}" @if ($actual) aria-current="page" @elseif (request()->routeIs(...$grupo)) aria-current="location" @endif>{{ $etiqueta }}</a>
        @endforeach
    </nav>

    <nav class="navegacion__cuenta" aria-label="Cuenta">
        @auth
            <form method="post" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="navegacion__sesion">Cerrar sesión</button>
            </form>
        @else
            <a href="{{ route('login') }}" @if (request()->routeIs('login')) aria-current="page" @endif>Iniciar sesión</a>
            <a href="{{ route('registro') }}" @if (request()->routeIs('registro')) aria-current="page" @endif>Crear cuenta</a>
        @endauth
    </nav>
</div>
