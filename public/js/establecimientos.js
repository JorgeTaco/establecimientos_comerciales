/*
 * Pantalla del directorio de establecimientos.
 *
 * Responsabilidades:
 *   1. Pedir los datos a DatosEstablecimientos (capa de datos aparte).
 *   2. Pintar tarjetas con acción "Ver ficha".
 *   3. Filtrar por texto, tipo, parroquia, estado y ubicación; ordenar.
 *   4. Abrir la ficha en un panel lateral con los datos del contrato.
 *   5. Mostrar el mapa con los comercios que tienen coordenadas o, si no hay
 *      clave, una lista clara sin romper la página.
 *
 * No duplica lógica del backend: solo presenta lo que devuelve la API.
 */
(function () {
    'use strict';

    var config = window.ESTABLECIMIENTOS || {};
    var datosApi = window.DatosEstablecimientos;

    var POR_PAGINA = 24;

    var dom = {
        origen: document.getElementById('origen-datos'),
        resumenTotal: document.getElementById('resumen-total'),
        resumenReciben: document.getElementById('resumen-reciben'),
        resumenConfirmar: document.getElementById('resumen-confirmar'),
        resumenUbicados: document.getElementById('resumen-ubicados'),
        buscar: document.getElementById('buscar'),
        tipo: document.getElementById('filtro-tipo'),
        parroquia: document.getElementById('filtro-parroquia'),
        categoria: document.getElementById('filtro-categoria'),
        buscarCerca: document.getElementById('buscar-cerca'),
        usarUbicacion: document.getElementById('usar-ubicacion'),
        ubicacionAviso: document.getElementById('ubicacion-aviso'),
        interes: document.getElementById('filtro-interes'),
        ubicados: document.getElementById('filtro-ubicados'),
        chips: document.querySelectorAll('.chip[data-estado]'),
        limpiar: document.getElementById('limpiar'),
        orden: document.getElementById('orden'),
        contador: document.getElementById('contador'),
        contadorLinea: document.getElementById('contador-linea'),
        listado: document.getElementById('listado'),
        mas: document.getElementById('mas'),
        carga: document.getElementById('estado-carga'),
        vacio: document.getElementById('estado-vacio'),
        error: document.getElementById('estado-error'),
        botonLista: document.getElementById('boton-lista'),
        botonMapa: document.getElementById('boton-mapa'),
        vistaLista: document.getElementById('vista-lista'),
        vistaMapa: document.getElementById('vista-mapa'),
        mapa: document.getElementById('mapa'),
        cargarMapa: document.getElementById('cargar-mapa'),
        mapaAviso: document.getElementById('mapa-aviso'),
        mapaAvisoTexto: document.getElementById('mapa-aviso-texto'),
        mapaUbicados: document.getElementById('mapa-ubicados'),
        mapaNota: document.getElementById('mapa-nota'),
        ficha: document.getElementById('ficha'),
        fichaFondo: document.getElementById('ficha-fondo'),
        fichaCerrar: document.getElementById('ficha-cerrar'),
        fichaNombre: document.getElementById('ficha-nombre'),
        fichaMeta: document.getElementById('ficha-meta'),
        fichaAvatar: document.getElementById('ficha-avatar'),
        fichaCuerpo: document.getElementById('ficha-cuerpo'),
        fichaAcciones: document.getElementById('ficha-acciones')
    };

    var estado = {
        todos: [],
        visibles: [],
        filtros: { texto: '', tipo: '', parroquia: '', categoria: '', estado: '', interes: '', soloUbicados: false, cerca: null },
        orden: 'nombre',
        vista: 'lista',
        pagina: 1,
        mapa: null,
        marcadores: [],
        marcadoresAvanzados: false,
        mapaListo: false,
        mapaIntentado: false,
        mapaError: null,
        consulta: 0,
        origen: null,
        origenVisible: null,
        fichaAbierta: null,
        focoPrevio: null
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

    function coordinar(valor) {
        return Number(valor).toFixed(5);
    }

    function iniciales(nombre) {
        var partes = String(nombre || '')
            .replace(/[^\p{L}\p{N} ]/gu, ' ')
            .split(/\s+/)
            .filter(Boolean);

        if (partes.length === 0) {
            return '·';
        }

        var letras = partes.slice(0, 2).map(function (parte) {
            return parte.charAt(0).toUpperCase();
        });

        return letras.join('');
    }

    function claseTipo(tipo) {
        var conocido = ['tienda', 'minimercado', 'supermercado', 'distribuidor', 'panaderia'];
        return conocido.indexOf(tipo) === -1 ? 'avatar--otro' : 'avatar--' + tipo;
    }

    function etiquetaEstado(item) {
        return crear('span', 'estado estado--' + item.estadoTono, item.estadoEtiqueta);
    }

    // ------------------------------------------------------------------- arranque

    function iniciar() {
        if (!datosApi) {
            mostrarError('No se cargó la capa de datos del listado.');
            return;
        }

        pintarEsqueletos();
        conectarEventos();
        marcarChipActivo();
        fetch(config.categorias, { headers: { Accept: 'application/json' } })
            .then(function (respuesta) {
                if (!respuesta.ok) throw new Error('Categorías no disponibles');
                return respuesta.json();
            })
            .then(function (datos) {
                datos.categorias.forEach(function (categoria) {
                    dom.categoria.append(opcion(String(categoria.id), categoria.nombre));
                });
            })
            .catch(function () { dom.categoria.disabled = true; });

        // Intenta la API y, si aún no tiene datos, cae al JSON ficticio.
        var cargarListado = datosApi.cargarConRespaldo || function (api, ejemplo, filtros) {
            return datosApi.cargar(ejemplo).then(function (lista) {
                return { lista: lista, origen: 'ejemplo', detalle: '' };
            });
        };

        cargarListado(config.api, config.datos, {})
            .then(function (resultado) {
                mostrarOrigen(resultado);
                estado.origen = resultado.origen;
                estado.todos = resultado.lista;
                llenarSelectores(resultado.lista);
                actualizarResumen(resultado.lista);
                aplicarFiltros();
                ocultar(dom.carga);
            })
            .catch(function (error) {
                mostrarError('No se pudieron cargar los establecimientos. ' + error.message);
            });
    }

    /** Convierte los filtros de la pantalla en los parámetros que espera la API. */
    function filtrosParaApi(f) {
        var api = {};
        if (f.texto.trim()) {
            api.q = f.texto.trim();
        }
        if (f.tipo) {
            api.tipo = f.tipo;
        }
        if (f.parroquia) {
            api.parroquia = f.parroquia;
        }
        if (f.categoria) {
            api.categoria_id = f.categoria;
        }
        if (f.cerca) {
            Object.assign(api, f.cerca);
        }
        if (f.estado) {
            api.recepcionProveedores = f.estado;
        }
        if (f.interes === '1' || f.interes === '0') {
            api.interes_declarado = f.interes;
        }
        return api;
    }

    /** Carga de nuevo los datos cuando cambian los filtros o el texto de búsqueda. */
    function recargarConFiltros() {
        var consulta = ++estado.consulta;
        mostrar(dom.carga);
        ocultar(dom.error);
        ocultar(dom.vacio);

        var apiFiltros = filtrosParaApi(estado.filtros);

        var carga = estado.origen === 'ejemplo'
            ? Promise.resolve({ lista: estado.todos, origen: 'ejemplo', detalle: '' })
            : datosApi.cargarConRespaldo(config.api, config.datos, apiFiltros);
        carga
            .then(function (resultado) {
                if (consulta !== estado.consulta) {
                    return;
                }
                mostrarOrigen(resultado);

                var lista = resultado.origen === 'ejemplo' ? resultado.lista.filter(function (item) {
                    var texto = plegar([item.nombre, item.parroquia, item.sector, item.direccion].join(' '));
                    return (!apiFiltros.q || texto.includes(plegar(apiFiltros.q))) &&
                        !apiFiltros.categoria_id && !apiFiltros.distancia_km &&
                        (!apiFiltros.tipo || item.tipo === apiFiltros.tipo) &&
                        (!apiFiltros.parroquia || item.parroquia === apiFiltros.parroquia) &&
                        (!apiFiltros.recepcionProveedores || item.estadoClave === apiFiltros.recepcionProveedores) &&
                        (!apiFiltros.interes_declarado || item.interesDeclarado === (apiFiltros.interes_declarado === '1'));
                }) : resultado.lista;
                if (estado.filtros.soloUbicados) {
                    lista = lista.filter(function (item) {
                        return item.tieneUbicacion;
                    });
                }

                estado.origenVisible = resultado.origen;
                estado.visibles = lista;
                ordenarVisibles();
                estado.pagina = 1;
                pintarListado();
                actualizarContadores();
                dom.limpiar.hidden = !hayFiltros();
                actualizarVistaMapa();
                ocultar(dom.carga);
            })
            .catch(function (error) {
                if (consulta !== estado.consulta) {
                    return;
                }
                ocultar(dom.carga);
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

        if (resultado.detalle) {
            // El detalle técnico va a la consola, no a la pantalla.
            window.console.warn('Respaldo de datos:', resultado.detalle);
        }

        dom.origen.textContent =
            'Vista previa con ' + resultado.lista.length + ' comercios de ejemplo (ficticios). ' +
            'La API todavía no tiene comercios cargados.';
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
            .sort(function (a, b) {
                return tipos[a].localeCompare(tipos[b], 'es');
            })
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
            }, 180);
        });

        dom.tipo.addEventListener('change', function (evento) {
            estado.filtros.tipo = evento.target.value;
            aplicarFiltros();
        });

        dom.parroquia.addEventListener('change', function (evento) {
            estado.filtros.parroquia = evento.target.value;
            aplicarFiltros();
        });
        dom.categoria.addEventListener('change', function (evento) {
            estado.filtros.categoria = evento.target.value;
            aplicarFiltros();
        });
        dom.buscarCerca.addEventListener('submit', function (evento) {
            evento.preventDefault();
            estado.filtros.cerca = Object.fromEntries(new FormData(dom.buscarCerca));
            dom.orden.value = estado.orden = 'distancia';
            dom.ubicacionAviso.textContent = 'Búsqueda por radio activada.';
            aplicarFiltros();
        });
        dom.usarUbicacion.addEventListener('click', function () {
            if (!navigator.geolocation || !window.isSecureContext) {
                dom.ubicacionAviso.textContent = 'Ingresa las coordenadas de origen para buscar por radio.';
                return;
            }
            navigator.geolocation.getCurrentPosition(function (posicion) {
                dom.buscarCerca.elements.latitud.value = posicion.coords.latitude;
                dom.buscarCerca.elements.longitud.value = posicion.coords.longitude;
                dom.buscarCerca.requestSubmit();
            }, function () {
                dom.ubicacionAviso.textContent = 'No se obtuvo la ubicación. Puedes ingresar las coordenadas manualmente.';
            });
        });

        dom.ubicados.addEventListener('change', function (evento) {
            estado.filtros.soloUbicados = evento.target.checked;
            aplicarFiltros();
        });

        // El select de interés solo existe con PUBLICAR_INTERES_ENCUESTA=true.
        if (dom.interes) {
            dom.interes.addEventListener('change', function (evento) {
                estado.filtros.interes = evento.target.value;
                aplicarFiltros();
            });
        }

        dom.orden.addEventListener('change', function (evento) {
            estado.orden = evento.target.value;
            aplicarFiltros();
        });

        Array.prototype.forEach.call(dom.chips, function (chip) {
            chip.addEventListener('click', function () {
                estado.filtros.estado = chip.dataset.estado || '';
                marcarChipActivo();
                aplicarFiltros();
            });
        });

        dom.limpiar.addEventListener('click', limpiarFiltros);

        dom.mas.addEventListener('click', function () {
            estado.pagina += 1;
            pintarListado();
        });

        dom.botonLista.addEventListener('click', function () {
            activarVista('lista');
        });

        dom.botonMapa.addEventListener('click', function () {
            activarVista('mapa');
        });
        dom.cargarMapa.addEventListener('click', function () {
            if (estado.mapaIntentado || !estado.visibles.some(function (item) { return item.tieneUbicacion; })) {
                return;
            }
            estado.mapaIntentado = true;
            actualizarVistaMapa();
            iniciarMapa(estado.visibles);
        });

        // Ficha: cerrar con el botón, el fondo o Escape.
        dom.fichaCerrar.addEventListener('click', cerrarFicha);
        dom.fichaFondo.addEventListener('click', cerrarFicha);

        document.addEventListener('keydown', function (evento) {
            if (evento.key === 'Escape' && estado.fichaAbierta) {
                cerrarFicha();
                return;
            }
            if (evento.key === 'Tab' && estado.fichaAbierta) {
                mantenerFocoEnFicha(evento);
            }
        });
    }

    function limpiarFiltros() {
        estado.filtros = { texto: '', tipo: '', parroquia: '', categoria: '', estado: '', interes: '', soloUbicados: false, cerca: null };
        dom.buscar.value = '';
        dom.tipo.value = '';
        dom.parroquia.value = '';
        dom.categoria.value = '';
        dom.buscarCerca.reset();
        dom.ubicacionAviso.textContent = '';
        dom.orden.value = estado.orden = 'nombre';
        dom.ubicados.checked = false;
        if (dom.interes) {
            dom.interes.value = '';
        }
        marcarChipActivo();
        aplicarFiltros();
        dom.buscar.focus();
    }

    function marcarChipActivo() {
        Array.prototype.forEach.call(dom.chips, function (chip) {
            var activo = (chip.dataset.estado || '') === estado.filtros.estado;
            chip.classList.toggle('is-activo', activo);
            chip.setAttribute('aria-pressed', activo ? 'true' : 'false');
        });
    }

    function hayFiltros() {
        var f = estado.filtros;
        return Boolean(f.texto.trim() || f.tipo || f.parroquia || f.categoria || f.estado || f.interes || f.soloUbicados || f.cerca);
    }

    function aplicarFiltros() {
        estado.pagina = 1;
        recargarConFiltros();
    }

    function ordenarVisibles() {
        var prioridad = { recibe_propuestas: 0, por_confirmar: 1, no_recibe_por_ahora: 2 };

        estado.visibles.sort(function (a, b) {
            if (estado.orden === 'distancia' && a.distanciaKm !== b.distanciaKm) {
                return (a.distanciaKm ?? Infinity) - (b.distanciaKm ?? Infinity);
            }
            if (estado.orden === 'estado') {
                var pa = prioridad[a.estadoClave];
                var pb = prioridad[b.estadoClave];
                pa = pa === undefined ? 3 : pa;
                pb = pb === undefined ? 3 : pb;
                if (pa !== pb) {
                    return pa - pb;
                }
            }

            if (estado.orden === 'ubicados') {
                if (a.tieneUbicacion !== b.tieneUbicacion) {
                    return a.tieneUbicacion ? -1 : 1;
                }
            }

            if (estado.orden === 'tipo') {
                var ta = a.tipoEtiqueta || '';
                var tb = b.tipoEtiqueta || '';
                if (ta !== tb) {
                    return ta.localeCompare(tb, 'es');
                }
            }

            return a.nombre.localeCompare(b.nombre, 'es');
        });
    }

    function actualizarResumen(lista) {
        dom.resumenTotal.textContent = lista.length;
        dom.resumenReciben.textContent = lista.filter(function (item) {
            return item.estadoClave === 'recibe_propuestas';
        }).length;
        dom.resumenConfirmar.textContent = lista.filter(function (item) {
            return item.estadoClave === 'por_confirmar';
        }).length;
        dom.resumenUbicados.textContent = lista.filter(function (item) {
            return item.tieneUbicacion;
        }).length;
    }

    function actualizarContadores() {
        var total = estado.todos.length;
        var visibles = estado.visibles.length;
        var reciben = estado.visibles.filter(function (item) {
            return item.estadoClave === 'recibe_propuestas';
        }).length;

        dom.contador.textContent = visibles === total
            ? '(' + total + ')'
            : '(' + visibles + ' de ' + total + ')';

        if (visibles === 0) {
            dom.contadorLinea.textContent = 'Sin resultados con estos filtros';
        } else if (visibles === total) {
            dom.contadorLinea.textContent =
                total + (total === 1 ? ' comercio' : ' comercios') +
                ' · ' + reciben + (reciben === 1 ? ' recibe propuestas' : ' reciben propuestas');
        } else {
            dom.contadorLinea.textContent =
                'Mostrando ' + visibles + ' de ' + total + ' · ' +
                reciben + (reciben === 1 ? ' recibe propuestas' : ' reciben propuestas');
        }

        dom.vacio.hidden = visibles !== 0;
    }

    // ------------------------------------------------------------------ esqueleto

    function pintarEsqueletos() {
        dom.listado.textContent = '';

        for (var i = 0; i < 6; i += 1) {
            var li = crear('li', 'esqueleto');
            li.appendChild(crear('div', 'esqueleto__linea esqueleto__linea--titulo'));
            li.appendChild(crear('div', 'esqueleto__linea esqueleto__linea--media'));
            li.appendChild(crear('div', 'esqueleto__linea esqueleto__linea--corta'));
            dom.listado.appendChild(li);
        }
    }

    // ------------------------------------------------------------------- tarjetas

    function pintarListado() {
        dom.listado.textContent = '';

        var limite = estado.pagina * POR_PAGINA;
        var visibles = estado.visibles.slice(0, limite);

        visibles.forEach(function (item) {
            dom.listado.appendChild(tarjeta(item));
        });

        dom.mas.hidden = estado.visibles.length <= limite;
        dom.mas.textContent = 'Mostrar más comercios (' + Math.max(estado.visibles.length - limite, 0) + ')';
    }

    function tarjeta(item) {
        var contenedor = crear('li', 'tarjeta');
        contenedor.dataset.estado = item.estadoClave || 'sin-estado';

        var cabecera = crear('div', 'tarjeta__cabecera');

        var avatar = crear('span', 'tarjeta__avatar ' + claseTipo(item.tipo), iniciales(item.nombre));
        avatar.setAttribute('aria-hidden', 'true');
        cabecera.appendChild(avatar);

        var identidad = crear('div', 'tarjeta__identidad');
        identidad.appendChild(crear('h3', 'tarjeta__nombre', item.nombre));

        var meta = crear('p', 'tarjeta__meta');
        meta.appendChild(crear('span', 'tarjeta__tipo', item.tipoEtiqueta));
        if (item.parroquia) {
            meta.appendChild(crear('span', 'tarjeta__separador', '·'));
            meta.appendChild(crear('span', null, item.parroquia));
        }
        identidad.appendChild(meta);

        cabecera.appendChild(identidad);
        contenedor.appendChild(cabecera);

        var etiquetas = crear('div', 'tarjeta__etiquetas');
        etiquetas.appendChild(etiquetaEstado(item));
        if (item.sector) {
            etiquetas.appendChild(crear('span', 'tarjeta__sector', item.sector));
        }
        if (item.interesDeclarado !== null && item.interesDeclarado !== undefined) {
            var interes = crear(
                'span',
                'tarjeta__interes' + (item.interesDeclarado ? ' tarjeta__interes--si' : ''),
                item.interesDeclarado ? 'Interés: Sí' : 'Interés: No'
            );
            interes.title = 'Interés declarado en la encuesta';
            etiquetas.appendChild(interes);
        }
        contenedor.appendChild(etiquetas);

        var ubicacion = crear('p', 'tarjeta__ubicacion');
        if (item.tieneUbicacion) {
            ubicacion.appendChild(crear('span', null, '◉'));
            ubicacion.appendChild(crear('span', null, 'En el mapa'));
        } else {
            ubicacion.classList.add('tarjeta__ubicacion--pendiente');
            ubicacion.appendChild(crear('span', null, '⚠'));
            ubicacion.appendChild(crear('span', null, 'Pendiente de ubicar'));
        }

        var acciones = crear('div', 'tarjeta__acciones');
        acciones.appendChild(ubicacion);

        var boton = crear('button', 'boton boton--ficha', 'Ver ficha');
        boton.type = 'button';
        boton.setAttribute('aria-label', 'Ver ficha de ' + item.nombre);
        boton.addEventListener('click', function (evento) {
            evento.stopPropagation();
            abrirFicha(item, boton);
        });
        acciones.appendChild(boton);
        contenedor.appendChild(acciones);

        contenedor.addEventListener('click', function () {
            abrirFicha(item, contenedor);
        });

        return contenedor;
    }

    // ---------------------------------------------------------------------- mapa

    function activarVista(vista) {
        estado.vista = vista;
        dom.vistaLista.hidden = vista !== 'lista';
        dom.vistaMapa.hidden = vista !== 'mapa';

        [dom.botonLista, dom.botonMapa].forEach(function (boton, indice) {
            var activo = (indice === 0) === (vista === 'lista');
            boton.classList.toggle('is-activo', activo);
            boton.setAttribute('aria-selected', activo ? 'true' : 'false');
            boton.tabIndex = activo ? 0 : -1;
        });

        actualizarVistaMapa();
    }

    function actualizarVistaMapa() {
        if (estado.vista !== 'mapa') {
            return;
        }

        var ubicados = estado.visibles.some(function (item) { return item.tieneUbicacion; });
        dom.cargarMapa.hidden = estado.mapaIntentado || !config.googleMapsKey || !ubicados;
        actualizarNotaMapa(estado.visibles);
        if (estado.mapaListo && ubicados) {
            ocultar(dom.mapaAviso);
            mostrar(dom.mapa);
            actualizarMarcadores(estado.visibles);
        } else {
            renderAvisoMapa(estado.visibles, estado.mapaError ||
                (!config.googleMapsKey ? 'sin-clave' : (estado.mapaIntentado ? 'cargando' : 'manual')));
        }
    }

    function iniciarMapa(lista) {
        actualizarNotaMapa(lista);

        if (!config.googleMapsKey) {
            estado.mapaError = 'sin-clave';
            renderAvisoMapa(lista, 'sin-clave');
            return;
        }

        // Google llama a esta función cuando la clave no está autorizada.
        window.gm_authFailure = function () {
            estado.mapaListo = false;
            estado.mapaError = 'auth';
            renderAvisoMapa(estado.visibles, 'auth');
        };

        cargarGoogleMaps()
            .then(function () {
                if (estado.mapaError !== 'auth') {
                    crearMapa();
                    actualizarVistaMapa();
                }
            })
            .catch(function () {
                estado.mapaError = estado.mapaError || 'fallo';
                renderAvisoMapa(estado.visibles, estado.mapaError);
            });
    }

    function renderAvisoMapa(lista, motivo) {
        var ubicados = lista.filter(function (item) {
            return item.tieneUbicacion;
        });

        ocultar(dom.mapa);
        mostrar(dom.mapaAviso);

        if (!ubicados.length) {
            dom.mapaAvisoTexto.textContent = 'Ningún comercio de esta selección tiene coordenadas.';
            dom.mapaUbicados.textContent = '';
            return;
        }

        if (motivo === 'manual') {
            dom.mapaAvisoTexto.textContent = 'Pulsa «Mostrar mapa» para ver los comercios en Google Maps.';
        } else if (motivo === 'cargando') {
            dom.mapaAvisoTexto.textContent = 'Cargando Google Maps…';
        } else if (motivo === 'sin-clave') {
            dom.mapaAvisoTexto.textContent =
                'El mapa se activa cuando esté configurada la clave de Google Maps. ' +
                'Mientras tanto, estos son los comercios con coordenadas:';
        } else if (motivo === 'auth') {
            dom.mapaAvisoTexto.textContent =
                'Google Maps no está disponible con la configuración actual. ' +
                'Estos son los comercios con coordenadas:';
        } else {
            dom.mapaAvisoTexto.textContent =
                'No se pudo cargar Google Maps. Estos son los comercios con coordenadas:';
        }

        dom.mapaUbicados.textContent = '';

        ubicados.forEach(function (item) {
            var fila = crear('li', 'mapa__fila');
            fila.appendChild(crear('span', 'mapa__fila-nombre', item.nombre));
            fila.appendChild(
                crear('span', 'mapa__fila-coords', coordinar(item.latitud) + ', ' + coordinar(item.longitud))
            );
            fila.appendChild(
                crear('span', 'mapa__fila-parroquia', item.parroquia || 'Sin parroquia')
            );
            dom.mapaUbicados.appendChild(fila);
        });

        actualizarNotaMapa(lista);
    }

    function actualizarNotaMapa(lista) {
        if (!dom.mapaNota) {
            return;
        }

        var ubicados = lista.filter(function (item) {
            return item.tieneUbicacion;
        });
        var pendientes = lista.length - ubicados.length;

        dom.mapaNota.textContent =
            ubicados.length + ' de ' + lista.length + ' con coordenadas' +
            (pendientes
                ? ' · ' + pendientes + (pendientes === 1 ? ' pendiente' : ' pendientes') + ' de ubicar'
                : '');
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

            // Si en 8 segundos no llamó al callback, asumimos que falló.
            window.setTimeout(function () {
                rechazar(new Error('Tiempo de espera agotado'));
            }, 8000);
        });
    }

    function crearMapa() {
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
        } else if (conCoordenadas.length === 1) {
            estado.mapa.setCenter({ lat: conCoordenadas[0].latitud, lng: conCoordenadas[0].longitud });
            estado.mapa.setZoom(14);
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

    // --------------------------------------------------------------------- ficha

    function abrirFicha(item, origen) {
        if (!dom.ficha) {
            return;
        }

        estado.focoPrevio = origen || document.activeElement;
        estado.fichaAbierta = item.id || item.nombre;

        dom.fichaNombre.textContent = item.nombre;
        dom.fichaMeta.textContent = [item.tipoEtiqueta, item.parroquia].filter(Boolean).join(' · ');
        dom.fichaAvatar.textContent = iniciales(item.nombre);
        dom.fichaAvatar.className = 'ficha__avatar ' + claseTipo(item.tipo);
        dom.fichaCuerpo.textContent = '';
        dom.fichaCuerpo.appendChild(crear('p', 'ficha__cargando', 'Cargando ficha…'));
        dom.fichaAcciones.textContent = '';

        mostrar(dom.fichaFondo);
        mostrar(dom.ficha);
        document.body.classList.add('sin-scroll');
        dom.fichaCerrar.focus();

        var pedirFicha = datosApi.cargarFicha || function (api, registro) {
            return Promise.resolve({ ficha: registro, origen: 'ejemplo' });
        };

        var vieneDeLaApi = estado.origenVisible === 'api';
        pedirFicha(config.apiBase, item, !vieneDeLaApi).then(function (resultado) {
            if (estado.fichaAbierta !== (item.id || item.nombre)) {
                return;
            }
            pintarFicha(resultado.ficha, vieneDeLaApi);
        });
    }

    function pintarFicha(ficha, puedeAbrirFicha) {
        var detalle = ficha.detalle || {};
        dom.fichaCuerpo.textContent = '';

        // 1. Recepción de propuestas
        var seccionEstado = seccionFicha('Recepción de propuestas');
        var cajaEstado = crear('div', 'ficha__estado ficha__estado--' + ficha.estadoTono);
        cajaEstado.appendChild(etiquetaEstado(ficha));
        cajaEstado.appendChild(crear('p', null, ficha.estadoDescripcion || ''));
        seccionEstado.appendChild(cajaEstado);
        if (ficha.interesDeclarado !== null && ficha.interesDeclarado !== undefined) {
            seccionEstado.appendChild(
                crear(
                    'p',
                    'ficha__nota',
                    'Interés declarado en la encuesta: ' + (ficha.interesDeclarado ? 'sí' : 'no') + '.'
                )
            );
        }
        dom.fichaCuerpo.appendChild(seccionEstado);

        // 2. Ubicación y categorías
        var seccionDatos = seccionFicha('Ubicación y categorías');
        var rejillaDatos = crear('div', 'ficha__rejilla');
        rejillaDatos.appendChild(datoFicha('Dirección', detalle.direccion || 'No registrada', true));
        rejillaDatos.appendChild(datoFicha('Sector', detalle.sector || 'No registrado'));
        rejillaDatos.appendChild(datoFicha('Cantón', detalle.canton || 'No registrado'));
        rejillaDatos.appendChild(
            datoFicha('Categorías', chipsFicha(detalle.categorias, 'Sin categoría asignada'), true)
        );
        seccionDatos.appendChild(rejillaDatos);
        dom.fichaCuerpo.appendChild(seccionDatos);

        // 3. Lo que busca el comercio
        var seccionBusca = seccionFicha('Lo que busca este comercio');
        var rejillaBusca = crear('div', 'ficha__rejilla');
        rejillaBusca.appendChild(
            datoFicha('Productos', chipsFicha(detalle.productosBuscados, 'Sin preferencia registrada'), true)
        );
        rejillaBusca.appendChild(datoFicha('Refrigeración', textoBooleano(detalle.tieneRefrigeracion)));
        rejillaBusca.appendChild(
            datoFicha(
                'Pedido inicial',
                detalle.pedidoInicial === null
                    ? 'No definido'
                    : detalle.pedidoInicial + ' unidades'
            )
        );
        rejillaBusca.appendChild(
            datoFicha('Horario para proveedores', detalle.horarioProveedores || 'No definido', true)
        );
        if (detalle.horarioAtencion) {
            rejillaBusca.appendChild(datoFicha('Horario de atención', detalle.horarioAtencion, true));
        }
        rejillaBusca.appendChild(datoFicha('Forma de compra', detalle.modalidadEtiqueta || 'No definida'));
        rejillaBusca.appendChild(datoFicha('Decisión de compra', detalle.decisionEtiqueta || 'No definida'));
        seccionBusca.appendChild(rejillaBusca);
        dom.fichaCuerpo.appendChild(seccionBusca);

        // 4. Condiciones (solo si hay algo)
        if (detalle.requisitos || detalle.devoluciones) {
            var seccionCondiciones = seccionFicha('Condiciones');
            var rejillaCondiciones = crear('div', 'ficha__rejilla');
            if (detalle.requisitos) {
                rejillaCondiciones.appendChild(datoFicha('Requisitos', detalle.requisitos, true));
            }
            if (detalle.devoluciones) {
                rejillaCondiciones.appendChild(datoFicha('Devoluciones', detalle.devoluciones, true));
            }
            seccionCondiciones.appendChild(rejillaCondiciones);
            dom.fichaCuerpo.appendChild(seccionCondiciones);
        }

        // 5. Contacto autorizado
        var seccionContacto = seccionFicha('Contacto');
        if (detalle.contacto) {
            seccionContacto.appendChild(contactoFicha(detalle.contacto));
        } else {
            seccionContacto.appendChild(
                crear(
                    'p',
                    'ficha__nota',
                    'El comercio todavía no autoriza mostrar sus datos de contacto. ' +
                        'Puedes visitarlo con la dirección de esta ficha.'
                )
            );
        }
        dom.fichaCuerpo.appendChild(seccionContacto);

        // 6. Verificación
        if (detalle.verificadoEn) {
            var verificado = crear('p', 'ficha__verificado');
            verificado.appendChild(crear('span', null, '✓'));
            verificado.appendChild(crear('span', null, 'Ficha verificada el ' + fechaLegible(detalle.verificadoEn)));
            dom.fichaCuerpo.appendChild(verificado);
        }

        pintarAccionesFicha(ficha, puedeAbrirFicha);
    }

    function pintarAccionesFicha(ficha, puedeAbrirFicha) {
        var detalle = ficha.detalle || {};
        dom.fichaAcciones.textContent = '';

        if (puedeAbrirFicha && ficha.id) {
            var fichaCompleta = crear('a', 'boton', 'Ver ficha completa');
            fichaCompleta.href = config.fichas + '/' + encodeURIComponent(ficha.id);
            dom.fichaAcciones.appendChild(fichaCompleta);
        }

        if (ficha.tieneUbicacion) {
            var enlace = document.createElement('a');
            enlace.className = 'boton';
            // La API ya entrega la URL armada; si no viene, se arma con las coordenadas.
            enlace.href =
                detalle.comoLlegarUrl ||
                'https://www.google.com/maps/dir/?api=1&destination=' +
                    encodeURIComponent(ficha.latitud + ',' + ficha.longitud);
            enlace.target = '_blank';
            enlace.rel = 'noopener noreferrer';
            enlace.textContent = 'Cómo llegar';
            dom.fichaAcciones.appendChild(enlace);
        } else if (detalle.direccion) {
            var buscar = document.createElement('a');
            buscar.className = 'boton';
            buscar.href =
                'https://www.google.com/maps/search/?api=1&query=' +
                encodeURIComponent(detalle.direccion + ', ' + (detalle.canton || 'Latacunga'));
            buscar.target = '_blank';
            buscar.rel = 'noopener noreferrer';
            buscar.textContent = 'Buscar dirección';
            dom.fichaAcciones.appendChild(buscar);
        }
    }

    function seccionFicha(titulo) {
        var seccion = crear('section', 'ficha__seccion');
        seccion.appendChild(crear('h3', 'ficha__titulo', titulo));
        return seccion;
    }

    function datoFicha(etiqueta, valor, ancho) {
        var dato = crear('div', 'ficha__dato' + (ancho ? ' ficha__dato--ancho' : ''));
        dato.appendChild(crear('p', 'ficha__etiqueta', etiqueta));

        if (valor instanceof Node) {
            dato.appendChild(valor);
        } else {
            dato.appendChild(crear('p', 'ficha__valor', valor));
        }

        return dato;
    }

    function chipsFicha(valores, vacio) {
        if (!valores || !valores.length) {
            return crear('p', 'ficha__valor', vacio);
        }

        var contenedor = crear('div', 'ficha__chips');
        valores.forEach(function (valor) {
            contenedor.appendChild(crear('span', 'chip chip--dato', valor));
        });
        return contenedor;
    }

    function contactoFicha(contacto) {
        var rejilla = crear('div', 'ficha__rejilla');

        if (contacto.nombre) {
            rejilla.appendChild(datoFicha('Persona', contacto.nombre, true));
        }

        if (contacto.telefono) {
            var telefono = document.createElement('a');
            telefono.className = 'ficha__valor';
            telefono.href = 'tel:' + contacto.telefono;
            telefono.textContent = contacto.telefono;
            rejilla.appendChild(datoFicha('Teléfono', telefono));
        }

        if (contacto.email) {
            var correo = document.createElement('a');
            correo.className = 'ficha__valor';
            correo.href = 'mailto:' + contacto.email;
            correo.textContent = contacto.email;
            rejilla.appendChild(datoFicha('Correo', correo, true));
        }

        if (contacto.canales && contacto.canales.length) {
            rejilla.appendChild(datoFicha('Canales', chipsFicha(contacto.canales), true));
        }

        return rejilla;
    }

    function textoBooleano(valor) {
        if (valor === true) {
            return 'Sí';
        }
        if (valor === false) {
            return 'No';
        }
        return 'No sabemos';
    }

    function fechaLegible(valor) {
        var partes = String(valor).slice(0, 10).split('-');
        if (partes.length !== 3) {
            return valor;
        }
        return partes[2] + '/' + partes[1] + '/' + partes[0];
    }

    function cerrarFicha() {
        if (!dom.ficha || dom.ficha.hidden) {
            return;
        }

        dom.ficha.hidden = true;
        dom.fichaFondo.hidden = true;
        document.body.classList.remove('sin-scroll');
        estado.fichaAbierta = null;

        if (estado.focoPrevio && typeof estado.focoPrevio.focus === 'function') {
            estado.focoPrevio.focus();
        }
        estado.focoPrevio = null;
    }

    function mantenerFocoEnFicha(evento) {
        var enfocables = dom.ficha.querySelectorAll(
            'a[href], button:not([disabled]), input, select, textarea, [tabindex]:not([tabindex="-1"])'
        );

        if (!enfocables.length) {
            return;
        }

        var primero = enfocables[0];
        var ultimo = enfocables[enfocables.length - 1];

        if (evento.shiftKey && document.activeElement === primero) {
            evento.preventDefault();
            ultimo.focus();
        } else if (!evento.shiftKey && document.activeElement === ultimo) {
            evento.preventDefault();
            primero.focus();
        }
    }

    // ------------------------------------------------------------------- arranque

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciar);
    } else {
        iniciar();
    }
})();
