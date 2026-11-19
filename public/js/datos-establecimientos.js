/*
 * Lectura de datos del listado y de la ficha de un establecimiento.
 *
 * Es la única parte del frontend que sabe DE DÓNDE vienen los datos y CÓMO se
 * llaman sus campos. La pantalla (establecimientos.js) solo usa los objetos ya
 * normalizados que devuelve este archivo.
 *
 * Origen real: GET /api/establecimientos/buscar y GET /api/establecimientos/{id}.
 * La vista previa pública consulta su propio JSON ficticio. El contrato de la
 * API usa snake_case en la ficha y camelCase en el
 * listado; aquí se aceptan ambas formas para no romper con ningún origen.
 */
(function () {
    'use strict';

    // Estados del contrato (docs/contrato-establecimientos.md).
    var ESTADOS = {
        recibe_propuestas: {
            etiqueta: 'Recibe propuestas',
            tono: 'positivo',
            descripcion: 'Este comercio acepta propuestas de proveedores.'
        },
        no_recibe_por_ahora: {
            etiqueta: 'No recibe por ahora',
            tono: 'negativo',
            descripcion: 'Por ahora no está comprando a proveedores nuevos.'
        },
        por_confirmar: {
            etiqueta: 'Por confirmar',
            tono: 'pendiente',
            descripcion: 'Aún no hemos verificado si compra a proveedores. Confírmalo antes de visitarlo.'
        }
    };

    var ESTADO_DESCONOCIDO = {
        etiqueta: 'Estado sin definir',
        tono: 'neutro',
        descripcion: 'No hay información sobre la recepción de propuestas.'
    };

    // Tipos normalizados; cualquier otro se muestra capitalizado.
    var TIPOS = {
        tienda: 'Tienda',
        minimercado: 'Minimercado',
        supermercado: 'Supermercado',
        distribuidor: 'Distribuidor',
        panaderia: 'Panadería'
    };

    var MODALIDADES = {
        contado: 'Contado',
        credito: 'Crédito',
        consignacion: 'Consignación'
    };

    var DECISIONES = {
        sucursal: 'Decide la sucursal',
        oficina_central: 'Decide la oficina central'
    };

    var CANALES = {
        telefono: 'Teléfono',
        whatsapp: 'WhatsApp',
        email: 'Correo'
    };

    function texto(valor) {
        return typeof valor === 'string' ? valor.trim() : '';
    }

    function numero(valor) {
        if (valor === null || valor === undefined || valor === '') {
            return null;
        }
        var n = Number(valor);
        return Number.isFinite(n) ? n : null;
    }

    function booleano(valor) {
        if (valor === true || valor === false) {
            return valor;
        }
        if (valor === 1 || valor === '1' || valor === 'true' || valor === 'si' || valor === 'sí') {
            return true;
        }
        if (valor === 0 || valor === '0' || valor === 'false' || valor === 'no') {
            return false;
        }
        return null;
    }

    function capitalizar(valor) {
        if (!valor) {
            return '';
        }
        return valor.charAt(0).toUpperCase() + valor.slice(1);
    }

    function etiquetaTipo(tipo) {
        return Object.hasOwn(TIPOS, tipo) ? TIPOS[tipo] : capitalizar(tipo) || 'Sin tipo';
    }

    function lista(valor) {
        return Array.isArray(valor) ? valor : [];
    }

    function primerValor(objeto) {
        for (var i = 1; i < arguments.length; i += 1) {
            var valor = arguments[i];
            if (valor !== undefined && valor !== null && valor !== '') {
                return valor;
            }
        }
        return null;
    }

    /** Nombre de categoría venga como texto u objeto {id, nombre}. */
    function nombreCategoria(categoria) {
        if (typeof categoria === 'string') {
            return categoria;
        }
        if (categoria && typeof categoria === 'object') {
            return texto(categoria.nombre) || texto(categoria.name);
        }
        return '';
    }

    /**
     * Convierte un registro del listado en el objeto que consume la pantalla.
     * Tolerante a campos faltantes: nunca lanza por un registro incompleto.
     */
    function normalizar(registro) {
        var tipo = texto(registro.tipo);
        var estadoClave = texto(registro.recepcionProveedores) || texto(registro.recepcion_proveedores);
        var latitud = numero(registro.latitud);
        var longitud = numero(registro.longitud);
        var estado = ESTADOS[estadoClave] || ESTADO_DESCONOCIDO;

        return {
            id: texto(registro.id),
            nombre: texto(registro.nombre) || 'Comercio sin nombre',
            tipo: tipo,
            tipoEtiqueta: etiquetaTipo(tipo),
            parroquia: texto(registro.parroquia),
            sector: texto(registro.sector),
            direccion: texto(registro.direccion),
            // Respuesta de la encuesta. Solo llega con PUBLICAR_INTERES_ENCUESTA=true;
            // null significa "sin dato" y la etiqueta se omite.
            interesDeclarado: typeof registro.interesDeclarado === 'boolean' ? registro.interesDeclarado : null,
            distanciaKm: numero(registro.distanciaKm),
            latitud: latitud,
            longitud: longitud,
            tieneUbicacion: latitud !== null && longitud !== null,
            estadoClave: estadoClave,
            estadoEtiqueta: estado.etiqueta,
            estadoTono: estado.tono,
            estadoDescripcion: estado.descripcion,
            verificadoEn: texto(registro.verificado_en) || texto(registro.verificadoEn),
            fotoUrl: texto(registro.foto_url) || texto(registro.fotoUrl),
            // Guarda el registro original para poder construir la ficha si la
            // API no responde (el listado de ejemplo ya trae los campos).
            crudo: registro,
            detalle: null
        };
    }

    /**
     * Completa un registro del listado con los campos de la ficha.
     * Acepta snake_case (API) y camelCase (ejemplo) y `perfil` anidado.
     */
    function normalizarFicha(registro, base) {
        var perfil = registro.perfil && typeof registro.perfil === 'object' ? registro.perfil : {};
        var ficha = normalizar(registro);
        var traeInteres = Object.prototype.hasOwnProperty.call(registro, 'interesDeclarado');

        if (base && base.id) {
            ficha.id = ficha.id || base.id;
            ficha.nombre = ficha.nombre || base.nombre;
            ficha.tipo = ficha.tipo || base.tipo;
            ficha.tipoEtiqueta = ficha.tipoEtiqueta === 'Sin tipo' ? base.tipoEtiqueta : ficha.tipoEtiqueta;
            ficha.parroquia = ficha.parroquia || base.parroquia;
            ficha.latitud = ficha.latitud === null ? base.latitud : ficha.latitud;
            ficha.longitud = ficha.longitud === null ? base.longitud : ficha.longitud;
            ficha.tieneUbicacion = ficha.latitud !== null && ficha.longitud !== null;
            if (!traeInteres && base.interesDeclarado !== null && base.interesDeclarado !== undefined) {
                ficha.interesDeclarado = base.interesDeclarado;
            }
            if (!ficha.estadoClave) {
                ficha.estadoClave = base.estadoClave;
                ficha.estadoEtiqueta = base.estadoEtiqueta;
                ficha.estadoTono = base.estadoTono;
                ficha.estadoDescripcion = base.estadoDescripcion;
            }
        }

        var modalidad = texto(primerValor(null, registro.modalidad_compra, registro.modalidadCompra, perfil.modalidad_compra, perfil.modalidadCompra));
        var decision = texto(primerValor(null, registro.decision_compra, registro.decisionCompra, perfil.decision_compra, perfil.decisionCompra));
        var contacto = registro.contacto && typeof registro.contacto === 'object' ? registro.contacto : null;

        ficha.detalle = {
            direccion: texto(primerValor(null, registro.direccion, perfil.direccion)),
            canton: texto(primerValor(null, registro.canton, perfil.canton)),
            sector: texto(primerValor(null, registro.sector, perfil.sector)),
            categorias: lista(primerValor(null, registro.categorias, perfil.categorias))
                .map(nombreCategoria)
                .filter(Boolean),
            productosBuscados: lista(primerValor(null, registro.productos_buscados, registro.productosBuscados, perfil.productos_buscados, perfil.productosBuscados))
                .map(texto)
                .filter(Boolean),
            tieneRefrigeracion: booleano(primerValor(null, registro.tiene_refrigeracion, registro.tieneRefrigeracion, perfil.tiene_refrigeracion, perfil.tieneRefrigeracion)),
            horarioProveedores: texto(primerValor(null, registro.horario_proveedores, registro.horarioProveedores, perfil.horario_proveedores, perfil.horarioProveedores)),
            horarioAtencion: texto(primerValor(null, registro.horario_atencion, registro.horarioAtencion, perfil.horario_atencion, perfil.horarioAtencion)),
            comoLlegarUrl: texto(primerValor(null, registro.como_llegar_url, registro.comoLlegarUrl)),
            requisitos: texto(primerValor(null, registro.requisitos, perfil.requisitos)),
            devoluciones: texto(primerValor(null, registro.devoluciones, perfil.devoluciones)),
            modalidadCompra: modalidad,
            modalidadEtiqueta: MODALIDADES[modalidad] || '',
            pedidoInicial: numero(primerValor(null, registro.pedido_inicial, registro.pedidoInicial, perfil.pedido_inicial, perfil.pedidoInicial)),
            decisionCompra: decision,
            decisionEtiqueta: DECISIONES[decision] || '',
            verificadoEn: texto(primerValor(null, registro.verificado_en, registro.verificadoEn, perfil.verificado_en, perfil.verificadoEn)),
            actualizadoEn: texto(primerValor(null, registro.actualizado_en, registro.actualizadoEn, perfil.actualizado_en, perfil.actualizadoEn)),
            fotoUrl: texto(primerValor(null, registro.foto_url, registro.fotoUrl, perfil.foto_url, perfil.fotoUrl)),
            contacto: contacto
                ? {
                      nombre: texto(contacto.nombre),
                      telefono: texto(contacto.telefono),
                      email: texto(contacto.email),
                      canales: lista(contacto.canales).map(function (canal) {
                          return CANALES[canal] || capitalizar(texto(canal));
                      })
                  }
                : null
        };

        return ficha;
    }

    function extraerLista(cuerpo) {
        if (Array.isArray(cuerpo)) {
            return cuerpo;
        }
        if (cuerpo && Array.isArray(cuerpo.establecimientos)) {
            return cuerpo.establecimientos;
        }
        return [];
    }

    function extraerFicha(cuerpo) {
        if (cuerpo && cuerpo.establecimiento && typeof cuerpo.establecimiento === 'object') {
            return cuerpo.establecimiento;
        }
        if (cuerpo && typeof cuerpo === 'object' && !Array.isArray(cuerpo)) {
            return cuerpo;
        }
        return null;
    }

    function pedir(url) {
        return fetch(url, { headers: { Accept: 'application/json' } }).then(function (respuesta) {
            if (!respuesta.ok) {
                var error = new Error('El servidor respondió ' + respuesta.status);
                error.status = respuesta.status;
                throw error;
            }
            return respuesta.json();
        });
    }

    /**
     * Descarga y normaliza el listado.
     * @param {string} url JSON de ejemplo o endpoint de la API.
     * @returns {Promise<Array>}
     */
    function cargar(url) {
        return pedir(url).then(function (cuerpo) {
            return extraerLista(cuerpo).map(normalizar);
        });
    }

    /**
     * Construye una query string a partir de un objeto de filtros.
     */
    function queryString(filtros) {
        var params = [];
        for (var clave in filtros) {
            if (!Object.prototype.hasOwnProperty.call(filtros, clave)) {
                continue;
            }
            var valor = filtros[clave];
            if (valor === null || valor === undefined || valor === '') {
                continue;
            }
            params.push(encodeURIComponent(clave) + '=' + encodeURIComponent(String(valor)));
        }
        return params.length ? '?' + params.join('&') : '';
    }

    async function buscar(apiUrl, filtros) {
        var cuerpo = await pedir(apiUrl + queryString({ ...filtros, facetas: 1 }));
        if (!cuerpo || !Array.isArray(cuerpo.establecimientos) ||
            !cuerpo.facetas || !Number.isInteger(cuerpo.totalDirectorio)) {
            throw new Error('El directorio recibió una respuesta inválida.');
        }
        return {
            establecimientos: cuerpo.establecimientos.map(normalizar),
            facetas: cuerpo.facetas,
            totalDirectorio: cuerpo.totalDirectorio
        };
    }

    async function cargarFicha(apiBaseUrl, registro) {
        var url = apiBaseUrl + '/' + encodeURIComponent(registro.id);
        var detalle = extraerFicha(await pedir(url));
        if (!detalle || !detalle.id) {
            throw new Error('No se recibió la ficha.');
        }
        return normalizarFicha(detalle, registro);
    }

    window.DatosEstablecimientos = {
        cargar: cargar,
        buscar: buscar,
        cargarFicha: cargarFicha,
        etiquetaTipo: etiquetaTipo,
        ESTADOS: ESTADOS,
        TIPOS: TIPOS,
        MODALIDADES: MODALIDADES,
        DECISIONES: DECISIONES
    };
})();
