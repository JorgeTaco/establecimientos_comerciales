<x-layout titulo="Contactar a {{ $establecimiento['nombre'] ?? 'comercio' }}">
    <div style="margin-bottom:1.5rem;">
        <h1 style="margin:0 0 0.25rem; font-size:1.35rem;">Contactar a {{ $establecimiento['nombre'] ?? 'este comercio' }}</h1>
        <p style="color:var(--tinta-suave); font-size:0.9rem; margin:0;">
            {{ $establecimiento['tipoEtiqueta'] ?? '' }} · {{ $establecimiento['parroquia'] ?? '' }}
        </p>
    </div>

    @if ($error ?? null)
        <div class="aviso aviso--error">{{ $error }}</div>
    @endif
    @if ($errors->any())
        <div class="aviso aviso--error">{{ $errors->first() }}</div>
    @endif

    <div class="tarjeta">
        <form id="formOportunidad" novalidate>
            <input type="hidden" name="referencia" value="{{ \Illuminate\Support\Str::uuid() }}">
            <input type="hidden" name="establecimiento_id" value="{{ $establecimiento['id'] }}">

            <div class="campo">
                <label for="mensaje">Mensaje para el comercio</label>
                <textarea id="mensaje" name="mensaje" rows="4" required placeholder="Preséntate y cuéntale qué productos te interesaría ofrecerle...">{{ old('mensaje') }}</textarea>
                <p class="pista" style="margin-top:0.25rem;">Máximo 4000 caracteres.</p>
            </div>

            <div class="campo" style="margin-bottom:1.5rem;">
                <label style="font-weight:600; letter-spacing:0.04em; text-transform:uppercase; color:var(--tinta-suave); font-size:0.82rem; margin-bottom:0.5rem; display:block;">Selecciona tus productos</label>
                <div id="lista-productos" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(14rem, 1fr)); gap:0.75rem;">
                    <!-- Se llenará con JS desde la API -->
                </div>
                <p id="error-productos" class="error" style="margin-top:0.5rem; display:none;">Selecciona al menos un producto.</p>
            </div>

            <button type="submit" class="boton" id="btnEnviar" style="width:100%;">Enviar propuesta</button>
        </form>
    </div>

    <p class="pista" style="margin-top:1.5rem;">
        <a href="{{ route('oportunidades.index') }}">← Volver a mis oportunidades</a>
    </p>

    @push('styles')
    <style>
        #formOportunidad { display: flex; flex-direction: column; gap: 0; }
        #formOportunidad .campo:last-child { margin-bottom: 1.25rem; }
        .producto-check {
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
            padding: 0.6rem 0.8rem;
            background: var(--superficie-suave);
            border: 1px solid var(--borde);
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .producto-check input { margin-top: 0.1rem; flex: none; }
        .producto-check:hover { border-color: var(--verde); background: var(--verde-claro); }
        .producto-check input:checked + div {
            border-color: var(--verde);
            background: var(--verde-claro);
        }
        .producto-check__nombre { font-weight: 600; font-size: 0.9rem; }
        .producto-check__precio { font-size: 0.8rem; color: var(--tinta-suave); }
    </style>
    @endpush

    <script>
        (function () {
            var form = document.getElementById('formOportunidad');
            var btn = document.getElementById('btnEnviar');
            var lista = document.getElementById('lista-productos');
            var errorProductos = document.getElementById('error-productos');
            var csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            var productosSeleccionados = {};

            // Cargar productos del emprendedor
            fetch('/api/productos', {
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                credentials: 'same-origin'
            })
            .then(function (res) {
                if (!res.ok) throw new Error('No se pudieron cargar tus productos.');
                return res.json();
            })
            .then(function (data) {
                var productos = data.productos || [];
                lista.innerHTML = '';
                if (productos.length === 0) {
                    lista.innerHTML = '<p style="color:var(--tinta-suave);">No tienes productos creados. <a href="{{ route('productos.crear') }}" style="color:var(--verde); font-weight:600;">Crear producto primero</a>.</p>';
                    btn.disabled = true;
                    return;
                }
                productos.forEach(function (p) {
                    var id = p.id;
                    var nodo = document.createElement('label');
                    nodo.className = 'producto-check';
                    nodo.innerHTML = '<input type="checkbox" name="productos[]" value="' + id + '" style="margin-top:0.1rem;">' +
                        '<div style="flex:1;">' +
                        '<div class="producto-check__nombre">' + escapeHtml(p.nombre) + '</div>' +
                        '<div class="producto-check__precio">$' + (p.precio_sugerido !== null ? p.precio_sugerido : '—') + ' sugerido · pedido mín. ' + (p.pedido_minimo || '—') + '</div>' +
                        '</div>';
                    nodo.addEventListener('click', function (e) {
                        if (e.target.tagName === 'INPUT') return;
                        var input = nodo.querySelector('input');
                        input.checked = !input.checked;
                        actualizarSeleccion();
                    });
                    nodo.querySelector('input').addEventListener('change', actualizarSeleccion);
                    lista.appendChild(nodo);
                });
            })
            .catch(function (error) {
                lista.innerHTML = '<p style="color:var(--negativo-texto);">' + error.message + '</p>';
            });

            function actualizarSeleccion() {
                var inputs = lista.querySelectorAll('input[type="checkbox"]:checked');
                errorProductos.style.display = inputs.length === 0 ? 'block' : 'none';
            }

            function escapeHtml(texto) {
                var d = document.createElement('div');
                d.textContent = texto;
                return d.innerHTML;
            }

            form.addEventListener('submit', function (evento) {
                evento.preventDefault();

                var inputs = lista.querySelectorAll('input[type="checkbox"]:checked');
                if (inputs.length === 0) {
                    errorProductos.style.display = 'block';
                    return;
                }

                var datos = new FormData(form);
                var cuerpo = {};
                for (var par of datos) {
                    cuerpo[par[0]] = par[1];
                }
                // productos ya viene como array del FormData si hay múltiples
                // pero FormData con mismo nombre múltiples veces -> necesitamos manejarlo
                var productosArray = Array.from(inputs).map(function (input) { return parseInt(input.value); });
                cuerpo.productos = productosArray;

                btn.disabled = true;
                btn.textContent = 'Enviando…';

                fetch('/api/establecimientos/' + '{{ $establecimiento['id'] }}' + '/propuestas', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(cuerpo)
                })
                .then(function (respuesta) {
                    if (!respuesta.ok) {
                        return respuesta.json().then(function (json) {
                            var msg = '';
                            if (json.errors) {
                                var primera = Object.values(json.errors)[0];
                                msg = Array.isArray(primera) ? primera[0] : String(primera);
                            }
                            throw new Error(msg || 'No se pudo enviar la propuesta.');
                        });
                    }
                    return respuesta.json();
                })
                .then(function () {
                    window.location.href = '{{ route('oportunidades.index') }}';
                })
                .catch(function (error) {
                    btn.disabled = false;
                    btn.textContent = 'Enviar propuesta';
                    var aviso = document.createElement('div');
                    aviso.className = 'aviso aviso--error';
                    aviso.textContent = error.message;
                    var existente = form.parentElement.querySelector('.aviso--error');
                    if (existente) existente.remove();
                    form.parentElement.insertBefore(aviso, form);
                });
            });
        })();
    </script>
</x-layout>
