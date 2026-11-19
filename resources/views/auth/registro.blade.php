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

        <form id="formRegistro" method="post" action="{{ route('registro.store') }}">
            @csrf
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
                <input type="password" id="password" name="password" minlength="12" placeholder="••••••••••••" required autocomplete="new-password">
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
    </div>

    @push('styles')
    <style>
        #formRegistro { display: flex; flex-direction: column; gap: 0; }
        #formRegistro .campo:last-child { margin-bottom: 1.25rem; }
    </style>
    @endpush

</x-layout>
