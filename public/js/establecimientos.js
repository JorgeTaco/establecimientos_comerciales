/* Directorio, filtros, fichas y mapa del piloto. */
(function () {
    'use strict';

    var config = window.ESTABLECIMIENTOS || {};
    var datosApi = window.DatosEstablecimientos;

    var POR_PAGINA = 24;

    var dom = {
        filtrosActivos: document.getElementById('filtros-activos'),
        buscar: document.getElementById('buscar'),
        formBusqueda: document.getElementById('form-busqueda'),
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
        quitarFiltros: document.getElementById('quitar-filtros'),
        orden: document.getElementById('orden'),
        contador: document.getElementById('contador'),
        contadorLinea: document.getElementById('contador-linea'),
        listado: document.getElementById('listado'),
        mas: document.getElementById('mas'),
        carga: document.getElementById('estado-carga'),
        vacio: document.getElementById('estado-vacio'),
        error: document.getElementById('estado-error'),
        errorTexto: document.getElementById('estado-error-texto'),
        reintentar: document.getElementById('reintentar'),
        directorioVacio: document.getElementById('directorio-vacio'),
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
        totalDirectorio: 0,
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
        temporizadorBusqueda: null,
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
            mostrarError('No pudimos abrir el directorio. Recarga esta página.');
            ocultar(dom.reintentar);
            return;
        }

        leerPreferencias();
        conectarEventos();
        marcarChipActivo();
        activarVista(estado.vista);
        aplicarFiltros();
    }

    function leerPreferencias() {
        var parametros = new URLSearchParams(window.location.search);
        var filtros = estado.filtros;
        filtros.texto = (parametros.get('q') || '').slice(0, dom.buscar.maxLength);
        filtros.tipo = (parametros.get('tipo') || '').slice(0, 80);
        filtros.parroquia = (parametros.get('parroquia') || '').slice(0, 255);
        var categoria = parametros.get('categoria_id') || '';
        filtros.categoria = /^[1-9][0-9]{0,14}$/.test(categoria) ? categoria : '';
        var recepcion = parametros.get('recepcionProveedores');
        filtros.estado = Array.from(dom.chips).some(chip => chip.dataset.estado === recepcion) ? recepcion : '';
        filtros.soloUbicados = parametros.get('ubicados') === '1';
        if (dom.interes && ['0', '1'].includes(parametros.get('interes_declarado'))) {
            dom.interes.value = filtros.interes = parametros.get('interes_declarado');
        }
        // Las coordenadas permanecen en el historial de esta pestaña.
        filtros.cerca = window.history.state?.directorioCerca ?? null;
        if (filtros.cerca) {
            Object.entries(filtros.cerca).forEach(function ([campo, valor]) {
                dom.buscarCerca.elements[campo].value = valor;
            });
            dom.ubicacionAviso.textContent = 'Búsqueda por radio activada.';
        }
        dom.buscar.value = filtros.texto;
        dom.ubicados.checked = filtros.soloUbicados;
        dom.orden.value = parametros.get('orden') || 'nombre';
        dom.orden.value = estado.orden = dom.orden.value || 'nombre';
        estado.vista = parametros.get('vista') === 'mapa' ? 'mapa' : 'lista';
    }

    function guardarPreferencias() {
        var f = estado.filtros;
        var url = new URL(window.location.href);
        var parametros = {
            q: f.texto.trim(),
            tipo: f.tipo,
            parroquia: f.parroquia,
            categoria_id: f.categoria,
            recepcionProveedores: f.estado,
            interes_declarado: f.interes,
            ubicados: f.soloUbicados ? '1' : '',
            orden: estado.orden === 'nombre' ? '' : estado.orden,
            vista: estado.vista === 'mapa' ? 'mapa' : ''
        };
        Object.entries(parametros).forEach(function ([nombre, valor]) {
            if (valor) url.searchParams.set(nombre, valor);
            else url.searchParams.delete(nombre);
        });
        ['latitud', 'longitud', 'distancia_km'].forEach(nombre => url.searchParams.delete(nombre));
        window.history.replaceState({ ...window.history.state, directorioCerca: f.cerca }, '', url);

        var activos = [f.tipo, f.parroquia, f.categoria, f.interes, f.soloUbicados, f.cerca].filter(Boolean).length;
        dom.filtrosActivos.textContent = activos ? '(' + activos + ')' : '';
        dom.filtrosActivos.hidden = !activos;
        dom.limpiar.hidden = !hayFiltros() && estado.orden === 'nombre';
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
        if (f.soloUbicados) {
            api.ubicados = 1;
        }
        if (f.estado) {
            api.recepcionProveedores = f.estado;
        }
        if (f.interes === '1' || f.interes === '0') {
            api.interes_declarado = f.interes;
        }
        return api;
    }

    async function recargarConFiltros() {
        var consulta = ++estado.consulta;
        var filtros = filtrosParaApi(estado.filtros);

        mostrar(dom.carga);
        ocultar(dom.error);
        ocultar(dom.vacio);
        ocultar(dom.directorioVacio);

        try {
            var respuesta = await datosApi.buscar(config.api, filtros);
            if (consulta !== estado.consulta) return;

            estado.totalDirectorio = respuesta.totalDirectorio;
            estado.visibles = respuesta.establecimientos;
            actualizarFacetas(respuesta.facetas);
            ordenarVisibles();
            estado.pagina = 1;
            pintarListado();
            actualizarContadores();
            actualizarPaneles();
            dom.directorioVacio.hidden = estado.totalDirectorio !== 0;
            dom.vacio.hidden = estado.totalDirectorio === 0 || estado.visibles.length !== 0;
            dom.quitarFiltros.hidden = !hayFiltrosDeResultado();
        } catch (error) {
            if (consulta !== estado.consulta) return;
            estado.visibles = [];
            pintarListado();
            dom.contador.textContent = '';
            dom.contadorLinea.textContent = '';
            actualizarPaneles();
            mostrarError(error.status === 422
                ? 'Revisa los filtros de búsqueda y vuelve a intentar.'
                : 'No pudimos cargar el directorio. Vuelve a intentar.');
        } finally {
            if (consulta === estado.consulta) ocultar(dom.carga);
        }
    }

    function mostrarError(mensaje) {
        dom.errorTexto.textContent = mensaje;
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

    function actualizarFacetas(facetas) {
        var filtros = estado.filtros;
        var tipoEtiqueta = opcion => datosApi.etiquetaTipo(opcion.valor);
        var interesEtiqueta = opcion => opcion.valor === '1' ? 'Sí' : 'No';

        mostrarOpciones(dom.tipo, facetas.tipo, filtros.tipo, tipoEtiqueta, tipoEtiqueta({ valor: filtros.tipo }));
        mostrarOpciones(dom.parroquia, facetas.parroquia, filtros.parroquia, opcion => opcion.valor, filtros.parroquia);
        mostrarOpciones(dom.categoria, facetas.categoria_id, filtros.categoria, opcion => opcion.nombre, 'Categoría seleccionada');
        if (dom.interes) {
            mostrarOpciones(dom.interes, facetas.interes_declarado, filtros.interes, interesEtiqueta, interesEtiqueta({ valor: filtros.interes }));
        }

        var recepcion = new Map(facetas.recepcionProveedores.map(opcion => [opcion.valor, opcion.cantidad]));
        dom.chips.forEach(function (chip) {
            var valor = chip.dataset.estado;
            if (!valor) return;
            var activo = valor === filtros.estado;
            var cantidad = recepcion.get(valor) ?? (activo ? estado.visibles.length : 0);
            chip.hidden = !activo && !cantidad;
            chip.querySelector('[data-cantidad]').textContent = ' (' + cantidad + ')';
        });
    }

    function mostrarOpciones(selector, opciones, seleccion, etiqueta, alternativa) {
        var entradas = opciones.map(opcion => ({
            valor: opcion.valor,
            nombre: etiqueta(opcion),
            cantidad: opcion.cantidad
        })).filter(opcion => opcion.cantidad > 0 || opcion.valor === seleccion);

        if (seleccion && !entradas.some(opcion => opcion.valor === seleccion)) {
            entradas.push({ valor: seleccion, nombre: alternativa, cantidad: estado.visibles.length });
        }

        selector.length = 1;
        entradas.sort((a, b) => a.nombre.localeCompare(b.nombre, 'es')).forEach(function (opcion) {
            selector.add(new Option(opcion.nombre + ' (' + opcion.cantidad + ')', opcion.valor));
        });
        selector.value = seleccion;
        selector.disabled = entradas.length === 0;
    }

    function conectarEventos() {
        dom.buscar.addEventListener('input', function () {
            estado.filtros.texto = dom.buscar.value.slice(0, dom.buscar.maxLength);
            estado.consulta += 1;
            window.clearTimeout(estado.temporizadorBusqueda);
            estado.temporizadorBusqueda = window.setTimeout(aplicarFiltros, 180);
        });

        dom.formBusqueda.addEventListener('submit', function (evento) {
            evento.preventDefault();
            aplicarFiltros();
        });

        dom.reintentar.addEventListener('click', aplicarFiltros);

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
            guardarPreferencias();
            ordenarVisibles();
            pintarListado();
        });

        Array.prototype.forEach.call(dom.chips, function (chip) {
            chip.addEventListener('click', function () {
                estado.filtros.estado = chip.dataset.estado || '';
                marcarChipActivo();
                aplicarFiltros();
            });
        });

        dom.limpiar.addEventListener('click', () => limpiarFiltros(false));
        dom.quitarFiltros.addEventListener('click', () => limpiarFiltros(true));

        dom.mas.addEventListener('click', function () {
            estado.pagina += 1;
            pintarListado();
        });

        [dom.botonLista, dom.botonMapa].forEach(function (boton, indice) {
            boton.addEventListener('click', function () {
                activarVista(indice === 0 ? 'lista' : 'mapa');
                guardarPreferencias();
            });
            boton.addEventListener('keydown', function (evento) {
                var destinos = { ArrowLeft: 1 - indice, ArrowRight: 1 - indice, Home: 0, End: 1 };
                if (!Object.hasOwn(destinos, evento.key)) return;
                evento.preventDefault();
                var destino = [dom.botonLista, dom.botonMapa][destinos[evento.key]];
                destino.click();
                destino.focus();
            });
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

    function limpiarFiltros(conservarBusqueda) {
        var texto = conservarBusqueda ? estado.filtros.texto : '';
        estado.filtros = { texto: texto, tipo: '', parroquia: '', categoria: '', estado: '', interes: '', soloUbicados: false, cerca: null };
        dom.buscar.value = texto;
        dom.tipo.value = '';
        dom.parroquia.value = '';
        dom.categoria.value = '';
        dom.buscarCerca.reset();
        dom.ubicacionAviso.textContent = '';
        if (!conservarBusqueda || estado.orden === 'distancia') {
            dom.orden.value = estado.orden = 'nombre';
        }
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
        return Boolean(estado.filtros.texto.trim()) || hayFiltrosDeResultado();
    }

    function hayFiltrosDeResultado() {
        var f = estado.filtros;
        return Boolean(f.tipo || f.parroquia || f.categoria || f.estado || f.interes || f.soloUbicados || f.cerca);
    }

    function aplicarFiltros() {
        window.clearTimeout(estado.temporizadorBusqueda);
        guardarPreferencias();
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

    function actualizarContadores() {
        var total = estado.totalDirectorio;
        var visibles = estado.visibles.length;
        dom.contador.textContent = visibles === total
            ? '(' + total + ')'
            : '(' + visibles + ' de ' + total + ')';
        if (!visibles) {
            dom.contadorLinea.textContent = '';
            return;
        }
        var reciben = estado.visibles.filter(item => item.estadoClave === 'recibe_propuestas').length;
        dom.contadorLinea.textContent = reciben + (reciben === 1 ? ' recibe propuestas' : ' reciben propuestas');
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

        [dom.botonLista, dom.botonMapa].forEach(function (boton, indice) {
            var activo = (indice === 0) === (vista === 'lista');
            boton.classList.toggle('is-activo', activo);
            boton.setAttribute('aria-selected', activo ? 'true' : 'false');
            boton.tabIndex = activo ? 0 : -1;
        });

        actualizarPaneles();
    }

    function actualizarPaneles() {
        var hayResultados = estado.visibles.length > 0;
        dom.vistaLista.hidden = !hayResultados || estado.vista !== 'lista';
        dom.vistaMapa.hidden = !hayResultados || estado.vista !== 'mapa';
        if (!dom.vistaMapa.hidden) actualizarVistaMapa();
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
            var acciones = crear('div', 'mapa__fila-acciones');
            var ficha = crear('button', 'boton boton--secundario', 'Ver ficha');
            ficha.type = 'button';
            ficha.setAttribute('aria-label', 'Ver ficha de ' + item.nombre);
            ficha.addEventListener('click', () => abrirFicha(item, ficha));
            acciones.append(ficha, enlaceComoLlegar(item, 'boton boton--secundario'));
            fila.append(acciones);
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
        var contenido = crear('div', 'mapa__ficha-marcador');
        var identificacion = [item.tipoEtiqueta, item.parroquia].filter(Boolean).join(' · ');
        var acciones = crear('div', 'mapa__fila-acciones');
        acciones.append(enlaceFichaCompleta(item, '', 'Ver ficha'), enlaceComoLlegar(item));
        contenido.append(crear('strong', null, item.nombre), crear('p', null, identificacion),
            crear('p', null, item.estadoEtiqueta), acciones);
        var ventana = new window.google.maps.InfoWindow({ content: contenido });

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

    // --------------------------------------------------------------------- ficha

    function abrirFicha(item, origen) {
        if (!dom.ficha) {
            return;
        }

        estado.focoPrevio = origen || document.activeElement;
        estado.fichaAbierta = {};

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

        var fichaAbierta = estado.fichaAbierta;
        datosApi.cargarFicha(config.apiBase, item).then(function (ficha) {
            if (estado.fichaAbierta === fichaAbierta) pintarFicha(ficha);
        }).catch(function () {
            if (estado.fichaAbierta !== fichaAbierta) return;
            dom.fichaCuerpo.textContent = 'No pudimos cargar los detalles del comercio.';
            pintarAccionesFicha(item);
        });
    }

    function pintarFicha(ficha) {
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

        pintarAccionesFicha(ficha);
    }

    function enlaceFichaCompleta(ficha, clase, etiqueta) {
        var enlace = crear('a', clase, etiqueta);
        enlace.href = config.fichas + '/' + encodeURIComponent(ficha.id);
        return enlace;
    }

    function enlaceComoLlegar(ficha, clase) {
        var enlace = crear('a', clase, 'Cómo llegar');
        enlace.href = ficha.detalle?.comoLlegarUrl ||
            'https://www.google.com/maps/dir/?api=1&destination=' +
            encodeURIComponent(ficha.latitud + ',' + ficha.longitud);
        enlace.target = '_blank';
        enlace.rel = 'noopener noreferrer';
        return enlace;
    }

    function pintarAccionesFicha(ficha) {
        var detalle = ficha.detalle || {};
        dom.fichaAcciones.textContent = '';

        if (ficha.id) {
            dom.fichaAcciones.appendChild(enlaceFichaCompleta(ficha, 'boton', 'Ver ficha completa'));
        }

        if (ficha.tieneUbicacion) {
            dom.fichaAcciones.appendChild(enlaceComoLlegar(ficha, 'boton'));
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
