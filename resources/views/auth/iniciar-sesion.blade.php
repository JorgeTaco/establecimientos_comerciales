<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/establecimientos.css') }}">
</head>
<body>
    <main class="contenido">
        <h1>Iniciar sesión</h1>
        <form method="post" action="{{ route('login.store') }}">
            @csrf
            <p class="campo">
                <label for="email">Correo electrónico</label>
                <input id="email" name="email" type="email" autocomplete="username" maxlength="255"
                       value="{{ old('email') }}" required
                       @error('email') aria-invalid="true" aria-describedby="error-email" @enderror>
                @error('email')
                    <span id="error-email" role="alert">{{ $message }}</span>
                @enderror
            </p>
            <p class="campo">
                <label for="password">Contraseña</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required
                       @error('password') aria-invalid="true" aria-describedby="error-password" @enderror>
                @error('password')
                    <span id="error-password" role="alert">{{ $message }}</span>
                @enderror
            </p>
            <button class="boton-secundario" type="submit">Entrar</button>
        </form>
        <p><a href="{{ route('establecimientos.index') }}">Ver establecimientos</a></p>
    </main>
</body>
</html>
