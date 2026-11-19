<x-layout titulo="Mis productos">
    <div class="fila">
        <form method="post" action="{{ route('catalogo.compartir') }}">
            @csrf
            <button type="submit" class="boton boton--secundario">Compartir catálogo</button>
        </form>
        <form method="post" action="{{ route('catalogo.revocar') }}">
            @csrf
            @method('DELETE')
            <button type="submit" class="boton boton--secundario">Revocar enlace</button>
        </form>
    </div>
    @if (session('catalogo_url'))
        <p><a href="{{ session('catalogo_url') }}">{{ session('catalogo_url') }}</a></p>
    @endif
    <div style="display:flex; align-items:center; justify-content:space-between; gap:1rem; margin-bottom:1.5rem; flex-wrap:wrap;">
        <div>
            <h1 style="margin:0; font-size:1.35rem;">Mis productos</h1>
            <p style="color:var(--tinta-suave); font-size:0.9rem; margin-top:0.25rem;">
                Gestiona tu catálogo de productos para ofrecer a los comercios.
            </p>
        </div>
        <a href="{{ route('productos.crear') }}" class="boton">+ Nuevo producto</a>
    </div>

    @if ($error ?? null)
        <div class="aviso aviso--error">{{ $error }}</div>
    @endif

    @if (session('status'))
        <div class="aviso aviso--exito">{{ session('status') }}</div>
    @endif

    @if (count($productos ?? []) === 0)
        <div class="tarjeta" style="text-align:center; padding:3rem 1rem;">
            <svg viewBox="0 0 64 64" style="width:3rem; height:3rem; color:var(--tinta-suave); margin-bottom:1rem;" aria-hidden="true">
                <circle cx="32" cy="32" r="26" fill="none" stroke="var(--borde)" stroke-width="2"/>
                <path d="M20 22c0-4.4 3.6-8 8-8s8 3.6 8 8-3.6 8-8 8-8-3.6-8-8Z M32 18l-4 4 M32 18l4 4" fill="none" stroke="var(--borde)" stroke-width="2" stroke-linecap="round"/>
            </svg>
            <h2 style="margin:0 0 0.5rem; font-size:1.1rem;">Todavía no tienes productos</h2>
            <p style="color:var(--tinta-suave); font-size:0.9rem; margin-bottom:1.5rem;">
                Crea tu primer producto para poder enviarle propuestas a los comercios.
            </p>
            <a href="{{ route('productos.crear') }}" class="boton">Crear producto</a>
        </div>
    @else
        <div class="listado" style="display:grid; gap:0.75rem; padding:0; list-style:none;">
            @foreach ($productos as $producto)
                <div style="display:flex; align-items:center; justify-content:space-between; gap:1rem; padding:0.85rem 1rem; background:var(--superficie); border:1px solid var(--borde); border-radius:var(--radio-chico);">
                    <div style="flex:1; min-width:0;">
                        <div class="oportunidad__nombre" style="font-weight:600; margin-bottom:0.15rem;">{{ $producto->nombre }}</div>
                        <div class="oportunidad__meta" style="font-size:0.82rem; color:var(--tinta-suave);">
                            {{ $producto->categoria?->nombre }}
                            @if ($producto->precio_sugerido !== null) · ${{ number_format($producto->precio_sugerido, 2) }} sugerido @endif
                            @if ($producto->requiere_refrigeracion === true) · Refrigeración @endif
                        </div>
                    </div>
                    <div style="display:flex; gap:0.5rem; flex-shrink:0;">
                        <a href="{{ route('productos.show', ['producto' => $producto->id]) }}" class="boton boton--secundario" style="padding:0.4rem 0.8rem; font-size:0.85rem;">Ver</a>
                        @if ($producto->archivado_en === null)
                            <a href="{{ route('productos.compatibles', ['producto' => $producto->id]) }}" class="boton" style="padding:0.4rem 0.8rem; font-size:0.85rem;">Comercios compatibles</a>
                        @else
                            <span>Archivado</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <p class="pista" style="margin-top:2rem;">
        <a href="{{ route('oportunidades.index') }}">← Ver mis oportunidades</a>
    </p>
</x-layout>
