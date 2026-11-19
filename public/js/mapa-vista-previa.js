(function () {
    'use strict';

    const boton = document.getElementById('mostrar-mapa-ejemplo');
    const panel = document.getElementById('panel-mapa-ejemplo');
    const aviso = document.getElementById('estado-mapa-ejemplo');
    const lienzo = document.getElementById('mapa-ejemplo');
    const centro = { lat: -0.9333, lng: -78.6167 };
    const { googleMapsKey, googleMapsMapId } = lienzo.dataset;
    let puntos = [];
    let mapa;
    let carga;

    function cargarRecurso(etiqueta, atributos) {
        return new Promise(function (resolver, rechazar) {
            const recurso = Object.assign(document.createElement(etiqueta), atributos);
            recurso.onload = resolver;
            recurso.onerror = rechazar;
            document.head.append(recurso);
        });
    }

    function ficha(comercio) {
        const contenido = document.createElement('div');
        const nombre = document.createElement('strong');
        const detalle = document.createElement('p');
        nombre.textContent = comercio.nombre;
        detalle.textContent = [comercio.parroquia, comercio.estadoEtiqueta].filter(Boolean).join(' · ');
        contenido.append(nombre, detalle);
        return contenido;
    }

    async function crearMapaLibre() {
        const base = 'https://unpkg.com/leaflet@1.9.4/dist/';
        await Promise.all([
            cargarRecurso('link', { rel: 'stylesheet', href: base + 'leaflet.css',
                integrity: 'sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=', crossOrigin: 'anonymous' }),
            cargarRecurso('script', { src: base + 'leaflet.js',
                integrity: 'sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=', crossOrigin: 'anonymous' })
        ]);

        const leaflet = window.L;
        const vista = leaflet.map(lienzo, { scrollWheelZoom: false }).setView(centro, 11);
        leaflet.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(vista);
        const marcadores = leaflet.featureGroup().addTo(vista);

        return {
            nombre: 'OpenStreetMap',
            dibujar(comercios) {
                vista.invalidateSize({ pan: false });
                marcadores.clearLayers();
                comercios.forEach(function (comercio) {
                    leaflet.marker([comercio.latitud, comercio.longitud], { title: comercio.nombre, alt: comercio.nombre })
                        .bindPopup(ficha(comercio)).addTo(marcadores);
                });
                if (comercios.length) {
                    vista.fitBounds(marcadores.getBounds(), { padding: [28, 28], maxZoom: 15, animate: false });
                }
            }
        };
    }

    async function crearMapaGoogle() {
        await new Promise(function (resolver, rechazar) {
            const params = new URLSearchParams({ key: googleMapsKey, libraries: 'marker', loading: 'async',
                language: 'es', region: 'EC', callback: 'mapaVistaPreviaListo' });
            const espera = window.setTimeout(rechazar, 8000);
            window.mapaVistaPreviaListo = function () {
                window.clearTimeout(espera);
                resolver();
            };
            window.gm_authFailure = function () {
                window.clearTimeout(espera);
                rechazar();
                if (mapa?.nombre === 'Google Maps') {
                    mapa = null;
                    lienzo.replaceChildren();
                    carga = prepararMapa(crearMapaLibre);
                }
            };
            cargarRecurso('script', { src: 'https://maps.googleapis.com/maps/api/js?' + params, async: true })
                .catch(function () {
                    window.clearTimeout(espera);
                    rechazar();
                });
        });

        const google = window.google.maps;
        const vista = new google.Map(lienzo, { center: centro, zoom: 11, mapId: googleMapsMapId,
            mapTypeControl: false, streetViewControl: false });
        const ventana = new google.InfoWindow();
        let marcadores = [];

        return {
            nombre: 'Google Maps',
            dibujar(comercios) {
                ventana.close();
                marcadores.forEach(marcador => { marcador.map = null; });
                const limites = new google.LatLngBounds();
                marcadores = comercios.map(function (comercio) {
                    const posicion = { lat: comercio.latitud, lng: comercio.longitud };
                    limites.extend(posicion);
                    const marcador = new google.marker.AdvancedMarkerElement({ map: vista, position: posicion, title: comercio.nombre });
                    marcador.addListener('click', function () {
                        ventana.setContent(ficha(comercio));
                        ventana.open({ map: vista, anchor: marcador });
                    });
                    return marcador;
                });
                if (marcadores.length > 1) vista.fitBounds(limites, 28);
                if (marcadores.length === 1) {
                    vista.setCenter(marcadores[0].position);
                    vista.setZoom(15);
                }
            }
        };
    }

    function dibujar() {
        if (panel.hidden || !mapa) return;
        mapa.dibujar(puntos);
        aviso.textContent = puntos.length
            ? puntos.length + (puntos.length === 1 ? ' comercio con coordenadas' : ' comercios con coordenadas') + ' · ' + mapa.nombre
            : 'Ningún comercio de esta selección tiene coordenadas.';
    }

    async function prepararMapa(crearMapa) {
        aviso.textContent = 'Cargando el mapa…';
        try {
            mapa = await crearMapa();
            dibujar();
        } catch {
            aviso.textContent = 'No se pudo cargar el mapa. Puedes seguir consultando las fichas.';
            lienzo.hidden = true;
        }
    }

    boton.addEventListener('click', function () {
        panel.hidden = !panel.hidden;
        boton.setAttribute('aria-expanded', String(!panel.hidden));
        boton.textContent = panel.hidden ? 'Mostrar mapa' : 'Ocultar mapa';
        if (panel.hidden) return;
        carga ??= prepararMapa(async function () {
            if (googleMapsKey && googleMapsMapId) {
                try {
                    return await crearMapaGoogle();
                } catch {
                    lienzo.replaceChildren();
                }
            }
            return crearMapaLibre();
        });
        dibujar();
    });

    window.MapaVistaPrevia = {
        actualizar(comercios) {
            puntos = comercios.filter(comercio => comercio.tieneUbicacion &&
                Math.abs(comercio.latitud) <= 90 && Math.abs(comercio.longitud) <= 180);
            boton.hidden = false;
            boton.disabled = panel.hidden && !puntos.length;
            dibujar();
        }
    };
})();
