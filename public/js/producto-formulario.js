(function () {
    'use strict';

    const formulario = document.getElementById('formProducto');
    const boton = document.getElementById('btnProducto');
    const estado = document.getElementById('producto-aviso');
    const csrf = document.querySelector('meta[name="csrf-token"]').content;

    async function solicitar(url, metodo, datos) {
        const headers = { Accept: 'application/json', 'X-CSRF-TOKEN': csrf };
        let body = datos;
        if (!(datos instanceof FormData)) {
            headers['Content-Type'] = 'application/json';
            body = JSON.stringify(datos);
        }
        const resultado = await fetch(url, { method: metodo, headers, body });
        const contenido = await resultado.json();
        if (!resultado.ok) {
            throw new Error(Object.values(contenido.errors || {})[0]?.[0] || contenido.message || 'No se pudo guardar el producto.');
        }
        return contenido;
    }

    function lista(valor, separador) {
        return (valor || '').split(separador).map(texto => texto.trim()).filter(Boolean);
    }

    formulario.addEventListener('submit', async evento => {
        evento.preventDefault();
        boton.disabled = true;
        estado.textContent = 'Guardando producto…';
        try {
            const foto = formulario.elements.archivo_foto.files[0];
            if (foto) {
                const datos = new FormData();
                datos.append('foto', foto);
                const subida = await solicitar('/api/fotos', 'POST', datos);
                formulario.elements.foto_url.value = new URL(subida.foto_url, window.location.href).href;
                formulario.elements.archivo_foto.value = '';
            }

            const cuerpo = Object.fromEntries(new FormData(formulario));
            delete cuerpo.archivo_foto;
            cuerpo.zonas_entrega = lista(cuerpo.zonas_entrega, ',');
            cuerpo.documentacion = lista(cuerpo.documentacion, /\r?\n/);
            cuerpo.requiere_refrigeracion = cuerpo.requiere_refrigeracion === '1';

            await solicitar(formulario.dataset.api, formulario.dataset.metodo, cuerpo);
            window.location.assign(formulario.dataset.destino);
        } catch (error) {
            estado.textContent = error.message;
            boton.disabled = false;
        }
    });
})();
