<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Establecimientos · {{ config('app.name') }}</title>
    <meta name="description" content="Listado de establecimientos comerciales del piloto de lácteos en Latacunga, Cotopaxi.">
    <link rel="stylesheet" href="{{ asset('css/establecimientos.css') }}">
</head>
<body>
    <header class="cabecera">
        <p class="cabecera__contexto">Piloto de lácteos · Latacunga, Cotopaxi</p>
        <h1 class="cabecera__titulo">Establecimientos</h1>
        <p class="cabecera__texto">
            Comercios donde un emprendedor puede ofrecer sus productos. Revisa si reciben
            propuestas antes de contactarlos.
        </p>

        <dl class="resumen" id="resumen" aria-live="polite">
            <div class="resumen__dato">
                <dt>Comercios</dt>
                <dd id="resumen-total">—</dd>
            </div>
            <div class="resumen__dato">
                <dt>Reciben propuestas</dt>
                <dd id="resumen-reciben">—</dd>
            </div>
            <div class="resumen__dato">
                <dt>Por confirmar</dt>
                <dd id="resumen-confirmar">—</dd>
            </div>
        </dl>
    </header>

    <main class="contenido">
        <p class="mensaje mensaje--aviso" id="origen-datos" role="status" hidden></p>

        <section class="filtros" aria-labelledby="filtros-titulo">
            <h2 class="visualmente-oculto" id="filtros-titulo">Buscar y filtrar</h2>

            <div class="campo">
                <label for="buscar">Buscar</label>
                <input
                    type="search"
                    id="buscar"
                    name="buscar"
                    placeholder="Nombre o parroquia"
                    autocomplete="off"
                >
            </div>

            <div class="campo">
                <label for="filtro-tipo">Tipo</label>
                <select id="filtro-tipo" name="tipo">
                    <option value="">Todos</option>
                </select>
            </div>

            <div class="campo">
                <label for="filtro-parroquia">Parroquia</label>
                <select id="filtro-parroquia" name="parroquia">
                    <option value="">Todas</option>
                </select>
            </div>

            <div class="campo">
                <label for="filtro-estado">Recepción de propuestas</label>
                <select id="filtro-estado" name="estado">
                    <option value="">Todos</option>
                    <option value="recibe_propuestas">Recibe propuestas</option>
                    <option value="no_recibe_por_ahora">No recibe por ahora</option>
                    <option value="por_confirmar">Por confirmar</option>
                </select>
            </div>

            @if (config('app.publicar_interes_encuesta'))
                <div class="campo">
                    <label for="filtro-interes">Interés declarado en la encuesta</label>
                    <select id="filtro-interes" name="interes_declarado">
                        <option value="">Todos</option>
                        <option value="1">Sí</option>
                        <option value="0">No</option>
                    </select>
                </div>
            @endif

            <button type="button" class="boton-secundario" id="limpiar" hidden>
                Limpiar filtros
            </button>
        </section>

        <section class="mapa" aria-labelledby="mapa-titulo">
            <div class="seccion__cabecera">
                <h2 class="seccion__titulo" id="mapa-titulo">Mapa</h2>
                <p class="seccion__nota" id="mapa-nota"></p>
            </div>

            <div id="mapa" class="mapa__lienzo" role="region" aria-label="Mapa de establecimientos con coordenadas" hidden></div>

            <div id="mapa-aviso" class="mapa__aviso">
                <p id="mapa-aviso-texto">Preparando el mapa…</p>
                <ul id="mapa-ubicados" class="mapa__lista"></ul>
            </div>
        </section>

        <section class="listado-seccion" aria-labelledby="listado-titulo">
            <div class="seccion__cabecera">
                <h2 class="seccion__titulo" id="listado-titulo">
                    Listado <span class="contador" id="contador"></span>
                </h2>
            </div>

            <p class="mensaje" id="estado-carga">Cargando establecimientos…</p>

            <p class="mensaje mensaje--error" id="estado-error" role="alert" hidden></p>

            <p class="mensaje" id="estado-vacio" hidden>
                Ningún comercio coincide con la búsqueda. Prueba con otros filtros.
            </p>

            <ul class="listado" id="listado"></ul>

            <noscript>
                <p class="mensaje mensaje--error">
                    Este listado necesita JavaScript para mostrar los datos. Actívalo en tu navegador.
                </p>
            </noscript>
        </section>
    </main>

    <footer class="pie">
        <p>
            El listado consulta <code>/api/establecimientos/buscar</code>. Mientras la base no tenga
            comercios cargados, se muestra el ejemplo ficticio de
            <code>public/datos/</code>.
        </p>
    </footer>

    {{--
        Puente entre Laravel y el JavaScript. Aquí van las rutas y las claves que
        el navegador no puede leer desde .env. Al pasar a la API basta cambiar
        "datos" por "api" en public/js/establecimientos.js.
    --}}
    <script>
        window.ESTABLECIMIENTOS = {
            datos: @json(asset('datos/establecimientos.ejemplo.json')),
            api: @json(url('/api/establecimientos/buscar')),
            googleMapsKey: @json(config('services.google_maps.key')),
            googleMapsMapId: @json(config('services.google_maps.map_id')),
            centro: { lat: -0.9333, lng: -78.6167 }
        };
    </script>

    <script src="{{ asset('js/datos-establecimientos.js') }}"></script>
    <script src="{{ asset('js/establecimientos.js') }}"></script>
</body>
</html>
