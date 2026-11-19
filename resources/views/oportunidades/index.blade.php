<x-layout titulo="Mis oportunidades">
    <div style="display:flex; align-items:center; justify-content:space-between; gap:1rem; margin-bottom:1.5rem; flex-wrap:wrap;">
        <div>
            <h1 style="margin:0; font-size:1.35rem;">Mis oportunidades</h1>
            <p style="color:var(--tinta-suave); font-size:0.9rem; margin-top:0.25rem;">
                Haz seguimiento a los comercios con los que estás negociando.
            </p>
        </div>
        <a href="{{ route('establecimientos.index') }}" class="boton boton--secundario">Ver directorio</a>
    </div>

    <div id="estado-vacio" class="tarjeta" style="text-align:center; padding:3rem 1rem;">
        <svg viewBox="0 0 64 64" style="width:3rem; height:3rem; color:var(--tinta-suave); margin-bottom:1rem;" aria-hidden="true">
            <circle cx="32" cy="32" r="26" fill="none" stroke="var(--borde)" stroke-width="2"/>
            <path d="M32 14v8 M32 42v8 M14 32h8 M42 32h8" stroke="var(--borde)" stroke-width="2" stroke-linecap="round"/>
        </svg>
        <h2 style="margin:0 0 0.5rem; font-size:1.1rem;">Todavía no tienes oportunidades</h2>
        <p style="color:var(--tinta-suave); font-size:0.9rem; margin-bottom:1.5rem;">
            Explora el directorio, elige un comercio y crea una oportunidad para iniciar el contacto.
        </p>
        <a href="{{ route('establecimientos.index') }}" class="boton">Ir al directorio</a>
    </div>

    <div id="lista-oportunidades" hidden>
        <ul class="listado" id="listado-oportunidades"></ul>
    </div>

    @push('styles')
    <style>
        #listado-oportunidades { display: grid; gap: 0.75rem; padding: 0; list-style: none; }
        .oportunidad {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 0.85rem 1rem;
            background: var(--superficie);
            border: 1px solid var(--borde);
            border-radius: var(--radio-chico);
        }
        .oportunidad__info { flex: 1; min-width: 0; }
        .oportunidad__nombre { font-weight: 600; margin-bottom: 0.15rem; }
        .oportunidad__meta { font-size: 0.82rem; color: var(--tinta-suave); }
        .oportunidad__estado {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.25rem 0.6rem;
            font-size: 0.75rem;
            font-weight: 600;
            border-radius: 999px;
            white-space: nowrap;
        }
        .estado--por_contactar { color: var(--neutro-texto); background: var(--neutro-fondo); }
        .estado--contactado { color: var(--pendiente-texto); background: var(--pendiente-fondo); }
        .estado--en_negociacion { color: var(--pendiente-texto); background: var(--pendiente-fondo); }
        .estado--primer_pedido { color: var(--positivo-texto); background: var(--positivo-fondo); }
        .estado--punto_venta_activo { color: var(--sobre-verde); background: var(--verde); }
        .estado--no_concretada { color: var(--negativo-texto); background: var(--negativo-fondo); }
        .oportunidad__acciones { display: flex; gap: 0.5rem; flex-shrink: 0; }
    </style>
    @endpush

    <script>
        (function () {
            var csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            var lista = document.getElementById('listado-oportunidades');
            var vacio = document.getElementById('estado-vacio');
            var visible = document.getElementById('lista-oportunidades');

            function estadoEtiqueta(estado) {
                var map = {
                    por_contactar: 'Por contactar',
                    contactado: 'Contactado',
                    muestra_entregada: 'Muestra entregada',
                    en_negociacion: 'En negociación',
                    primer_pedido: 'Primer pedido',
                    punto_venta_activo: 'Punto de venta activo',
                    no_concretada: 'No concretada'
                };
                return map[estado] || estado;
            }

            function cargar() {
                fetch('/api/oportunidades', {
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                    credentials: 'same-origin'
                })
                .then(function (res) {
                    if (!res.ok) throw new Error('No se pudieron cargar las oportunidades.');
                    return res.json();
                })
                .then(function (data) {
                    var oportunidades = data.oportunidades || [];
                    lista.innerHTML = '';
                    if (oportunidades.length === 0) {
                        vacio.hidden = false;
                        visible.hidden = true;
                        return;
                    }
                    vacio.hidden = true;
                    visible.hidden = false;
                    oportunidades.forEach(function (o) {
                        var li = document.createElement('li');
                        li.className = 'oportunidad';
                        li.innerHTML = '<div class="oportunidad__info">' +
                            '<div class="oportunidad__nombre">' + escapeHtml(o.establecimiento?.nombre || 'Comercio') + '</div>' +
                            '<div class="oportunidad__meta">Estado: <strong>' + estadoEtiqueta(o.estado) + '</strong> · Próxima gestión: ' + (o.proxima_gestion || '—') + '</div>' +
                            '</div>' +
                            '<div class="oportunidad__acciones">' +
                            '<a href="/oportunidades/' + encodeURIComponent(o.id) + '" class="boton boton--secundario" style="padding:0.4rem 0.8rem; font-size:0.85rem;">Ver seguimiento</a>' +
                            '</div>';
                        lista.appendChild(li);
                    });
                })
                .catch(function (error) {
                    var aviso = document.createElement('div');
                    aviso.className = 'aviso aviso--error';
                    aviso.textContent = error.message;
                    vacio.parentNode.insertBefore(aviso, vacio);
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