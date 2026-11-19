<x-layout titulo="Iniciar sesión">
    <div class="tarjeta">
        <h1 style="margin-top:0; font-size:1.35rem; margin-bottom:0.5rem;">Iniciar sesión</h1>
        <p style="color:var(--tinta-suave); font-size:0.9rem; margin-bottom:1.5rem;">
            Accede a tu cuenta para gestionar oportunidades, productos y propuestas.
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

        <form id="formLogin" novalidate>
            <div class="campo">
                <label for="email">Correo electrónico</label>
                <input type="email" id="email" name="email" placeholder="tucorreo@ejemplo.com" required autocomplete="email">
            </div>
            <div class="campo">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" placeholder="••••••••" required autocomplete="current-password">
            </div>
            <button type="submit" class="boton" id="btnLogin" style="width:100%;">Ingresar</button>
        </form>

        <div class="fila">
            <span>¿No tienes cuenta?</span>
            <a href="{{ route('registro') }}" class="boton boton--secundario">Crear cuenta</a>
        </div>
        <p class="pista">
            <a href="{{ route('establecimientos.index') }}">← Volver al directorio</a>
        </p>
    </div>

    @push('styles')
    <style>
        #formLogin { display: flex; flex-direction: column; gap: 0; }
        #formLogin .campo:last-child { margin-bottom: 1.25rem; }
    </style>
    @endpush

    <script>
        (function () {
            var form = document.getElementById('formLogin');
            var btn = document.getElementById('btnLogin');
            var csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            form.addEventListener('submit', function (evento) {
                evento.preventado = true;
                evento.preventDefault();

                var datos = new FormData(form);
                var cuerpo = {};
                for (var par of datos) {
                    cuerpo[par[0]] = par[1];
                }

                btn.disabled = true;
                btn.textContent = 'Ingresando…';

                fetch('/api/sesion', {
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
                            throw new Error(json.errors ? json.errors.email?.[0] : 'Credenciales no válidas.');
                        });
                    }
                    return respuesta.json();
                })
                .then(function () {
                    window.location.href = '{{ route('oportunidades.index') }}';
                })
                .catch(function (error) {
                    btn.disabled = false;
                    btn.textContent = 'Ingresar';
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