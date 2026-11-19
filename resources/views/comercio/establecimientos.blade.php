<x-layout titulo="Mis establecimientos">
    <div style="display:flex; align-items:center; justify-content:space-between; gap:1rem; margin-bottom:1.5rem; flex-wrap:wrap;">
        <div>
            <h1 style="margin:0; font-size:1.35rem;">Mis establecimientos</h1>
            <p style="color:var(--tinta-suave); font-size:0.9rem; margin-top:0.25rem;">
                Gestiona las fichas de tus comercios registrados.
            </p>
        </div>
        <a href="{{ route('establecimientos.index') }}" class="boton boton--secundario">Ver directorio</a>
    </div>

    <div id="estado-vacio" class="tarjeta" style="text-align:center; padding:3rem 1rem;">
        <svg viewBox="0 0 64 64" style="width:3rem; height:3rem; color:var(--tinta-suave); margin-bottom:1rem;" aria-hidden="true">
            <rect x="6" y="12" width="52" height="40" rx="4" fill="none" stroke="var(--borde)" stroke-width="2"/>
            <circle cx="20" cy="32" r="3" fill="var(--borde)"/>
            <circle cx="32" cy="32" r="3" fill="var(--borde)"/>
            <circle cx="44" cy="32" r="3" fill="var(--borde)"/>
            <path d="M20 44h24" fill="none" stroke="var(--borde)" stroke-width="2" stroke-linecap="round"/>
        </svg>
        <h2 style="margin:0 0 0.5rem; font-size:1.1rem;">No tienes establecimientos registrados</h2>
        <p style="color:var(--tinta-suave); font-size:0.9rem; margin-bottom:1.5rem;">
            Desde el directorio puedes explorar comercios. Para crear o editar una ficha como comercio,
            usa la opción correspondiente en la ficha del establecimiento.
        </p>
        <a href="{{ route('establecimientos.index') }}" class="boton">Ir al directorio</a>
    </div>

    <div id="lista-establecimientos" hidden>
        <ul class="listado" id="listado-establecimientos"></ul>
    </div>

    @push('styles')
    <style>
        #listado-establecimientos { display: grid; gap: 0.75rem; padding: 0; list-style: none; }
        .establecimiento {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 0.85rem 1rem;
            background: var(--superficie);
            border: 1px solid var(--borde);
            border-radius: var(--radio-chico);
        }
        .establecimiento__info { flex: 1; min-width: 0; }
        .establecimiento__nombre { font-weight: 600; margin-bottom: 0.15rem; }
        .establecimiento__meta { font-size: 0.82rem; color: var(--tinta-suave); }
        .establecimiento__acciones { display: flex; gap: 0.5rem; flex-shrink: 0; }
    </style>
    @endpush

    <script>
        (function () {
            var csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            var lista = document.getElementById('listado-establecimientos');
            var vacio = document.getElementById('estado-vacio');
            var visible = document.getElementById('lista-establecimientos');

            function cargar() {
                fetch('/api/mis-establecimientos', {
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                    credentials: 'same-origin'
                })
                .then(function (res) {
                    if (!res.ok) throw new Error('No se pudieron cargar tus establecimientos.');
                    return res.json();
                })
                .then(function (data) {
                    var establecimientos = data.establecimientos || [];
                    lista.innerHTML = '';
                    if (establecimientos.length === 0) {
                        vacio.hidden = false;
                        visible.hidden = true;
                        return;
                    }
                    vacio.hidden = true;
                    visible.hidden = false;
                    establecimientos.forEach(function (e) {
                        var li = document.createElement('li');
                        li.className = 'establecimiento';
                        li.innerHTML = '<div class="establecimiento__info">' +
                            '<div class="establecimiento__nombre">' + escapeHtml(e.nombre) + '</div>' +
                            '<div class="establecimiento__meta">' + escapeHtml(e.tipo) + ' · ' + escapeHtml(e.parroquia) + '</div>' +
                            '</div>' +
                            '<div class="establecimiento__acciones">' +
                            '<a href="/establecimientos/' + e.id + '" class="boton boton--secundario" style="padding:0.4rem 0.8rem; font-size:0.85rem;">Ver ficha</a>' +
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