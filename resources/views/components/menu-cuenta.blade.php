<nav class="navegacion" aria-label="Cuenta">
    @auth
        @if (auth()->user()->suspendido_en === null)
            @switch(auth()->user()->rol)
                @case('emprendedor')
                    <a href="{{ route('productos.index') }}">Mis productos</a>
                    <a href="{{ route('oportunidades.index') }}">Mis oportunidades</a>
                    @break
                @case('comercio')
                    <a href="{{ route('comercio.establecimientos') }}">Mis establecimientos</a>
                    <a href="{{ route('propuestas.recibidas') }}">Propuestas recibidas</a>
                    @break
                @case('administrador')
                    <a href="{{ route('admin.indicadores') }}">Panel admin</a>
                    @break
            @endswitch
        @endif
        <form method="post" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="boton boton--secundario">Cerrar sesión</button>
        </form>
    @else
        <a href="{{ route('login') }}">Iniciar sesión</a>
        <a href="{{ route('registro') }}">Crear cuenta</a>
    @endauth
</nav>
