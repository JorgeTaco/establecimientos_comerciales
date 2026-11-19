<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Establecimientos · {{ config('app.name') }}</title>
    <meta name="description" content="Directorio de comercios del piloto de lácteos en Latacunga, Cotopaxi. Encuentra dónde ofrecer tus productos y revisa si reciben propuestas.">
    <meta name="theme-color" content="#1f6f4a">
    <link rel="stylesheet" href="{{ asset('css/establecimientos.css') }}">
    <link rel="stylesheet" href="{{ asset('css/navegacion.css') }}">
</head>
<body class="directorio">
    <header class="cabecera cabecera--directorio cabecera--navegacion">
        <div class="barra">
            <x-navegacion />
        </div>
    </header>

    <main class="contenido">
        <section class="entrada-directorio" aria-labelledby="titulo-directorio">
            <p class="heroe__contexto">Latacunga · Cotopaxi</p>
            <h1 class="heroe__titulo" id="titulo-directorio">Encuentra comercios</h1>
            <form class="buscador" id="form-busqueda" role="search" method="get" action="{{ route('establecimientos.index') }}">
                <label class="visualmente-oculto" for="buscar">Buscar comercio</label>
                <span class="buscador__icono" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false">
                        <circle cx="10.5" cy="10.5" r="6.5" fill="none" stroke="currentColor" stroke-width="2"/>
                        <path d="m15.5 15.5 4.5 4.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </span>
                <input
                    type="search"
                    id="buscar"
                    name="q"
                    maxlength="100"
                    placeholder="Nombre, sector o dirección"
                    autocomplete="off"
                    enterkeyhint="search"
                >
                <button type="button" class="buscador__limpiar" id="limpiar" aria-label="Limpiar filtros" hidden>✕</button>
            </form>
        </section>

        <div class="controles-directorio">
            <div class="chips" role="group" aria-label="Filtrar por recepción de propuestas">
                <button type="button" class="chip chip--estado is-activo" data-estado="">Todos los estados</button>
                <button type="button" class="chip chip--estado chip--positivo" data-estado="recibe_propuestas">
                    <span class="chip__punto" aria-hidden="true"></span> Recibe propuestas
                </button>
                <button type="button" class="chip chip--estado chip--pendiente" data-estado="por_confirmar">
                    <span class="chip__punto" aria-hidden="true"></span> Por confirmar
                </button>
                <button type="button" class="chip chip--estado chip--negativo" data-estado="no_recibe_por_ahora">
                    <span class="chip__punto" aria-hidden="true"></span> No recibe por ahora
                </button>
            </div>

            <div class="barra-listado__acciones">
                <div class="vista-toggle" role="tablist" aria-label="Vista del directorio">
                    <button type="button" class="vista-toggle__boton is-activo" id="boton-lista" role="tab" aria-selected="true" aria-controls="vista-lista" tabindex="0">
                        <span aria-hidden="true">☰</span> Lista
                    </button>
                    <button type="button" class="vista-toggle__boton" id="boton-mapa" role="tab" aria-selected="false" aria-controls="vista-mapa" tabindex="-1">
                        <span aria-hidden="true">◉</span> Mapa
                    </button>
                </div>
                <div class="campo campo--orden">
                    <label for="orden">Ordenar</label>
                    <select id="orden" name="orden">
                        <option value="nombre">Nombre</option>
                        <option value="estado">Recibe primero</option>
                        <option value="ubicados">Con ubicación</option>
                        <option value="tipo">Tipo</option>
                        <option value="distancia">Distancia</option>
                    </select>
                </div>
            </div>
        </div>

        <details class="panel-filtros" id="filtros">
            <summary>Más filtros <span class="contador" id="filtros-activos" hidden></span></summary>
            <div class="filtros">
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
                    <label for="filtro-categoria">Categoría</label>
                    <select id="filtro-categoria">
                        <option value="">Todas</option>
                    </select>
                </div>

                {{-- Solo se publica con PUBLICAR_INTERES_ENCUESTA=true (config/app.php). --}}
                @if (config('app.publicar_interes_encuesta'))
                    <div class="campo">
                        <label for="filtro-interes">Interés en la encuesta</label>
                        <select id="filtro-interes" name="interes_declarado">
                            <option value="">Todos</option>
                            <option value="1">Sí</option>
                            <option value="0">No</option>
                        </select>
                    </div>
                @endif

                <label class="chip chip--check" for="filtro-ubicados">
                    <input type="checkbox" id="filtro-ubicados" name="ubicados">
                    <span>Solo con ubicación</span>
                </label>
            </div>
            <details>
                <summary>Buscar por distancia</summary>
                <form id="buscar-cerca" class="filtros">
                    <div class="campo">
                        <label for="origen-latitud">Latitud de origen</label>
                        <input id="origen-latitud" name="latitud" type="number" step="any" min="-90" max="90" required>
                    </div>
                    <div class="campo">
                        <label for="origen-longitud">Longitud de origen</label>
                        <input id="origen-longitud" name="longitud" type="number" step="any" min="-180" max="180" required>
                    </div>
                    <div class="campo">
                        <label for="radio-km">Radio en kilómetros</label>
                        <input id="radio-km" name="distancia_km" type="number" min="0.1" max="500" step="0.1" value="5" required>
                    </div>
                    <button type="submit" class="boton boton--secundario">Buscar cerca</button>
                    <button type="button" class="boton boton--secundario" id="usar-ubicacion">Usar mi ubicación</button>
                    <p id="ubicacion-aviso" role="status"></p>
                </form>
            </details>
        </details>

        <div class="barra-listado">
            <div class="barra-listado__grupo">
                <h2 class="seccion__titulo" id="listado-titulo">
                    Comercios <span class="contador" id="contador"></span>
                </h2>
                <p class="seccion__nota" id="contador-linea" aria-live="polite"></p>
            </div>
        </div>

        <p class="mensaje" id="estado-carga" role="status" hidden>Cargando establecimientos…</p>

        <div class="mensaje mensaje--error" id="estado-error" hidden>
            <p id="estado-error-texto" role="alert"></p>
            <button type="button" class="boton boton--secundario" id="reintentar">Reintentar</button>
        </div>
        <p class="mensaje" id="directorio-vacio" role="status" hidden>
            El directorio aún no tiene comercios registrados.
        </p>

        <p class="mensaje" id="estado-vacio" role="status" hidden>
            Ningún comercio coincide con la búsqueda. Prueba con otros filtros.
        </p>

        <div id="vista-lista" role="tabpanel" aria-labelledby="boton-lista">
            <ul class="listado" id="listado"></ul>

            <div class="listado__pie">
                <button type="button" class="boton boton--secundario" id="mas" hidden>Mostrar más comercios</button>
            </div>
        </div>

        <section class="mapa" id="vista-mapa" role="tabpanel" aria-labelledby="boton-mapa" hidden>
            <div class="seccion__cabecera">
                <h2 class="seccion__titulo" id="mapa-titulo">Mapa</h2>
                <p class="seccion__nota" id="mapa-nota"></p>
            </div>

            <button type="button" class="boton boton--secundario" id="cargar-mapa" aria-controls="mapa" hidden>Mostrar mapa</button>
            <div id="mapa" class="mapa__lienzo" role="region" aria-label="Mapa de establecimientos con coordenadas" hidden></div>

            <div id="mapa-aviso" class="mapa__aviso">
                <p id="mapa-aviso-texto" role="status">Preparando las coordenadas…</p>
                <ul id="mapa-ubicados" class="mapa__lista"></ul>
            </div>
        </section>

        <noscript>
            <p class="mensaje mensaje--error">
                Este listado necesita JavaScript para mostrar los datos. Actívalo en tu navegador.
            </p>
        </noscript>
    </main>

    <footer class="pie">
        <p>
            Directorio del piloto de lácteos. Los datos de contacto de cada comercio solo se
            muestran cuando el comercio los autoriza.
        </p>
    </footer>

    {{-- Panel de ficha: se abre al pulsar "Ver ficha" en cualquier tarjeta. --}}
    <div class="ficha-fondo" id="ficha-fondo" hidden></div>

    <aside
        class="ficha"
        id="ficha"
        role="dialog"
        aria-modal="true"
        aria-labelledby="ficha-nombre"
        hidden
    >
        <header class="ficha__cabecera">
            <span class="ficha__avatar" id="ficha-avatar" aria-hidden="true"></span>
            <div class="ficha__identidad">
                <h2 class="ficha__nombre" id="ficha-nombre">Comercio</h2>
                <p class="ficha__meta" id="ficha-meta"></p>
            </div>
            <button type="button" class="ficha__cerrar" id="ficha-cerrar" aria-label="Cerrar ficha">✕</button>
        </header>

        <div class="ficha__cuerpo" id="ficha-cuerpo"></div>

        <footer class="ficha__acciones" id="ficha-acciones"></footer>
    </aside>

    {{--
        Puente entre Laravel y el JavaScript. Aquí van las rutas y las claves que
        el navegador no puede leer desde .env.
    --}}
    <script>
        window.ESTABLECIMIENTOS = {
            api: @json(url('/api/establecimientos/buscar')),
            apiBase: @json(url('/api/establecimientos')),
            fichas: @json(route('establecimientos.index')),
            categorias: @json(url('/api/categorias')),
            googleMapsKey: @json(config('services.google_maps.key')),
            googleMapsMapId: @json(config('services.google_maps.map_id')),
            centro: { lat: -0.9333, lng: -78.6167 }
        };
    </script>

    <script src="{{ asset('js/datos-establecimientos.js') }}" defer></script>
    <script src="{{ asset('js/establecimientos.js') }}" defer></script>
</body>
</html>
