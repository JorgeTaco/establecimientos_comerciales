<x-layout titulo="Responder propuesta #{{ $propuestaId }}">
    <div style="margin-bottom:1.5rem;">
        <h1 style="margin:0; font-size:1.35rem;">Responder propuesta</h1>
        <p style="color:var(--tinta-suave); font-size:0.9rem; margin-top:0.25rem;">
            Responde a la propuesta del emprendedor.
        </p>
    </div>

    @if ($error ?? null)
        <div class="aviso aviso--error">{{ $error }}</div>
    @endif
    @if ($errors->any())
        <div class="aviso aviso--error">{{ $errors->first() }}</div>
    @endif

    <div class="tarjeta">
        <!-- Datos de la propuesta (cargados desde API) -->
        <div id="propuesta-info" hidden style="margin-bottom:1.5rem;">
            <div style="display:flex; align-items:center; gap:1rem; flex-wrap:wrap;">
                <div class="ficha__avatar" style="width:2.6rem; height:2.6rem; font-size:0.92rem; font-weight:700; color:var(--verde-oscuro); background:var(--verde-claro); border-radius:14px; display:grid; place-items:center;" id="propuesta-iniciales">?</div>
                <div>
                    <h2 style="margin:0; font-size:1.15rem; line-height:1.2;" id="propuesta-nombre">Cargando…</h2>
                    <p style="margin:0.2rem 0 0; font-size:0.82rem; color:var(--tinta-suave);" id="propuesta-tipo">—</p>
                </div>
                <a href="#" id="propuesta-link" class="boton boton--secundario" style="margin-left:auto;">Ver ficha del comercio</a>
            </div>
        </div>

        <form id="formRespuesta" novalidate>
            <div class="campo">
                <label for="respuesta">Tu respuesta</label>
                <select id="respuesta" name="respuesta" required>
                    <option value="">Selecciona una respuesta</option>
                    <option value="me_interesa">Me interesa</option>
                    <option value="solicito_informacion">Solicito información</option>
                    <option value="agendar_visita">Agendar visita</option>
                    <option value="no_interesado_por_ahora">No interesado por ahora</option>
                </select>
            </div>
            <div class="campo">
                <label for="mensaje_respuesta">Mensaje (opcional)</label>
                <textarea id="mensaje_respuesta" name="mensaje_respuesta" rows="3" placeholder="Agrega un comentario para el emprendedor...">{{ old('mensaje_respuesta') }}</textarea>
                <p class="pista" style="margin-top:0.25rem;">Máximo 4000 caracteres.</p>
            </div>
            <button type="submit" class="boton" id="btnResponder" style="width:100%;">Enviar respuesta</button>
        </form>
    </div>

    <p class="pista" style="margin-top:1.5rem;">
        <a href="{{ route('propuestas.recibidas') }}">← Volver a propuestas recibidas</a>
    </p>

    @push('styles')
    <style>
        #formRespuesta { display: flex; flex-direction: column; gap: 0; }
        #formRespuesta .campo:last-child { margin-bottom: 1.25rem; }
        textarea { width: 100%; padding: 0.6rem 0.8rem; font-size: 0.95rem; color: var(--tinta); background: var(--superficie-suave); border: 1px solid var(--borde); border-radius: 8px; font-family: inherit; }
        textarea:focus { outline: 2px solid var(--verde); outline-offset: 2px; border-color: var(--verde); }
    </style>
    @endpush

    <script>
        (function () {
            var csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            var propuestaInfo = document.getElementById('propuesta-info');
            var propuestaNombre = document.getElementById('propuesta-nombre');
            var propuestaTipo = document.getElementById('propuesta-tipo');
            var propuestaIniciales = document.getElementById('propuesta-iniciales');
            var propuestaLink = document.getElementById('propuesta-link');

            var propuestaId = {{ $propuestaId ?? 'null' }};

            if (propuestaId) {
                fetch('/api/propuestas-recibidas', {
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                    credentials: 'same-origin'
                })
                .then(function (res) {
                    if (!res.ok) throw new Error('No se pudo cargar la propuesta.');
                    return res.json();
                })
                .then(function (data) {
                    var propuestas = data.propuestas || [];
                    var p = propuestas.find(function (x) { return (x.id ?? x.propuesta_id) == propuestaId; }) || {};
                    var estId = p.establecimiento_id;

                    if (estId) {
                        return fetch('/api/establecimientos/' + estId, {
                            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                            credentials: 'same-origin'
                        }).then(function (res2) {
                            if (!res2.ok) return {};
                            return res2.json();
                        }).then(function (data2) {
                            var e = data2.establecimiento || {};
                            propuestaNombre.textContent = e.nombre || 'Comercio';
                            propuestaTipo.textContent = (e.tipoEtiqueta || e.tipo || '') + ' · ' + (e.parroquia || '');
                            propuestaIniciales.textContent = (e.nombre ? e.nombre.split(' ').map(function (part) { return part.charAt(0); }).join('').slice(0, 2) : '?').toUpperCase();
                            propuestaLink.href = '/establecimientos/' + e.id;
                            propuestaInfo.hidden = false;
                        });
                    } else {
                        propuestaInfo.hidden = false;
                    }
                })
                .catch(function () {
                    propuestaInfo.hidden = false;
                });
            }

            var form = document.getElementById('formRespuesta');
            var btn = document.getElementById('btnResponder');

            form.addEventListener('submit', function (evento) {
                evento.preventDefault();

                var datos = new FormData(form);
                var cuerpo = {};
                for (var par of datos) {
                    cuerpo[par[0]] = par[1];
                }

                btn.disabled = true;
                btn.textContent = 'Enviando…';

                fetch('/api/propuestas-recibidas/' + propuestaId + '/respuesta', {
                    method: 'PATCH',
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
                            throw new Error(msg || 'No se pudo enviar la respuesta.');
                        });
                    }
                    return respuesta.json();
                })
                .then(function () {
                    window.location.href = '{{ route('propuestas.recibidas') }}';
                })
                .catch(function (error) {
                    btn.disabled = false;
                    btn.textContent = 'Enviar respuesta';
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