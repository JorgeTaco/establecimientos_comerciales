<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Establecimientos · {{ config('app.name') }}</title>
    <meta name="description" content="Directorio de comercios del piloto de lácteos en Latacunga, Cotopaxi. Encuentra dónde ofrecer tus productos y revisa si reciben propuestas.">
    <meta name="theme-color" content="#1f6f4a">
    <link rel="stylesheet" href="{{ asset('css/establecimientos.css') }}">
</head>
<body>
    <header class="cabecera">
        <div class="barra">
            <a class="marca" href="{{ url('/establecimientos') }}">
                <span class="marca__icono" aria-hidden="true">
                    <svg viewBox="0 0 32 32" role="img" focusable="false">
                        <path d="M16 3c-3.6 5.2-7 9.4-7 14a7 7 0 0 0 14 0c0-4.6-3.4-8.8-7-14Z" fill="currentColor"/>
                        <path d="M12.6 17.5c.2 2.3 1.5 3.9 3.6 4.6" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round" opacity=".75"/>
                    </svg>
                </span>
                <span class="marca__texto">Establecimientos</span>
            </a>
            <p class="piloto">
                <span class="piloto__punto" aria-hidden="true"></span>
                Piloto lácteos · Cotopaxi
            </p>
        </div>
        <x-menu-cuenta />

        <div class="heroe">
            <p class="heroe__contexto">Latacunga · Cotopaxi</p>
            <h1 class="heroe__titulo">Encuentra dónde vender tus productos</h1>
            <p class="heroe__texto">
                Directorio de tiendas, minimarkets y supermercados. Revisa la ficha de cada
                comercio y confirma si recibe propuestas antes de contactarlo.
            </p>

            <div class="buscador">
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
                    name="buscar"
                    placeholder="Buscar por nombre, parroquia o sector"
                    autocomplete="off"
                    enterkeyhint="search"
                >
                <button type="button" class="buscador__limpiar" id="limpiar" aria-label="Limpiar búsqueda y filtros" hidden>✕</button>
            </div>

            <dl class="resumen" id="resumen" aria-live="polite">
                <div class="resumen__dato">
                    <dt>Comercios</dt>
                    <dd id="resumen-total">—</dd>
                </div>
                <div class="resumen__dato resumen__dato--positivo">
                    <dt>Reciben propuestas</dt>
                    <dd id="resumen-reciben">—</dd>
                </div>
                <div class="resumen__dato resumen__dato--pendiente">
                    <dt>Por confirmar</dt>
                    <dd id="resumen-confirmar">—</dd>
                </div>
                <div class="resumen__dato resumen__dato--neutro">
                    <dt>Con ubicación</dt>
                    <dd id="resumen-ubicados">—</dd>
                </div>
            </dl>
        </div>

        <div class="heroe__adorno" aria-hidden="true">
            <svg viewBox="0 0 240 240" focusable="false">
                <circle cx="120" cy="120" r="112" fill="var(--verde-claro)"/>
                <path d="M120 44c-20 29-38 51-38 73a38 38 0 0 0 76 0c0-22-18-44-38-73Z" fill="var(--verde)"/>
                <path d="M104 118c2 13 9 22 21 26" fill="none" stroke="#fff" stroke-width="7" stroke-linecap="round" opacity=".6"/>
                <path d="M46 194c15-19 29-19 44 0s29 19 44 0 29-19 44 0" fill="none" stroke="var(--mantequilla)" stroke-width="8" stroke-linecap="round"/>
                <circle cx="186" cy="62" r="9" fill="var(--mantequilla)"/>
                <circle cx="58" cy="74" r="5" fill="var(--verde)" opacity=".45"/>
            </svg>
        </div>
    </header>

    <main class="contenido">
        <section class="pasos" aria-labelledby="pasos-titulo">
            <h2 class="visualmente-oculto" id="pasos-titulo">Cómo funciona</h2>
            <ol class="pasos__lista">
                <li class="paso">
                    <span class="paso__numero" aria-hidden="true">1</span>
                    <p class="paso__texto"><strong>Busca</strong> el comercio por parroquia o tipo.</p>
                </li>
                <li class="paso">
                    <span class="paso__numero" aria-hidden="true">2</span>
                    <p class="paso__texto"><strong>Abre su ficha</strong> y revisa refrigeración, pedido y horario.</p>
                </li>
                <li class="paso">
                    <span class="paso__numero" aria-hidden="true">3</span>
                    <p class="paso__texto"><strong>Contacta</strong> solo si recibe propuestas.</p>
                </li>
            </ol>
        </section>

        <p class="mensaje mensaje--aviso" id="origen-datos" role="status" hidden></p>

        <section class="panel-filtros" aria-labelledby="filtros-titulo">
            <h2 class="visualmente-oculto" id="filtros-titulo">Buscar y filtrar</h2>

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
        </section>

        <div class="barra-listado">
            <div class="barra-listado__grupo">
                <h2 class="seccion__titulo" id="listado-titulo">
                    Comercios <span class="contador" id="contador"></span>
                </h2>
                <p class="seccion__nota" id="contador-linea"></p>
            </div>

            <div class="barra-listado__acciones">
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

                <div class="vista-toggle" role="tablist" aria-label="Vista del directorio">
                    <button type="button" class="vista-toggle__boton is-activo" id="boton-lista" role="tab" aria-selected="true" aria-controls="vista-lista" tabindex="0">
                        <span aria-hidden="true">☰</span> Lista
                    </button>
                    <button type="button" class="vista-toggle__boton" id="boton-mapa" role="tab" aria-selected="false" aria-controls="vista-mapa" tabindex="-1">
                        <span aria-hidden="true">◉</span> Mapa
                    </button>
                </div>
            </div>
        </div>

        <p class="mensaje" id="estado-carga">Cargando establecimientos…</p>

        <p class="mensaje mensaje--error" id="estado-error" role="alert" hidden></p>

        <p class="mensaje" id="estado-vacio" hidden>
            Ningún comercio coincide con la búsqueda. Prueba con otros filtros.
        </p>

        <div id="vista-lista">
            <ul class="listado" id="listado"></ul>

            <div class="listado__pie">
                <button type="button" class="boton boton--secundario" id="mas" hidden>Mostrar más comercios</button>
            </div>
        </div>

        <section class="mapa" id="vista-mapa" aria-labelledby="mapa-titulo" hidden>
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
            datos: @json(asset('datos/establecimientos.ejemplo.json')),
            api: @json(url('/api/establecimientos/buscar')),
            apiBase: @json(url('/api/establecimientos')),
            fichas: @json(route('establecimientos.index')),
            categorias: @json(url('/api/categorias')),
            googleMapsKey: @json(config('services.google_maps.key')),
            googleMapsMapId: @json(config('services.google_maps.map_id')),
            centro: { lat: -0.9333, lng: -78.6167 }
        };
    </script>

    <script src="{{ asset('js/datos-establecimientos.js') }}"></script>
    <script src="{{ asset('js/establecimientos.js') }}"></script>
</body>
</html>
