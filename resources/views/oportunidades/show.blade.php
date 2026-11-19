<x-layout titulo="Oportunidad #{{ $oportunidadId }}">
    <div style="display:flex; align-items:center; justify-content:space-between; gap:1rem; margin-bottom:1.5rem; flex-wrap:wrap;">
        <div>
            <h1 style="margin:0; font-size:1.35rem;">Oportunidad #{{ $oportunidadId }}</h1>
            <p style="color:var(--tinta-suave); font-size:0.9rem; margin-top:0.25rem;">
                Seguimiento de tu contacto con este comercio.
            </p>
        </div>
        <a href="{{ route('oportunidades.index') }}" class="boton boton--secundario">← Volver a mis oportunidades</a>
    </div>

    @if (session('status'))
        <div class="aviso aviso--exito">{{ session('status') }}</div>
    @endif
    @if ($error ?? null)
        <div class="aviso aviso--error">{{ $error }}</div>
    @endif

    <!-- Datos del comercio (se cargan desde la API) -->
    <div id="comercio-info" hidden>
        <div class="tarjeta" style="margin-bottom:1.5rem;">
            <h2 style="font-size:0.85rem; text-transform:uppercase; letter-spacing:0.08em; color:var(--tinta-suave); margin-bottom:0.5rem;">Comercio</h2>
            <div style="display:flex; align-items:center; gap:1rem; flex-wrap:wrap;">
                <div class="ficha__avatar" style="width:2.6rem; height:2.6rem; font-size:0.92rem; font-weight:700; color:var(--verde-oscuro); background:var(--verde-claro); border-radius:14px; display:grid; place-items:center;" id="comercio-iniciales">?</div>
                <div>
                    <h2 style="margin:0; font-size:1.15rem; line-height:1.2;" id="comercio-nombre">Cargando…</h2>
                    <p style="margin:0.2rem 0 0; font-size:0.82rem; color:var(--tinta-suave);" id="comercio-tipo">—</p>
                </div>
                <a href="#" id="comercio-link" class="boton boton--secundario" style="margin-left:auto;">Ver ficha del comercio</a>
            </div>
        </div>
    </div>

    <!-- Estado de la oportunidad -->
    <div class="tarjeta" style="margin-bottom:1.5rem;">
        <h2 style="font-size:0.85rem; text-transform:uppercase; letter-spacing:0.08em; color:var(--tinta-suave); margin-bottom:0.5rem;">Estado de la oportunidad</h2>
        <div class="ficha__estado ficha__estado--{{ $estadoTono ?? 'neutro' }}" style="padding:0.7rem 0.8rem; border-radius:var(--radio-chico); font-size:0.85rem; line-height:1.35; display:inline-flex; flex-direction:column; gap:0.4rem;">
            <span class="estado estado--{{ $estadoTono ?? 'neutro' }}" style="background:rgba(255,255,255,0.55);">
                {{ $estadoEtiqueta ?? 'Por contactar' }}
            </span>
            @if ($notas)
                <p style="margin:0;">{{ $notas }}</p>
            @endif
        </div>
    </div>

    <!-- Propuestas -->
    <div id="propuestas-container" hidden>
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:1rem; flex-wrap:wrap;">
            <h2 style="font-size:0.85rem; text-transform:uppercase; letter-spacing:0.08em; color:var(--tinta-suave); margin:0;">Propuestas enviadas</h2>
            <p style="color:var(--tinta-suave); font-size:0.85rem; margin:0;">
                {{ $propuestasCount }} propuesta(s)
            </p>
        </div>

        <div id="lista-propuestas" style="display:grid; gap:0.75rem; padding:0; list-style:none;">
            @foreach ($propuestas as $propuesta)
                <div style="display:flex; align-items:center; justify-content:space-between; gap:1rem; padding:0.85rem 1rem; background:var(--superficie); border:1px solid var(--borde); border-radius:var(--radio-chico);">
                    <div style="flex:1; min-width:0;">
                        <div class="oportunidad__nombre" style="font-weight:600; margin-bottom:0.15rem;">Propuesta #{{ $propuesta->id ?? $loop->index }}</div>
                        <div class="oportunidad__meta" style="font-size:0.82rem; color:var(--tinta-suave);">
                            {{ $propuesta->mensaje ?? '' }}
                            @if ($propuesta->productos && count($propuesta->productos) > 0)
                                · {{ count($propuesta->productos) }} producto(s)
                            @endif
                        </div>
                        @if ($propuesta->respondida_en)
                            <div style="margin-top:0.5rem; font-size:0.8rem; color:var(--verde);">
                                ✓ Respondida: {{ ucfirst($propuesta->respuesta ?? '') }}
                            </div>
                        @endif
                    </div>
                    <div style="display:flex; gap:0.5rem; flex-shrink:0;">
                        @if (!$propuesta->respondida_en)
                            <a href="{{ route('propuestas.responder', ['propuesta' => $propuesta->id ?? $loop->index]) }}" class="boton boton--secundario" style="padding:0.4rem 0.8rem; font-size:0.85rem;">Responder</a>
                        @else
                            <span class="chip chip--dato" style="padding:0.25rem 0.55rem; font-size:0.76rem;">Respondida</span>
                        @endif
                    </div>
                </div>
            @empty
                <p style="color:var(--tinta-suave); font-size:0.9rem; text-align:center; padding:1rem;">Todavía no has enviado propuestas para esta oportunidad.</p>
            @endforeach
        </div>
    </div>

    <p class="pista" style="margin-top:2rem;">
        <a href="{{ route('oportunidades.index') }}">← Volver a mis oportunidades</a>
    </p>

    @push('styles')
    <style>
        #comercio-info .ficha__avatar {
            width: 2.6rem;
            height: 2.6rem;
        }
    </style>
    @endpush

    <script>
        (function () {
            var csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            var comercioInfo = document.getElementById('comercio-info');
            var comercioNombre = document.getElementById('comercio-nombre');
            var comercioTipo = document.getElementById('comercio-tipo');
            var comercioIniciales = document.getElementById('comercio-iniciales');
            var comercioLink = document.getElementById('comercio-link');
            var propuestasContainer = document.getElementById('propuestas-container');

            // Datos de la oportunidad ya vienen del servidor ({{ $oportunidad }})
            var establecimientoId = {{ $establecimientoId ?? 'null' }};

            if (establecimientoId) {
                fetch('/api/establecimientos/' + establecimientoId, {
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                    credentials: 'same-origin'
                })
                .then(function (res) {
                    if (!res.ok) throw new Error('No se pudo cargar el comercio.');
                    return res.json();
                })
                .then(function (data) {
                    var e = data.establecimiento || {};
                    comercioNombre.textContent = e.nombre || 'Comercio';
                    comercioTipo.textContent = (e.tipoEtiqueta || e.tipo || '') + ' · ' + (e.parroquia || '');
                    comercioIniciales.textContent = (e.nombre ? e.nombre.split(' ').map(function (p) { return p.charAt(0); }).join('').slice(0, 2) : '?').toUpperCase();
                    comercioLink.href = '/establecimientos/' + e.id;
                    comercioInfo.hidden = false;
                })
                .catch(function () {
                    comercioInfo.hidden = false;
                    comercioNombre.textContent = 'Comercio';
                });
            } else {
                comercioInfo.hidden = false;
            }

            // Las propuestas ya vienen renderizadas del servidor.
            // Si quieres recargarlas dinámicamente, descomenta lo siguiente:
            /*
            propuestasContainer.hidden = false;
            fetch('/api/oportunidades/' + {{ $oportunidadId }} + '/propuestas', { ... })
            */
        })();
    </script>
</x-layout>