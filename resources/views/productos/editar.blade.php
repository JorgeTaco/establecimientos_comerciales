<x-layout titulo="Editar producto">
    <div class="tarjeta">
        <h1 style="margin-top:0; font-size:1.35rem; margin-bottom:0.5rem;">Editar producto</h1>
        <p style="color:var(--tinta-suave); font-size:0.9rem; margin-bottom:1.5rem;">
            Modifica los datos de "{{ $producto->nombre }}".
        </p>

        @if ($error ?? null)
            <div class="aviso aviso--error">{{ $error }}</div>
        @endif
        @if ($errors->any())
            <div class="aviso aviso--error">{{ $errors->first() }}</div>
        @endif

        <form id="formProducto" data-api="{{ url('/api/productos/'.$producto->id) }}" data-metodo="PATCH" data-destino="{{ route('productos.index') }}">

            <div class="campo">
                <label for="nombre">Nombre del producto</label>
                <input type="text" id="nombre" name="nombre" placeholder="Ej: Yogur natural" required value="{{ old('nombre', $producto->nombre) }}" autocomplete="off">
            </div>
            <div class="campo">
                <label for="categoria_id">Categoría</label>
                <select id="categoria_id" name="categoria_id" required>
                    <option value="">Selecciona una categoría</option>
                    @foreach ($categorias as $cat)
                        <option value="{{ $cat['id'] }}" {{ old('categoria_id', $producto->categoria_id) == $cat['id'] ? 'selected' : '' }}>{{ $cat['nombre'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="campo">
                <label for="descripcion">Descripción</label>
                <textarea id="descripcion" name="descripcion" rows="3" placeholder="Describe tu producto...">{{ old('descripcion', $producto->descripcion) }}</textarea>
            </div>
            <div class="campo">
                <label for="precio_comercio">Precio al comercio (USD)</label>
                <input type="number" id="precio_comercio" name="precio_comercio" step="0.01" min="0" placeholder="0.00" value="{{ old('precio_comercio', $producto->precio_comercio ?? '') }}">
            </div>
            <div class="campo">
                <label for="precio_sugerido">Precio sugerido al público (USD)</label>
                <input type="number" id="precio_sugerido" name="precio_sugerido" step="0.01" min="0" placeholder="0.00" value="{{ old('precio_sugerido', $producto->precio_sugerido ?? '') }}">
            </div>
            <div class="campo">
                <label for="pedido_minimo">Pedido mínimo (unidades)</label>
                <input type="number" id="pedido_minimo" name="pedido_minimo" min="1" placeholder="1" value="{{ old('pedido_minimo', $producto->pedido_minimo ?? '') }}">
            </div>
            <div class="campo">
                <label for="capacidad_semanal">Capacidad semanal (unidades)</label>
                <input type="number" id="capacidad_semanal" name="capacidad_semanal" min="1" placeholder="1" value="{{ old('capacidad_semanal', $producto->capacidad_semanal ?? '') }}">
            </div>
            <div class="campo">
                <label for="vida_util_dias">Vida útil (días)</label>
                <input type="number" id="vida_util_dias" name="vida_util_dias" min="1" placeholder="1" value="{{ old('vida_util_dias', $producto->vida_util_dias ?? '') }}">
            </div>
            <div class="campo">
                <label style="display:flex; align-items:center; gap:0.5rem; font-weight:600; letter-spacing:0.04em; text-transform:uppercase; color:var(--tinta-suave); font-size:0.82rem;">
                    <input type="checkbox" name="requiere_refrigeracion" value="1" {{ (old('requiere_refrigeracion', $producto->requiere_refrigeracion ?? false) == '1' || old('requiere_refrigeracion', $producto->requiere_refrigeracion ?? false) === true) ? 'checked' : '' }}>
                    Requiere refrigeración
                </label>
            </div>
            <div class="campo">
                <label for="conservacion">Instrucciones de conservación</label>
                <textarea id="conservacion" name="conservacion" rows="2" placeholder="Ej: Mantener refrigerado entre 2 °C y 6 °C.">{{ old('conservacion', $producto->conservacion) }}</textarea>
            </div>
            <div class="campo">
                <label for="zonas_entrega">Zonas de entrega (separadas por coma)</label>
                <input type="text" id="zonas_entrega" name="zonas_entrega" placeholder="Mulaló, Tanicuchí, Guaytacama" value="{{ old('zonas_entrega', $producto->zonas_entrega ? implode(', ', $producto->zonas_entrega) : '') }}">
                <p class="pista" style="margin-top:0.25rem;">Ej: Mulaló, Tanicuchí, Guaytacama</p>
            </div>
            <div class="campo">
                <label for="frecuencia_entrega">Frecuencia de entrega</label>
                <input type="text" id="frecuencia_entrega" name="frecuencia_entrega" placeholder="Ej: Semanal" value="{{ old('frecuencia_entrega', $producto->frecuencia_entrega) }}">
            </div>
            <div class="campo">
                <label for="presentacion">Presentación</label>
                <input id="presentacion" name="presentacion" maxlength="255" value="{{ old('presentacion', $producto->presentacion) }}">
            </div>
            <div class="campo">
                <label for="archivo_foto">Fotografía</label>
                <input id="archivo_foto" name="archivo_foto" type="file" accept="image/jpeg,image/png,image/webp">
                <label for="foto_url">O enlace a la fotografía</label>
                <input id="foto_url" name="foto_url" type="url" maxlength="2048" value="{{ old('foto_url', $producto->foto_url) }}">
            </div>
            <div class="campo">
                <label for="documentacion">Documentación, una entrada por línea</label>
                <textarea id="documentacion" name="documentacion" rows="3">{{ old('documentacion', implode("\n", $producto->documentacion ?? [])) }}</textarea>
            </div>
            <p id="producto-aviso" role="status"></p>
            <button type="submit" class="boton" id="btnProducto" style="width:100%;">Guardar cambios</button>
        </form>

        <form id="formArchivar" method="post" action="{{ route('productos.destroy', $producto) }}" style="display:inline;" onsubmit="return confirm('¿Archivar este producto? Los productos archivados no se pueden enviar en propuestas.');">
            @csrf
            <input type="hidden" name="_method" value="DELETE">
            <button type="submit" class="boton boton--secundario" style="margin-top:1rem;">Archivar producto</button>
        </form>

    </div>

    @push('styles')
    <style>
        #formProducto { display: flex; flex-direction: column; gap: 0; }
        #formProducto .campo:last-child { margin-bottom: 1.25rem; }
        textarea { width: 100%; padding: 0.6rem 0.8rem; font-size: 0.95rem; color: var(--tinta); background: var(--superficie-suave); border: 1px solid var(--borde); border-radius: 8px; font-family: inherit; }
        textarea:focus { outline: 2px solid var(--verde); outline-offset: 2px; border-color: var(--verde); }
    </style>
    @endpush

    <script src="{{ asset('js/producto-formulario.js') }}" defer></script>
</x-layout>
