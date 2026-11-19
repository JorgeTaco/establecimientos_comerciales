<x-layout titulo="Crear cuenta">
    <div class="tarjeta">
        <h1 style="margin-top:0; font-size:1.35rem; margin-bottom:0.5rem;">Crear cuenta</h1>
        <p style="color:var(--tinta-suave); font-size:0.9rem; margin-bottom:1.5rem;">
            Regístrate como emprendedor o como comercio para participar en el piloto.
        </p>

        @if ($error ?? null)
            <div class="aviso aviso--error">{{ $error }}</div>
        @endif
        @if (session('error'))
            <div class="aviso aviso--error">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="aviso aviso--error">{{ $errors->first() }}</div>
        @endif

        <form id="formRegistro" novalidate>
            <div class="campo">
                <label for="name">Nombre completo</label>
                <input type="text" id="name" name="name" placeholder="Michelle Cheme" required autocomplete="name">
            </div>
            <div class="campo">
                <label for="email">Correo electrónico</label>
                <input type="email" id="email" name="email" placeholder="tucorreo@ejemplo.com" required autocomplete="email">
            </div>
            <div class="campo">
                <label for="password">Contraseña (mínimo 12 caracteres)</label>
                <input type="password" id="password" name="password" placeholder="••••••••••••" required autocomplete="new-password">
            </div>
            <div class="campo">
                <label for="password_confirmation">Confirmar contraseña</label>
                <input type="password" id="password_confirmation" name="password_confirmation" placeholder="••••••••••••" required autocomplete="new-password">
            </div>
            <div class="campo">
                <label style="font-weight:600; margin-bottom:0.4rem;">¿Quién eres?</label>
                <label style="display:inline-flex; align-items:center; gap:0.4rem; cursor:pointer; margin-right:1rem;">
                    <input type="radio" name="rol" value="emprendedor" checked>
                    Emprendedor (oferta productos)
                </label>
                <label style="display:inline-flex; align-items:center; gap:0.4rem; cursor:pointer;">
                    <input type="radio" name="rol" value="comercio">
                    Comercio (recibe propuestas)
                </label>
            </div>
            <button type="submit" class="boton" id="btnRegistro" style="width:100%;">Crear cuenta</button>
        </form>

        <div class="fila">
            <span>¿Ya tienes cuenta?</span>
            <a href="{{ route('login') }}" class="boton boton--secundario">Iniciar sesión</a>
        </div>
        <p class="pista">
            <a href="{{ route('establecimientos.index') }}">← Volver al directorio</a>
        </p>
    </div>

    @push('styles')
    <style>
        #formRegistro { display: flex; flex-direction: column; gap: 0; }
        #formRegistro .campo:last-child { margin-bottom: 1.25rem; }
    </style>
    @endpush

    <script>
        (function () {
            var form = document.getElementById('formRegistro');
            var btn = document.getElementById('btnRegistro');
            var csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            form.addEventListener('submit', function (evento) {
                evento.preventDefault();

                var datos = new FormData(form);
                var cuerpo = {};
                for (var par of datos) {
                    cuerpo[par[0]] = par[1];
                }
                cuerpo.password_confirmation = cuerpo.password;

                btn.disabled = true;
                btn.textContent = 'Creando cuenta…';

                fetch('/api/registro', {
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
                            throw new Error(msg || 'No se pudo crear la cuenta.');
                        });
                    }
                    return respuesta.json();
                })
                .then(function () {
                    window.location.href = '{{ route('oportunidades.index') }}';
                })
                .catch(function (error) {
                    btn.disabled = false;
                    btn.textContent = 'Crear cuenta';
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