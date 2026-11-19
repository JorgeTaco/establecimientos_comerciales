(function () {
    'use strict';

    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const base = '/api/admin';

    async function solicitar(ruta, metodo = 'GET', datos) {
        const headers = { Accept: 'application/json', 'X-CSRF-TOKEN': csrf };
        let body = datos;
        if (datos && !(datos instanceof FormData)) {
            headers['Content-Type'] = 'application/json';
            body = JSON.stringify(datos);
        }
        const respuesta = await fetch(base + ruta, { method: metodo, headers, body });
        const json = await respuesta.json();
        if (!respuesta.ok) {
            throw new Error(Object.values(json.errors || {})[0]?.[0] || json.message || 'No se pudo completar la acción.');
        }
        return json;
    }

    function nodo(tipo, texto) {
        const elemento = document.createElement(tipo);
        if (texto) elemento.textContent = texto;
        return elemento;
    }

    function boton(texto, tipo = 'button') {
        const elemento = nodo('button', texto);
        elemento.type = tipo;
        elemento.className = 'boton boton--secundario';
        return elemento;
    }

    function seleccionar(nombre, opciones, valor) {
        const select = nodo('select');
        select.setAttribute('aria-label', nombre);
        for (const [clave, etiqueta] of opciones) select.add(new Option(etiqueta, clave));
        select.value = valor;
        return select;
    }

    function controlar(id, accion) {
        const estado = document.querySelector('#' + id + ' [data-estado]');
        estado.textContent = '';
        return accion(estado).catch(error => { estado.textContent = error.message; });
    }

    function enviar(formulario, id, accion) {
        formulario.addEventListener('submit', evento => {
            evento.preventDefault();
            const botones = Array.from(formulario.querySelectorAll('button'));
            botones.forEach(b => { b.disabled = true; });
            controlar(id, estado => accion(estado, evento.submitter)).finally(() => {
                botones.forEach(b => { b.disabled = false; });
            });
        });
    }

    function par(seccion, etiqueta, valor) {
        seccion.append(nodo('dt', etiqueta), nodo('dd', String(valor)));
    }

    function cargarIndicadores() {
        return controlar('indicadores', async () => {
            const { indicadores } = await solicitar('/indicadores');
            document.getElementById('indicador-pedidos').textContent = indicadores.emprendedores_con_primer_pedido;
            document.getElementById('indicador-puntos').textContent = indicadores.puntos_venta_activos;
        });
    }

    async function mostrarCategorias() {
        const { categorias } = await solicitar('/categorias');
        const lista = document.querySelector('#categorias [data-lista]');
        lista.replaceChildren();
        for (const categoria of categorias) {
            const formulario = nodo('form');
            formulario.className = 'fila';
            const nombre = nodo('input');
            nombre.setAttribute('aria-label', 'Nombre de ' + categoria.nombre);
            nombre.required = true;
            nombre.maxLength = 255;
            nombre.value = categoria.nombre;
            const activa = seleccionar('Disponibilidad de ' + categoria.nombre,
                [['1', 'Activa'], ['0', 'Inactiva']], categoria.activa ? '1' : '0');
            formulario.append(nombre, activa, boton('Guardar', 'submit'));
            enviar(formulario, 'categorias', async estado => {
                await solicitar('/categorias/' + categoria.id, 'PATCH', { nombre: nombre.value, activa: activa.value === '1' });
                estado.textContent = 'Categoría actualizada.';
                await mostrarCategorias();
            });
            lista.append(formulario);
        }
    }

    function mostrarUsuario(usuario, lista) {
        const formulario = nodo('form');
        formulario.className = 'fila';
        formulario.append(nodo('strong', usuario.name), nodo('span', usuario.email));
        if (usuario.rol === 'administrador') {
            formulario.append(nodo('span', 'Administrador'));
        } else {
            const rol = seleccionar('Rol de ' + usuario.name,
                [['emprendedor', 'Emprendedor'], ['comercio', 'Comercio']], usuario.rol);
            const estadoCuenta = seleccionar('Estado de ' + usuario.name,
                [['0', 'Activa'], ['1', 'Suspendida']], usuario.suspendido_en ? '1' : '0');
            formulario.append(rol, estadoCuenta, boton('Guardar', 'submit'));
            enviar(formulario, 'usuarios', async estado => {
                await solicitar('/usuarios/' + usuario.id, 'PATCH', { rol: rol.value, suspendido: estadoCuenta.value === '1' });
                estado.textContent = 'Usuario actualizado.';
            });
        }
        lista.append(formulario);
    }

    function mostrarReporte(reporte, lista) {
        const detalle = nodo('article');
        const ficha = nodo('a', 'Ver establecimiento #' + reporte.establecimiento_id);
        ficha.href = '/establecimientos/' + encodeURIComponent(reporte.establecimiento_id);
        detalle.append(ficha, nodo('p', reporte.descripcion), nodo('p', 'Estado: ' + reporte.estado));
        if (reporte.resolucion) detalle.append(nodo('p', reporte.resolucion));
        if (reporte.estado === 'pendiente') {
            const formulario = nodo('form');
            formulario.className = 'fila';
            const estadoReporte = seleccionar('Resultado del reporte', [['resuelto', 'Resuelto'], ['descartado', 'Descartado']], 'resuelto');
            const resolucion = nodo('textarea');
            resolucion.setAttribute('aria-label', 'Resolución del reporte');
            resolucion.required = true;
            resolucion.maxLength = 4000;
            formulario.append(estadoReporte, resolucion, boton('Guardar resolución', 'submit'));
            enviar(formulario, 'reportes', async estado => {
                await solicitar('/reportes/' + reporte.id, 'PATCH', { estado: estadoReporte.value, resolucion: resolucion.value });
                estado.textContent = 'Reporte actualizado.';
                await cargarPagina('reportes', mostrarReporte, lista.dataset.cursor);
            });
            detalle.append(formulario);
        }
        lista.append(detalle);
    }

    async function cargarPagina(id, mostrar, cursor = '') {
        const parametros = new URLSearchParams({ por_cursor: '1' });
        if (cursor) parametros.set('cursor', cursor);
        const respuesta = await solicitar('/' + id + '?' + parametros);
        const paginador = respuesta[id];
        const lista = document.querySelector('#' + id + ' [data-lista]');
        const controles = document.querySelector('#' + id + ' [data-paginacion]');
        lista.replaceChildren();
        lista.dataset.cursor = cursor;
        for (const elemento of paginador.data) mostrar(elemento, lista);
        if (!paginador.data.length) lista.append(nodo('p', 'No hay registros.'));
        const anterior = boton('Anterior');
        const siguiente = boton('Siguiente');
        anterior.disabled = !paginador.prev_cursor;
        siguiente.disabled = !paginador.next_cursor;

        function cambiar(destino) {
            anterior.disabled = siguiente.disabled = true;
            controlar(id, () => cargarPagina(id, mostrar, destino)).finally(() => {
                anterior.disabled = !paginador.prev_cursor;
                siguiente.disabled = !paginador.next_cursor;
            });
        }

        anterior.onclick = () => cambiar(paginador.prev_cursor);
        siguiente.onclick = () => cambiar(paginador.next_cursor);
        controles.replaceChildren(anterior, siguiente);
    }

    enviar(document.getElementById('crear-categoria'), 'categorias', async estado => {
        const nombre = document.getElementById('categoria-nombre');
        await solicitar('/categorias', 'POST', { nombre: nombre.value, activa: true });
        nombre.value = '';
        estado.textContent = 'Categoría creada.';
        await mostrarCategorias();
    });

    const formulario = document.getElementById('importar-archivo');
    enviar(formulario, 'importacion', async (estado, pulsado) => {
        const datos = new FormData(formulario);
        datos.set('simular', pulsado?.value || '1');
        estado.textContent = 'Procesando archivo…';
        const resultado = await solicitar('/establecimientos/importar-archivo', 'POST', datos);
        const resumen = document.getElementById('importacion-resumen');
        resumen.replaceChildren();
        par(resumen, 'Total de filas', resultado.resumen.total);
        par(resumen, 'Listas para importar', resultado.resumen.validas_para_importar);
        par(resumen, 'Importadas', resultado.resumen.importadas);
        par(resumen, 'Posibles duplicados', resultado.resumen.posibles_duplicados);
        par(resumen, 'Inválidas', resultado.resumen.invalidas);
        par(resumen, 'Coordenadas a revisar', resultado.resumen.coordenadas_a_revisar);
        par(resumen, 'Para reintentar', resultado.resumen.reintentar);
        par(resumen, 'Errores al guardar', resultado.resumen.errores);

        const pendientes = resultado.filas.filter(fila => !['valida', 'importada'].includes(fila.estado));
        const detalles = document.getElementById('filas-revisar');
        detalles.hidden = pendientes.length === 0;
        detalles.querySelector('ul').replaceChildren(...pendientes.map(fila => nodo('li',
            'Fila ' + fila.fila + ' · ' + fila.estado.replaceAll('_', ' '))));
        estado.textContent = resultado.simulacion ? 'Simulación terminada.' : 'Importación terminada.';
    });

    cargarIndicadores();
    controlar('categorias', mostrarCategorias);
    controlar('usuarios', () => cargarPagina('usuarios', mostrarUsuario));
    controlar('reportes', () => cargarPagina('reportes', mostrarReporte));
})();
