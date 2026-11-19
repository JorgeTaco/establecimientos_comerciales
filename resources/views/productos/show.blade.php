<x-layout titulo="{{ $producto->nombre }}">
    <div style="display:flex; align-items:center; justify-content:space-between; gap:1rem; margin-bottom:1.5rem; flex-wrap:wrap;">
        <div>
            <h1 style="margin:0; font-size:1.35rem;">{{ $producto->nombre }}</h1>
            <p style="color:var(--tinta-suave); font-size:0.9rem; margin-top:0.25rem;">
                Detalle de tu producto en el catálogo.
            </p>
        </div>
        <div style="display:flex; gap:0.5rem;">
            <a href="{{ route('productos.edit', ['producto' => $producto->id]) }}" class="boton boton--secundario">Editar</a>
            <a href="{{ route('productos.compatibles', ['producto' => $producto->id]) }}" class="boton">Ver comercios compatibles</a>
        </div>
    </div>

    @if (session('status'))
        <div class="aviso aviso--exito">{{ session('status') }}</div>
    @endif

    <div class="tarjeta">
        <div class="ficha__rejilla" style="display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:0.5rem;">
            <div class="ficha__dato" style="padding:0.6rem 0.7rem; background:var(--superficie-suave); border-radius:10px; min-width:0; grid-column:1 / -1;">
                <p class="ficha__etiqueta">Nombre</p>
                <p class="ficha__valor">{{ $producto->nombre }}</p>
            </div>
            <div class="ficha__dato" style="padding:0.6rem 0.7rem; background:var(--superficie-suave); border-radius:10px; min-width:0;">
                <p class="ficha__etiqueta">Categoría</p>
                <p class="ficha__valor">{{ $producto->categoria_id ? 'Categoría #' . $producto->categoria_id : 'Sin categoría' }}</p>
            </div>
            <div class="ficha__dato" style="padding:0.6rem 0.7rem; background:var(--superficie-suave); border-radius:10px; min-width:0;">
                <p class="ficha__etiqueta">Precio al comercio (USD)</p>
                <p class="ficha__valor">{{ $producto->precio_comercio !== null ? number_format($producto->precio_comercio, 2) : 'No definido' }}</p>
            </div>
            <div class="ficha__dato" style="padding:0.6rem 0.7rem; background:var(--superficie-suave); border-radius:10px; min-width:0;">
                <p class="ficha__etiqueta">Precio sugerido (USD)</p>
                <p class="ficha__valor">{{ $producto->precio_sugerido !== null ? number_format($producto->precio_sugerido, 2) : 'No definido' }}</p>
            </div>
            <div class="ficha__dato" style="padding:0.6rem 0.7rem; background:var(--superficie-suave); border-radius:10px; min-width:0;">
                <p class="ficha__etiqueta">Pedido mínimo</p>
                <p class="ficha__valor">{{ $producto->pedido_minimo ?? 'No definido' }} unidades</p>
            </div>
            <div class="ficha__dato" style="padding:0.6rem 0.7rem; background:var(--superficie-suave); border-radius:10px; min-width:0;">
                <p class="ficha__etiqueta">Capacidad semanal</p>
                <p class="ficha__valor">{{ $producto->capacidad_semanal ?? 'No definida' }} unidades</p>
            </div>
            <div class="ficha__dato" style="padding:0.6rem 0.7rem; background:var(--superficie-suave); border-radius:10px; min-width:0;">
                <p class="ficha__etiqueta">Vida útil (días)</p>
                <p class="ficha__valor">{{ $producto->vida_util_dias ?? 'No definido' }}</p>
            </div>
            <div class="ficha__dato" style="padding:0.6rem 0.7rem; background:var(--superficie-suave); border-radius:10px; min-width:0;">
                <p class="ficha__etiqueta">Requiere refrigeración</p>
                <p class="ficha__valor">{{ $producto->requiere_refrigeracion === true ? 'Sí' : 'No' }}</p>
            </div>
            <div class="ficha__dato" style="padding:0.6rem 0.7rem; background:var(--superficie-suave); border-radius:10px; min-width:0; grid-column:1 / -1;">
                <p class="ficha__etiqueta">Zonas de entrega</p>
                <p class="ficha__valor">
                    @if ($producto->zonas_entrega && count($producto->zonas_entrega) > 0)
                        @foreach ($producto->zonas_entrega as $zona)
                            <span class="chip chip--dato" style="margin-right:0.35rem;">{{ $zona }}</span>
                        @endforeach
                    @else
                        No definidas
                    @endif
                </p>
            </div>
            <div class="ficha__dato" style="padding:0.6rem 0.7rem; background:var(--superficie-suave); border-radius:10px; min-width:0; grid-column:1 / -1;">
                <p class="ficha__etiqueta">Frecuencia de entrega</p>
                <p class="ficha__valor">{{ $producto->frecuencia_entrega ?? 'No definida' }}</p>
            </div>
            <div class="ficha__dato" style="padding:0.6rem 0.7rem; background:var(--superficie-suave); border-radius:10px; min-width:0; grid-column:1 / -1;">
                <p class="ficha__etiqueta">Descripción</p>
                <p class="ficha__valor">{{ $producto->descripcion ?? 'Sin descripción' }}</p>
            </div>
            <div class="ficha__dato" style="padding:0.6rem 0.7rem; background:var(--superficie-suave); border-radius:10px; min-width:0; grid-column:1 / -1;">
                <p class="ficha__etiqueta">Conservación</p>
                <p class="ficha__valor">{{ $producto->conservacion ?? 'No especificada' }}</p>
            </div>
            <div class="ficha__dato" style="padding:0.6rem 0.7rem; background:var(--superficie-suave); border-radius:10px; min-width:0; grid-column:1 / -1;">
                <p class="ficha__etiqueta">Estado</p>
                <p class="ficha__valor">{{ $producto->archivado_en ? 'Archivado' : 'Activo' }}</p>
            </div>
        </div>
    </div>

    <p class="pista" style="margin-top:2rem;">
        <a href="{{ route('productos.index') }}">← Volver a mis productos</a>
    </p>
</x-layout>