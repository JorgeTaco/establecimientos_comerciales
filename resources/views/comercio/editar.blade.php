<x-layout titulo="{{ $establecimiento ? 'Editar establecimiento' : 'Registrar establecimiento' }}">
    <h1>{{ $establecimiento ? 'Editar establecimiento' : 'Registrar establecimiento' }}</h1>

    <section class="tarjeta">
        @if ($errors->any())
            <ul class="aviso aviso--error" role="alert">
                @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
            </ul>
        @endif
        <form method="post" action="{{ $establecimiento ? route('comercio.update', $establecimiento['id']) : route('comercio.store') }}">
            @csrf
            @if ($establecimiento) @method('PATCH') @endif

            @foreach (['nombre' => 'Nombre del comercio', 'tipo' => 'Tipo de comercio', 'parroquia' => 'Parroquia'] as $campo => $etiqueta)
                <div class="campo">
                    <label for="{{ $campo }}">{{ $etiqueta }}</label>
                    <input id="{{ $campo }}" name="{{ $campo }}" value="{{ old($campo, $establecimiento[$campo] ?? '') }}" required
                        maxlength="{{ $campo === 'tipo' ? 80 : 255 }}" @if ($campo === 'tipo') list="tipos" @endif>
                </div>
            @endforeach
            <datalist id="tipos">
                @foreach (['tienda', 'minimercado', 'supermercado', 'distribuidor', 'panaderia'] as $tipo)
                    <option value="{{ $tipo }}"></option>
                @endforeach
            </datalist>
            @foreach (['latitud' => 90, 'longitud' => 180] as $campo => $limite)
                <div class="campo">
                    <label for="{{ $campo }}">{{ ucfirst($campo) }}</label>
                    <input id="{{ $campo }}" name="{{ $campo }}" type="number" step="any" min="{{ -$limite }}" max="{{ $limite }}"
                        value="{{ old($campo, $establecimiento[$campo] ?? '') }}">
                </div>
            @endforeach
            <div class="campo">
                <label for="recepcion">Recepción de proveedores</label>
                <select id="recepcion" name="recepcion_proveedores">
                    @foreach (['por_confirmar' => 'Por confirmar', 'recibe_propuestas' => 'Recibe propuestas', 'no_recibe_por_ahora' => 'No recibe por ahora'] as $valor => $etiqueta)
                        <option value="{{ $valor }}" @selected(old('recepcion_proveedores', $establecimiento['recepcionProveedores'] ?? 'por_confirmar') === $valor)>{{ $etiqueta }}</option>
                    @endforeach
                </select>
            </div>

            <fieldset>
                <legend>Categorías</legend>
                @php($elegidas = array_map('intval', (array) old('categorias', array_column($establecimiento['categorias'] ?? [], 'id'))))
                @foreach ($categorias as $categoria)
                    <label><input type="checkbox" name="categorias[]" value="{{ $categoria->id }}" @checked(in_array($categoria->id, $elegidas, true))> {{ $categoria->nombre }}</label>
                @endforeach
            </fieldset>
            @foreach (['direccion' => 'Dirección', 'canton' => 'Cantón', 'sector' => 'Sector', 'horario_atencion' => 'Horario general', 'horario_proveedores' => 'Horario para proveedores', 'foto_url' => 'URL de la fotografía'] as $campo => $etiqueta)
                <div class="campo">
                    <label for="{{ $campo }}">{{ $etiqueta }}</label>
                    <input id="{{ $campo }}" name="perfil[{{ $campo }}]" type="{{ $campo === 'foto_url' ? 'url' : 'text' }}"
                        maxlength="{{ $campo === 'foto_url' ? 2048 : 255 }}" value="{{ old('perfil.'.$campo, $establecimiento[$campo] ?? '') }}">
                </div>
            @endforeach
            <div class="campo">
                <label for="productos">Productos buscados, separados por coma</label>
                <input id="productos" name="productos_texto" maxlength="4000" value="{{ old('productos_texto', implode(', ', $establecimiento['productos_buscados'] ?? [])) }}">
            </div>
            @foreach (['tiene_refrigeracion' => ['Refrigeración', ['1' => 'Sí', '0' => 'No']], 'modalidad_compra' => ['Modalidad de compra', ['contado' => 'Contado', 'credito' => 'Crédito', 'consignacion' => 'Consignación']], 'decision_compra' => ['Dónde se decide la compra', ['sucursal' => 'Sucursal', 'oficina_central' => 'Oficina central']]] as $campo => [$etiqueta, $opciones])
                @php($elegido = old('perfil.'.$campo, $establecimiento[$campo] ?? null))
                <div class="campo">
                    <label for="{{ $campo }}">{{ $etiqueta }}</label>
                    <select id="{{ $campo }}" name="perfil[{{ $campo }}]">
                        <option value="">Por confirmar</option>
                        @foreach ($opciones as $valor => $texto)
                            <option value="{{ $valor }}" @selected($elegido !== null && (string) (is_bool($elegido) ? (int) $elegido : $elegido) === (string) $valor)>{{ $texto }}</option>
                        @endforeach
                    </select>
                </div>
            @endforeach
            <div class="campo">
                <label for="pedido">Pedido inicial en unidades</label>
                <input id="pedido" name="perfil[pedido_inicial]" type="number" min="1" max="1000000000" value="{{ old('perfil.pedido_inicial', $establecimiento['pedido_inicial'] ?? '') }}">
            </div>
            @foreach (['requisitos' => 'Requisitos', 'devoluciones' => 'Condiciones de devolución'] as $campo => $etiqueta)
                <div class="campo">
                    <label for="{{ $campo }}">{{ $etiqueta }}</label>
                    <textarea id="{{ $campo }}" name="perfil[{{ $campo }}]" maxlength="4000">{{ old('perfil.'.$campo, $establecimiento[$campo] ?? '') }}</textarea>
                </div>
            @endforeach

            <fieldset>
                <legend>Contacto comercial</legend>
                @foreach (['contacto_nombre' => ['Responsable', 'text'], 'contacto_telefono' => ['Teléfono', 'tel'], 'contacto_email' => ['Correo', 'email']] as $campo => [$etiqueta, $tipo])
                    <div class="campo">
                        <label for="{{ $campo }}">{{ $etiqueta }}</label>
                        <input id="{{ $campo }}" name="perfil[{{ $campo }}]" type="{{ $tipo }}" value="{{ old('perfil.'.$campo, $edicion[$campo] ?? '') }}">
                    </div>
                @endforeach
                @foreach (['telefono' => 'Teléfono', 'whatsapp' => 'WhatsApp', 'email' => 'Correo'] as $canal => $etiqueta)
                    <label><input type="checkbox" name="perfil[canales_contacto][]" value="{{ $canal }}"
                        @checked(in_array($canal, (array) old('perfil.canales_contacto', $edicion['canales_contacto'] ?? []), true))> {{ $etiqueta }}</label>
                @endforeach
                <input type="hidden" name="perfil[autorizar_contacto]" value="0">
                <label><input type="checkbox" name="perfil[autorizar_contacto]" value="1" @checked((bool) old('perfil.autorizar_contacto', false))> Autorizo publicar estos datos y canales de contacto</label>
            </fieldset>
            <button type="submit" class="boton">Guardar establecimiento</button>
        </form>
    </section>
</x-layout>
