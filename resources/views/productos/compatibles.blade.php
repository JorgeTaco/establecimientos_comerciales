<x-layout titulo="Comercios compatibles · {{ $producto->nombre }}">
    <div style="display:flex; align-items:center; justify-content:space-between; gap:1rem; margin-bottom:1.5rem; flex-wrap:wrap;">
        <div>
            <h1 style="margin:0; font-size:1.35rem;">Comercios compatibles</h1>
            <p style="color:var(--tinta-suave); font-size:0.9rem; margin-top:0.25rem;">
                Comercios que pueden recibir tu producto <strong>{{ $producto->nombre }}</strong>.
            </p>
        </div>
    </div>

    <div class="tarjeta">
        <p style="color:var(--tinta-suave); font-size:0.9rem; margin-bottom:1rem;">
            Un comercio es compatible cuando coincide en categoría, zona de entrega, refrigeración (si aplica) y rango de pedido.
        </p>

        <div id="estado-carga">Cargando comercios compatibles…</p>
        <div id="estado-error" class="mensaje mensaje--error" role="alert" hidden></div>
        <div id="estado-vacio" class="mensaje" hidden>
            No hay comercios compatibles con este producto en este momento.
        </div>

        <div id="lista-compatibles" hidden>
            <ul class="listado" id="listado-compatibles"></ul>
        </div>
    </div>

    @push('styles')
    <style>
        #listado-compatibles { display: grid; gap: 0.75rem; padding: 0; list-style: none; }
        .compatible {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 0.85rem 1rem;
            background: var(--superficie);
            border: 1px solid var(--borde);
            border-radius: var(--radio-chico);
        }
        .compatible__info { flex: 1; min-width: 0; }
        .compatible__nombre { font-weight: 600; margin-bottom: 0.15rem; }
        .compatible__meta { font-size: 0.82rem; color: var(--tinta-suave); }
        .compatible__motivos {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            flex-shrink: 0;
            max-width: 22rem;
        }
        .motivo {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.25rem 0.6rem;
            font-size: 0.75rem;
            font-weight: 600;
            border-radius: 999px;
            white-space: nowrap;
        }
        .motivo--positivo { color: var(--positivo-texto); background: var(--positivo-fondo); }
        .motivo--pendiente { color: var(--pendiente-texto); background: var(--pendiente-fondo); }
        .compatible__accion {
            display: flex;
            gap: 0.5rem;
            flex-shrink: 0;
        }
    </style>
    @endpush

    <script>
        (function () {
            var csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            var lista = document.getElementById('listado-compatibles');
            var carga = document.getElementById('estado-carga');
            var error = document.getElementById('estado-error');
            var vacio = document.getElementById('estado-vacio');
            var visible = document.getElementById('lista-compatibles');

            function cargar() {
                fetch('/api/productos/{{ $producto->id }}/establecimientos-compatibles', {
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                    credentials: 'same-origin'
                })
                .then(function (res) {
                    if (!res.ok) throw new Error('No se pudieron cargar los comercios compatibles.');
                    return res.json();
                })
                .then(function (data) {
                    var comercios = data.establecimientos || [];
                    lista.innerHTML = '';
                    if (comercios.length === 0) {
                        vacio.hidden = false;
                        visible.hidden = true;
                        return;
                    }
                    vacio.hidden = true;
                    visible.hidden = false;
                    comercios.forEach(function (c) {
                        var li = document.createElement('li');
                        li.className = 'compatible';
                        var motivosHtml = (c.motivos || []).map(function (m) {
                            return '<span class="motivo motivo--positivo">✓ ' + escapeHtml(m) + '</span>';
                        }).join('');
                        var pendientesHtml = (c.pendientes || []).map(function (p) {
                            return '<span class="motivo motivo--pendiente">⚠ ' + escapeHtml(p) + '</span>';
                        }).join('');
                        li.innerHTML = '<div class="compatible__info">' +
                            '<div class="compatible__nombre">' + escapeHtml(c.nombre) + '</div>' +
                            '<div class="compatible__meta">' + escapeHtml(c.tipoEtiqueta || c.tipo) + ' · ' + escapeHtml(c.parroquia || '') + '</div>' +
                            '</div>' +
                            '<div class="compatible__motivos">' +
                            motivosHtml + pendientesHtml +
                            '</div>' +
                            '<div class="compatible__accion">' +
                            '<a href="/establecimientos/' + encodeURIComponent(c.id) + '?desde_producto={{ $producto->id }}" class="boton boton--secundario" style="padding:0.4rem 0.8rem; font-size:0.85rem;">Ver ficha</a>' +
                            '</div>';
                        lista.appendChild(li);
                    });
                })
                .catch(function (error) {
                    carga.hidden = true;
                    error.textContent = error.message;
                    error.hidden = false;
                });
            }

            function escapeHtml(texto) {
                var d = document.createElement('div');
                d.textContent = texto;
                return d.innerHTML;
            }

            cargar();
        })();
    </script>
</x-layout>