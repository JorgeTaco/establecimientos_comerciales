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

        <form id="formLogin" method="post" action="{{ route('login.store') }}">
            @csrf
            @if (old('evaluacion', request()->boolean('evaluacion')))
                <input type="hidden" name="evaluacion" value="1">
            @endif
            <div class="campo">
                <label for="email">Correo electrónico</label>
                <input type="email" id="email" name="email" placeholder="tucorreo@ejemplo.com" value="{{ old('email') }}" required autocomplete="username">
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
    </div>

    @push('styles')
    <style>
        #formLogin { display: flex; flex-direction: column; gap: 0; }
        #formLogin .campo:last-child { margin-bottom: 1.25rem; }
    </style>
    @endpush

</x-layout>
