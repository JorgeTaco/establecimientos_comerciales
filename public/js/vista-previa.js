(function () {
    'use strict';

    var listado = document.getElementById('comercios-ejemplo');
    var plantilla = document.getElementById('comercio-ejemplo');
    var estado = document.getElementById('estado-vista-previa');
    var archivo = 'public/datos/establecimientos.ejemplo.json';

    function agregarCampo(ficha, etiqueta, valor) {
        var grupo = document.createElement('div');
        var titulo = document.createElement('dt');
        var dato = document.createElement('dd');
        titulo.className = 'ficha__etiqueta';
        titulo.textContent = etiqueta;
        dato.className = 'ficha__valor';
        dato.textContent = valor || 'Sin registrar';
        grupo.append(titulo, dato);
        ficha.append(grupo);
    }

    window.DatosEstablecimientos.cargar(archivo).then(function (comercios) {
        comercios.forEach(function (comercio) {
            var tarjeta = plantilla.content.cloneNode(true);
            var perfil = comercio.crudo;
            var etiqueta = tarjeta.querySelector('.estado');
            tarjeta.querySelector('h3').textContent = comercio.nombre;
            tarjeta.querySelector('.tarjeta__meta').textContent = [comercio.tipoEtiqueta, comercio.parroquia].filter(Boolean).join(' · ');
            etiqueta.textContent = comercio.estadoEtiqueta;
            etiqueta.classList.add('estado--' + comercio.estadoTono);

            var ficha = tarjeta.querySelector('dl');
            agregarCampo(ficha, 'Dirección', comercio.direccion);
            agregarCampo(ficha, 'Horario para proveedores', perfil.horario_proveedores);
            agregarCampo(ficha, 'Productos buscados', (perfil.productos_buscados || []).join(', '));
            agregarCampo(ficha, 'Pedido inicial', perfil.pedido_inicial ? perfil.pedido_inicial + ' unidades' : '');
            listado.append(tarjeta);
        });
        estado.hidden = true;
    }).catch(function () {
        estado.textContent = 'No se pudieron cargar los comercios de ejemplo.';
    });
})();
