/*
 * Lectura de datos del listado de establecimientos.
 *
 * Es la única parte del frontend que sabe DE DÓNDE vienen los datos y CÓMO se
 * llaman sus campos. La pantalla (establecimientos.js) solo usa los objetos ya
 * normalizados que devuelve este archivo.
 *
 * Hoy lee el JSON ficticio de datos/ejemplos/establecimientos.json copiado a
 * public/datos/. Cuando Jorge publique GET /api/establecimientos, el contrato es
 * idéntico: {"establecimientos": [...]}. Cambiar de origen es cambiar la URL
 * que recibe cargar().
 */
(function () {
    'use strict';

    // Estados del contrato inicial (docs/contrato-establecimientos.md).
    var ESTADOS = {
        recibe_propuestas: { etiqueta: 'Recibe propuestas', tono: 'positivo' },
        no_recibe_por_ahora: { etiqueta: 'No recibe por ahora', tono: 'negativo' },
        por_confirmar: { etiqueta: 'Por confirmar', tono: 'pendiente' }
    };

    var ESTADO_DESCONOCIDO = { etiqueta: 'Estado sin definir', tono: 'neutro' };

    // Tipos normalizados que trae el ejemplo; cualquier otro se muestra tal cual.
    var TIPOS = {
        tienda: 'Tienda',
        minimercado: 'Minimercado',
        supermercado: 'Supermercado'
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

    function capitalizar(valor) {
        if (!valor) {
            return '';
        }
        return valor.charAt(0).toUpperCase() + valor.slice(1);
    }

    /**
     * Convierte un registro del JSON en el objeto que consume la pantalla.
     * Tolerante a campos faltantes: nunca lanza por un registro incompleto.
     */
    function normalizar(registro) {
        var tipo = texto(registro.tipo);
        var estadoClave = texto(registro.recepcionProveedores);
        var latitud = numero(registro.latitud);
        var longitud = numero(registro.longitud);
        var estado = ESTADOS[estadoClave] || ESTADO_DESCONOCIDO;

        return {
            id: texto(registro.id),
            nombre: texto(registro.nombre) || 'Comercio sin nombre',
            tipo: tipo,
            tipoEtiqueta: TIPOS[tipo] || capitalizar(tipo) || 'Sin tipo',
            parroquia: texto(registro.parroquia),
            latitud: latitud,
            longitud: longitud,
            tieneUbicacion: latitud !== null && longitud !== null,
            estadoClave: estadoClave,
            estadoEtiqueta: estado.etiqueta,
            estadoTono: estado.tono
        };
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

    /**
     * Descarga y normaliza el listado.
     * @param {string} url JSON de ejemplo o endpoint de la API.
     * @returns {Promise<Array>}
     */
    function cargar(url) {
        return fetch(url, { headers: { Accept: 'application/json' } })
            .then(function (respuesta) {
                if (!respuesta.ok) {
                    throw new Error('El servidor respondió ' + respuesta.status);
                }
                return respuesta.json();
            })
            .then(function (cuerpo) {
                return extraerLista(cuerpo).map(normalizar);
            });
    }

    /**
     * Intenta primero la API real. Si no responde, falla o no tiene comercios
     * cargados, usa el JSON ficticio. Nunca inventa datos: informa el origen
     * para que la pantalla pueda avisarlo.
     *
     * @param {string} apiUrl endpoint de la API (GET /api/establecimientos)
     * @param {string} ejemploUrl JSON ficticio de respaldo
     * @returns {Promise<{lista: Array, origen: string, detalle: string}>}
     */
    function cargarConRespaldo(apiUrl, ejemploUrl) {
        var detalle = '';

        return fetch(apiUrl, { headers: { Accept: 'application/json' } })
            .then(function (respuesta) {
                if (!respuesta.ok) {
                    throw new Error('HTTP ' + respuesta.status);
                }
                return respuesta.json();
            })
            .then(function (cuerpo) {
                var lista = extraerLista(cuerpo).map(normalizar);
                if (lista.length === 0) {
                    throw new Error('la API no tiene comercios cargados');
                }
                return { lista: lista, origen: 'api', detalle: '' };
            })
            .catch(function (error) {
                detalle = error.message;
                return cargar(ejemploUrl).then(function (lista) {
                    return { lista: lista, origen: 'ejemplo', detalle: detalle };
                });
            });
    }

    window.DatosEstablecimientos = {
        cargar: cargar,
        cargarConRespaldo: cargarConRespaldo,
        ESTADOS: ESTADOS,
        TIPOS: TIPOS
    };
})();
