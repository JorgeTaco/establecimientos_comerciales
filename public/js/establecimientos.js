/*
 * Pantalla del listado de establecimientos.
 *
 * Responsabilidades:
 *   1. Pedir los datos a DatosEstablecimientos (capa de datos aparte).
 *   2. Pintar las tarjetas y los tres estados de recepción de propuestas.
 *   3. Filtrar por texto, tipo, parroquia y estado.
 *   4. Mostrar el mapa de Google con los comercios que tienen coordenadas y,
 *      si no hay clave configurada, un aviso claro sin romper la página.
 */
(function () {
    'use strict';

    var config = window.ESTABLECIMIENTOS || {};
    var datosApi = window.DatosEstablecimientos;

    var dom = {
        resumenTotal: document.getElementById('resumen-total'),
        resumenReciben: document.getElementById('resumen-reciben'),
        resumenConfirmar: document.getElementById('resumen-confirmar'),
        buscar: document.getElementById('buscar'),
        tipo: document.getElementById('filtro-tipo'),
        parroquia: document.getElementById('filtro-parroquia'),
        estado: document.getElementById('filtro-estado'),
        interes: document.getElementById('filtro-interes'),
        limpiar: document.getElementById('limpiar'),
        contador: document.getElementById('contador'),
        listado: document.getElementById('listado'),
        carga: document.getElementById('estado-carga'),
        vacio: document.getElementById('estado-vacio'),
        error: document.getElementById('estado-error'),
        mapa: document.getElementById('mapa'),
        mapaAviso: document.getElementById('mapa-aviso'),
        mapaAvisoTexto: document.getElementById('mapa-aviso-texto'),
        mapaUbicados: document.getElementById('mapa-ubicados'),
        mapaNota: document.getElementById('mapa-nota'),
        origen: document.getElementById('origen-datos')
    };

    var estado = {
        todos: [],
        visibles: [],
        filtros: { texto: '', tipo: '', parroquia: '', estado: '', interes: '' },
        mapa: null,
        marcadores: [],
        marcadoresAvanzados: false,
        mapaListo: false
    };

    // ---------------------------------------------------------------- utilidades

    function crear(etiqueta, clase, contenido) {
        var nodo = document.createElement(etiqueta);
        if (clase) {
            nodo.className = clase;
        }
        if (contenido !== undefined && contenido !== null) {
            nodo.textContent = contenido;
        }
        return nodo;
    }

    /** Minúsculas y sin tildes, para que "Tanicuchi" encuentre "Tanicuchí". */
    function plegar(texto) {
        return String(texto || '')
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '');
    }

    function coordinar(valor, decimales) {
        return Number(valor).toFixed(decimales === undefined ? 5 : decimales);
    }

    // ------------------------------------------------------------------- arranque

    function iniciar() {
        if (!datosApi) {
            mostrarError('No se cargó la capa de datos del listado.');
            return;
        }

        // Intenta la API y, si aún no tiene datos, cae al JSON ficticio.
        var cargarListado = datosApi.cargarConRespaldo || function (api, ejemplo) {
            return datosApi.cargar(ejemplo).then(function (lista) {
                return { lista: lista, origen: 'ejemplo', detalle: '' };
            });
        };

        cargarListado(config.api, config.datos)
            .then(function (resultado) {
                var lista = resultado.lista;
                mostrarOrigen(resultado);
                estado.todos = lista;
                llenarSelectores(lista);
                actualizarResumen(lista);
                conectarEventos();
                aplicarFiltros();
                ocultar(dom.carga);
                iniciarMapa(lista);
            })
            .catch(function (error) {
                mostrarError('No se pudieron cargar los establecimientos. ' + error.message);
            });
    }

    /** Aviso visible cuando la pantalla no está leyendo la API real. */
    function mostrarOrigen(resultado) {
        if (!dom.origen) {
            return;
        }

        if (resultado.origen === 'api') {
            ocultar(dom.origen);
            return;
        }

        var mensaje =
            'Mostrando ' + resultado.lista.length + ' comercios de ejemplo (ficticios). ' +
            'La API todavía no tiene comercios cargados.';

        if (resultado.detalle) {
            // El detalle técnico va a la consola, no a la pantalla.
            window.console.warn('Respaldo de datos:', resultado.detalle);
        }

        dom.origen.textContent = mensaje;
        mostrar(dom.origen);
    }

    function mostrarError(mensaje) {
        ocultar(dom.carga);
        dom.error.textContent = mensaje;
        mostrar(dom.error);
    }

    function mostrar(nodo) {
        if (nodo) {
            nodo.hidden = false;
        }
    }

    function ocultar(nodo) {
        if (nodo) {
            nodo.hidden = true;
        }
    }

    // -------------------------------------------------------------- filtros/UI

    function llenarSelectores(lista) {
        var tipos = {};
        var parroquias = {};

        lista.forEach(function (item) {
            if (item.tipo) {
                tipos[item.tipo] = item.tipoEtiqueta;
            }
            if (item.parroquia) {
                parroquias[item.parroquia] = true;
            }
        });

        Object.keys(tipos)
            .sort()
            .forEach(function (valor) {
                dom.tipo.appendChild(opcion(valor, tipos[valor]));
            });

        Object.keys(parroquias)
            .sort(function (a, b) {
                return a.localeCompare(b, 'es');
            })
            .forEach(function (valor) {
                dom.parroquia.appendChild(opcion(valor, valor));
            });
    }

    function opcion(valor, etiqueta) {
        var nodo = document.createElement('option');
        nodo.value = valor;
        nodo.textContent = etiqueta;
        return nodo;
    }

    function conectarEventos() {
        var temporizador = null;

        dom.buscar.addEventListener('input', function (evento) {
            var valor = evento.target.value;
            window.clearTimeout(temporizador);
            temporizador = window.setTimeout(function () {
                estado.filtros.texto = valor;
                aplicarFiltros();
            }, 200);
        });

        dom.tipo.addEventListener('change', function (evento) {
            estado.filtros.tipo = evento.target.value;
            aplicarFiltros();
        });

        dom.parroquia.addEventListener('change', function (evento) {
            estado.filtros.parroquia = evento.target.value;
            aplicarFiltros();
        });

        dom.estado.addEventListener('change', function (evento) {
            estado.filtros.estado = evento.target.value;
            aplicarFiltros();
        });

        if (dom.interes) {
            dom.interes.addEventListener('change', function (evento) {
                estado.filtros.interes = evento.target.value;
                aplicarFiltros();
            });
        }

        dom.limpiar.addEventListener('click', function () {
            estado.filtros = { texto: '', tipo: '', parroquia: '', estado: '', interes: '' };
            dom.buscar.value = '';
            dom.tipo.value = '';
            dom.parroquia.value = '';
            dom.estado.value = '';
            if (dom.interes) {
                dom.interes.value = '';
            }
            aplicarFiltros();
            dom.buscar.focus();
        });
    }

    function hayFiltros() {
        var f = estado.filtros;
        return Boolean(f.texto || f.tipo || f.parroquia || f.estado || f.interes);
    }

    function aplicarFiltros() {
        var f = estado.filtros;
        var texto = plegar(f.texto.trim());

        estado.visibles = estado.todos.filter(function (item) {
            if (f.tipo && item.tipo !== f.tipo) {
                return false;
            }
            if (f.parroquia && item.parroquia !== f.parroquia) {
                return false;
            }
            if (f.estado && item.estadoClave !== f.estado) {
                return false;
            }
            if (f.interes && item.interesDeclarado !== (f.interes === '1')) {
                return false;
            }
            if (texto) {
                var buscable = plegar([item.nombre, item.parroquia, item.sector, item.direccion, item.tipoEtiqueta].join(' '));
                if (buscable.indexOf(texto) === -1) {
                    return false;
                }
            }
            return true;
        });

        pintarListado(estado.visibles);
        actualizarContador();
        actualizarMarcadores(estado.visibles);
        dom.limpiar.hidden = !hayFiltros();
    }

    function actualizarResumen(lista) {
        dom.resumenTotal.textContent = lista.length;
        dom.resumenReciben.textContent = lista.filter(function (item) {
            return item.estadoClave === 'recibe_propuestas';
        }).length;
        dom.resumenConfirmar.textContent = lista.filter(function (item) {
            return item.estadoClave === 'por_confirmar';
        }).length;
    }

    function actualizarContador() {
        var total = estado.todos.length;
        var visibles = estado.visibles.length;

        if (visibles === total) {
            dom.contador.textContent = '(' + total + ')';
        } else {
            dom.contador.textContent = '(' + visibles + ' de ' + total + ')';
        }

        dom.vacio.hidden = visibles !== 0;
    }

    // ------------------------------------------------------------------- tarjetas

    function pintarListado(lista) {
        dom.listado.textContent = '';

        lista.forEach(function (item) {
            dom.listado.appendChild(tarjeta(item));
        });
    }

    function tarjeta(item) {
        var contenedor = crear('li', 'tarjeta');
        contenedor.dataset.estado = item.estadoClave || 'sin-estado';

        var encabezado = crear('div', 'tarjeta__encabezado');
        encabezado.appendChild(crear('h3', 'tarjeta__nombre', item.nombre));
        encabezado.appendChild(etiquetaEstado(item));
        contenedor.appendChild(encabezado);

        var meta = crear('p', 'tarjeta__meta');
        meta.appendChild(crear('span', 'tarjeta__tipo', item.tipoEtiqueta));
        if (item.parroquia) {
            meta.appendChild(crear('span', 'tarjeta__separador', '·'));
            meta.appendChild(crear('span', 'tarjeta__parroquia', item.parroquia));
        }
        if (item.sector) {
            meta.appendChild(crear('span', 'tarjeta__separador', '·'));
            meta.appendChild(crear('span', '', item.sector));
        }
        contenedor.appendChild(meta);

        if (item.interesDeclarado !== null) {
            var interes = item.interesDeclarado ? 'Sí' : 'No';
            contenedor.appendChild(crear('p', 'tarjeta__meta', 'Interés declarado en la encuesta: ' + interes));
        }
        contenedor.appendChild(ubicacion(item));

        return contenedor;
    }

    function etiquetaEstado(item) {
        return crear('span', 'estado estado--' + item.estadoTono, item.estadoEtiqueta);
    }

    function ubicacion(item) {
        var linea = crear('p', 'tarjeta__ubicacion');

        if (item.tieneUbicacion) {
            linea.classList.add('tarjeta__ubicacion--ok');
            linea.appendChild(crear('span', 'icono', '📍'));
            linea.appendChild(
                crear(
                    'span',
                    null,
                    'En el mapa · ' + coordinar(item.latitud) + ', ' + coordinar(item.longitud)
                )
            );
        } else {
            linea.classList.add('tarjeta__ubicacion--pendiente');
            linea.appendChild(crear('span', 'icono', '⚠️'));
            linea.appendChild(crear('span', null, 'Pendiente de ubicar en el mapa'));
        }

        return linea;
    }

    // ---------------------------------------------------------------------- mapa

    function iniciarMapa(lista) {
        var ubicados = lista.filter(function (item) {
            return item.tieneUbicacion;
        });
        var pendientes = lista.length - ubicados.length;

        dom.mapaNota.textContent =
            ubicados.length + ' de ' + lista.length + ' comercios tienen coordenadas' +
            (pendientes
                ? ' · ' + pendientes + (pendientes === 1 ? ' pendiente' : ' pendientes') + ' de ubicar'
                : '');

        if (!config.googleMapsKey) {
            avisoMapa(
                'El mapa aún no está configurado. Comercios con coordenadas:',
                ubicados
            );
            return;
        }

        var errorAutenticacion = false;
        function mostrarMapaNoDisponible() {
            errorAutenticacion = true;
            estado.mapaListo = false;
            avisoMapa('Google Maps no está disponible. Comercios con coordenadas:', ubicados);
        }

        window.gm_authFailure = mostrarMapaNoDisponible;
        cargarGoogleMaps()
            .then(function () {
                if (!errorAutenticacion) {
                    crearMapa(ubicados);
                }
            })
            .catch(mostrarMapaNoDisponible);
    }

    function cargarGoogleMaps() {
        if (window.google && window.google.maps) {
            return Promise.resolve();
        }

        return new Promise(function (resolver, rechazar) {
            var script = document.createElement('script');
            var parametros = [
                'key=' + encodeURIComponent(config.googleMapsKey),
                'libraries=marker',
                'language=es',
                'region=EC',
                'loading=async',
                'callback=__mapaEstablecimientosListo'
            ];

            window.__mapaEstablecimientosListo = function () {
                resolver();
            };

            script.src = 'https://maps.googleapis.com/maps/api/js?' + parametros.join('&');
            script.async = true;
            script.onerror = function () {
                rechazar(new Error('Sin conexión con Google Maps'));
            };
            document.head.appendChild(script);

            window.setTimeout(function () {
                rechazar(new Error('Tiempo de espera agotado'));
            }, 8000);
        });
    }

    function crearMapa(ubicados) {
        ocultar(dom.mapaAviso);
        mostrar(dom.mapa);

        estado.mapa = new window.google.maps.Map(dom.mapa, {
            center: config.centro || { lat: -0.9333, lng: -78.6167 },
            zoom: 11,
            mapId: config.googleMapsMapId || undefined,
            mapTypeControl: false,
            streetViewControl: false,
            fullscreenControl: false
        });

        estado.marcadoresAvanzados = Boolean(
            config.googleMapsMapId && window.google.maps.marker && window.google.maps.marker.AdvancedMarkerElement
        );

        estado.mapaListo = true;
        actualizarMarcadores(estado.visibles);

        if (ubicados.length === 1) {
            estado.mapa.setCenter({ lat: ubicados[0].latitud, lng: ubicados[0].longitud });
            estado.mapa.setZoom(14);
        }
    }

    function actualizarMarcadores(lista) {
        if (!estado.mapaListo || !estado.mapa) {
            return;
        }

        estado.marcadores.forEach(function (marcador) {
            marcador.map = null;
        });
        estado.marcadores = [];

        var limites = new window.google.maps.LatLngBounds();
        var conCoordenadas = lista.filter(function (item) {
            return item.tieneUbicacion;
        });

        conCoordenadas.forEach(function (item) {
            var posicion = { lat: item.latitud, lng: item.longitud };
            var marcador = crearMarcador(posicion, item);
            marcador.map = estado.mapa;
            estado.marcadores.push(marcador);
            limites.extend(posicion);
        });

        if (conCoordenadas.length > 1) {
            estado.mapa.fitBounds(limites, 48);
        }
    }

    function crearMarcador(posicion, item) {
        var ventana = new window.google.maps.InfoWindow({
            content:
                '<strong>' + escapar(item.nombre) + '</strong><br>' +
                escapar(item.tipoEtiqueta) +
                (item.parroquia ? ' · ' + escapar(item.parroquia) : '') +
                '<br>' + escapar(item.estadoEtiqueta)
        });

        if (estado.marcadoresAvanzados) {
            var avanzado = new window.google.maps.marker.AdvancedMarkerElement({
                position: posicion,
                title: item.nombre
            });
            avanzado.addListener('click', function () {
                ventana.open({ map: estado.mapa, anchor: avanzado });
            });
            return avanzado;
        }

        var clasico = new window.google.maps.Marker({
            position: posicion,
            title: item.nombre
        });
        clasico.addListener('click', function () {
            ventana.open({ map: estado.mapa, anchor: clasico });
        });
        return clasico;
    }

    function escapar(valor) {
        return String(valor)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    /** Aviso cuando no hay mapa: explica por qué y lista lo que sí tiene coordenadas. */
    function avisoMapa(mensaje, ubicados) {
        ocultar(dom.mapa);
        mostrar(dom.mapaAviso);
        dom.mapaAvisoTexto.textContent = mensaje;
        dom.mapaUbicados.textContent = '';

        ubicados.forEach(function (item) {
            var fila = crear('li', 'mapa__fila');
            fila.appendChild(crear('span', 'mapa__fila-nombre', item.nombre));
            fila.appendChild(
                crear('span', 'mapa__fila-parroquia', item.parroquia || 'Sin parroquia')
            );
            fila.appendChild(
                crear('span', 'mapa__fila-coords', coordinar(item.latitud) + ', ' + coordinar(item.longitud))
            );
            dom.mapaUbicados.appendChild(fila);
        });
    }

    // ------------------------------------------------------------------- arranque

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciar);
    } else {
        iniciar();
    }
})();
