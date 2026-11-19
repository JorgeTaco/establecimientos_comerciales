<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titulo ?? config('app.name') }} · {{ config('app.name') }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Merriweather:ital,wght@0,700;1,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/navegacion.css') }}">
    <style>
        :root {
            --fondo: #fbf7ef;
            --fondo-calido: #f6efe0;
            --superficie: #ffffff;
            --superficie-suave: #f7f2e6;
            --tinta: #12271f;
            --tinta-suave: #5c6b62;
            --borde: #e7e0cf;
            --borde-fuerte: #d6ccb4;
            --verde: #1f6f4a;
            --verde-oscuro: #145034;
            --verde-claro: #e6f2e9;
            --sobre-verde: #ffffff;
            --mantequilla: #e9a93a;
            --mantequilla-claro: #fdf1d6;
            --neutro-texto: #4c5a63;
            --neutro-fondo: #edf1f3;
            --positivo-texto: #14664a;
            --positivo-fondo: #e4f3ea;
            --negativo-texto: #a5342a;
            --negativo-fondo: #fbeae7;
            --pendiente-texto: #8a5a06;
            --pendiente-fondo: #fdf1d6;
            --radio: 16px;
            --radio-chico: 10px;
            --ancho: 42rem;
            --sombra-suave: 0 1px 2px rgba(18, 39, 31, 0.05), 0 10px 30px -18px rgba(18, 39, 31, 0.35);
        }
        *,
        *::before,
        *::after { box-sizing: border-box; }
        [hidden] { display: none !important; }
        html { scroll-behavior: smooth; }
        body {
            margin: 0;
            padding: 0;
            background: var(--fondo);
            color: var(--tinta);
            font-family: Inter, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            font-size: 16px;
            line-height: 1.5;
            -webkit-text-size-adjust: 100%;
        }
        a { color: var(--verde); text-decoration: none; }
        a:hover { text-decoration: underline; }
        .contenedor {
            width: 100%;
            max-width: var(--ancho);
            margin: 0 auto;
            padding: 0 1rem;
        }
        .tarjeta {
            background: var(--superficie);
            border: 1px solid var(--borde);
            border-radius: var(--radio);
            box-shadow: var(--sombra-suave);
            padding: 1.5rem;
        }
        .campo {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
            margin-bottom: 1rem;
        }
        .campo label {
            font-size: 0.82rem;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: var(--tinta-suave);
        }
        .campo input {
            width: 100%;
            padding: 0.6rem 0.8rem;
            font-size: 0.95rem;
            color: var(--tinta);
            background: var(--superficie-suave);
            border: 1px solid var(--borde);
            border-radius: 8px;
            font-family: inherit;
        }
        .campo input:focus {
            outline: 2px solid var(--verde);
            outline-offset: 2px;
            border-color: var(--verde);
        }
        .error {
            color: var(--negativo-texto);
            font-size: 0.82rem;
            margin-top: 0.25rem;
        }
        .boton {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            padding: 0.7rem 1.25rem;
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--sobre-verde);
            background: var(--verde);
            border: 1px solid transparent;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            font-family: inherit;
        }
        .boton:hover { background: var(--verde-oscuro); }
        .boton--secundario {
            color: var(--tinta);
            background: var(--superficie);
            border-color: var(--borde-fuerte);
        }
        .boton--secundario:hover { background: var(--superficie-suave); color: var(--tinta); }
        .boton[disabled] { opacity: 0.6; cursor: not-allowed; }
        .fila {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid var(--borde);
        }
        .pista {
            font-size: 0.85rem;
            color: var(--tinta-suave);
            margin-top: 0.5rem;
        }
        .pista a { color: var(--verde); font-weight: 600; text-decoration: none; }
        .pista a:hover { text-decoration: underline; }
        .aviso {
            padding: 0.85rem 1rem;
            border-radius: var(--radio-chico);
            margin-bottom: 1rem;
            font-size: 0.88rem;
        }
        .aviso--error {
            background: var(--negativo-fondo);
            color: var(--negativo-texto);
            border: 1px solid transparent;
        }
        .aviso--exito {
            background: var(--verde-claro);
            color: var(--verde-oscuro);
            border: 1px solid transparent;
        }
        .cabecera {
            background: var(--fondo-calido);
            border-bottom: 1px solid var(--borde);
            padding-block: 0.65rem;
        }
        .cabecera .contenedor {
            max-width: 74rem;
        }
        .marca {
            display: inline-flex;
            align-items: center;
            gap: 0.55rem;
            font-weight: 700;
            font-size: 1.25rem;
            color: var(--tinta);
            text-decoration: none;
        }
        .marca__icono {
            display: grid;
            place-items: center;
            width: 2.1rem;
            height: 2.1rem;
            color: #fff;
            background: linear-gradient(150deg, var(--verde), var(--verde-oscuro));
            border-radius: 10px;
            box-shadow: 0 6px 14px -8px rgba(20, 80, 52, 0.9);
        }
        .pagina {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .principal {
            flex: 1;
            padding: 1.25rem 0 4rem;
        }
        .pie {
            padding: 2rem 1rem 3rem;
            font-size: 0.8rem;
            color: var(--tinta-suave);
            text-align: center;
            border-top: 1px solid var(--borde);
        }
        @media (max-width: 40rem) {
            .tarjeta { padding: 1.25rem; border-radius: var(--radio-chico); }
        }
    </style>
    @stack('styles')
</head>
<body>
    <header class="cabecera cabecera--navegacion">
        <div class="contenedor">
            <x-navegacion />
        </div>
    </header>

    <main class="principal">
        <div class="contenedor">
            <x-ruta-navegacion :titulo="$titulo ?? null" />
            @if (session('status'))
                <div class="aviso aviso--exito">{{ session('status') }}</div>
            @endif
            @if ($error ?? null)
                <div class="aviso aviso--error">{{ $error }}</div>
            @endif
            {{ $slot }}
        </div>
    </main>

    <footer class="pie">
        <div class="contenedor">
            Directorio del piloto de lácteos · Cotopaxi. Los datos de contacto solo se muestran cuando el comercio los autoriza.
        </div>
    </footer>
</body>
</html>
