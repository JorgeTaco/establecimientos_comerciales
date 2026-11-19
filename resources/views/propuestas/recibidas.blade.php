<x-layout titulo="Propuestas recibidas">
    <div style="display:flex; align-items:center; justify-content:space-between; gap:1rem; margin-bottom:1.5rem; flex-wrap:wrap;">
        <div>
            <h1 style="margin:0; font-size:1.35rem;">Propuestas recibidas</h1>
            <p style="color:var(--tinta-suave); font-size:0.9rem; margin-top:0.25rem;">
                Los emprendedores te han enviado propuestas de productos para tu comercio.
            </p>
        </div>
        <a href="{{ route('establecimientos.index') }}" class="boton boton--secundario">Ver directorio</a>
    </div>

    <div id="estado-vacio" class="tarjeta" style="text-align:center; padding:3rem 1rem;">
        <svg viewBox="0 0 64 64" style="width:3rem; height:3rem; color:var(--tinta-suave); margin-bottom:1rem;" aria-hidden="true">
            <circle cx="32" cy="32" r="26" fill="none" stroke="var(--borde)" stroke-width="2"/>
            <path d="M20 22c0-4.4 3.6-8 8-8s8 3.6 8 8-3.6 8-8 8-8-3.6-8-8Z M32 18l-4 4 M32 18l4 4" fill="none" stroke="var(--borde)" stroke-width="2" stroke-linecap="round"/>
        </svg>
        <h2 style="margin:0 0 0.5rem; font-size:1.1rem;">Todavía no tienes propuestas</h2>
        <p style="color:var(--tinta-suave); font-size:0.9rem; margin-bottom:1.5rem;">
            Cuando un emprendedor te envíe una propuesta, aparecerá aquí para que respondas.
        </p>
        <a href="{{ route('establecimientos.index') }}" class="boton">Ir al directorio</a>
    </div>

    <div id="lista-propuestas" hidden>
        <ul class="listado" id="listado-propuestas"></ul>
    </div>

    @push('styles')
    <style>
        #listado-propuestas { display: grid; gap: 0.75rem; padding: 0; list-style: none; }
        .propuesta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 0.85rem 1rem;
            background: var(--superficie);
            border: 1px solid var(--borde);
            border-radius: var(--radio-chico);
        }
        .propuesta__info { flex: 1; min-width: 0; }
        .propuesta__nombre { font-weight: 600; margin-bottom: 0.15rem; }
        .propuesta__meta { font-size: 0.82rem; color: var(--tinta-suave); }
        .propuesta__respuesta {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.25rem 0.6rem;
            font-size: 0.75rem;
            font-weight: 600;
            border-radius: 999px;
            white-space: nowrap;
        }
        .respuesta--pendiente { color: var(--neutro-texto); background: var(--neutro-fondo); }
        .respuesta--me_interesa { color: var(--positivo-texto); background: var(--positivo-fondo); }
        .respuesta--solicito_informacion { color: var(--pendiente-texto); background: var(--pendiente-fondo); }
        .respuesta--agendar_visita { color: var(--pendiente-texto); background: var(--pendiente-fondo); }
        .respuesta--no_interesado_por_ahora { color: var(--negativo-texto); background: var(--negativo-fondo); }
        .propuesta__acciones { display: flex; gap: 0.5rem; flex-shrink: 0; }
    </style>
    @endpush

    <script>
        (function () {
            var csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            var lista = document.getElementById('listado-propuestas');
            var vacio = document.getElementById('estado-vacio');
            var visible = document.getElementById('lista-propuestas');

            function respuestaEtiqueta(r) {
                var map = {
                    me_interesa: 'Me interesa',
                    solicito_informacion: 'Solicitó información',
                    agendar_visita: 'Agendar visita',
                    no_interesado_por_ahora: 'No interesado por ahora'
                };
                return map[r] || 'Sin respuesta';
            }

            function cargar() {
                fetch('/api/propuestas-recibidas', {
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                    credentials: 'same-origin'
                })
                .then(function (res) {
                    if (!res.ok) throw new Error('No se pudieron cargar las propuestas.');
                    return res.json();
                })
                .then(function (data) {
                    var propuestas = data.propuestas || [];
                    lista.innerHTML = '';
                    if (propuestas.length === 0) {
                        vacio.hidden = false;
                        visible.hidden = true;
                        return;
                    }
                    vacio.hidden = true;
                    visible.hidden = false;
                    propuestas.forEach(function (p) {
                        var respondida = p.respondida_en !== null;
                        var li = document.createElement('li');
                        li.className = 'propuesta';
                        li.innerHTML = '<div class="propuesta__info">' +
                            '<div class="propuesta__nombre">Propuesta #' + p.id + '</div>' +
                            '<div class="propuesta__meta">' + escapeHtml(p.mensaje) + ' · ' + (p.productos ? p.productos.length : 0) + ' producto(s)</div>' +
                            '</div>' +
                            '<div style="display:flex; align-items:center; gap:0.5rem;">' +
                            '<span class="propuesta__respuesta respuesta--' + (respondida ? (p.respuesta || 'pendiente') : 'pendiente') + '">' +
                            (respondida ? respuestaEtiqueta(p.respuesta) : 'Pendiente') +
                            '</span>' +
                            (respondida ? '' : '<a href="{{ route('propuestas.responder', ['propuesta' => p.id]) }}" class="boton boton--secundario" style="padding:0.4rem 0.8rem; font-size:0.85rem;">Responder</a>') +
                            '</div>' +
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