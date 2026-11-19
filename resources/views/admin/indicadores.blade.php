<x-layout titulo="Panel administrativo">
    <h1>Panel administrativo</h1>
    <nav class="fila" aria-label="Secciones">
        <a href="#indicadores">Indicadores</a>
        <a href="#categorias">Categorías</a>
        <a href="#usuarios">Usuarios</a>
        <a href="#reportes">Reportes</a>
        <a href="#importacion">Importación</a>
        <a href="{{ route('comercio.crear') }}">Registrar establecimiento</a>
    </nav>

    <section class="tarjeta" id="indicadores">
        <h2>Indicadores</h2>
        <dl>
            <dt>Emprendedores con primer pedido</dt>
            <dd id="indicador-pedidos">—</dd>
            <dt>Puntos de venta activos</dt>
            <dd id="indicador-puntos">—</dd>
        </dl>
        <p data-estado role="status"></p>
    </section>

    <section class="tarjeta" id="categorias">
        <h2>Categorías</h2>
        <form id="crear-categoria" class="fila">
            <div class="campo">
                <label for="categoria-nombre">Nombre de la categoría</label>
                <input id="categoria-nombre" name="nombre" required maxlength="255">
            </div>
            <button type="submit" class="boton">Crear categoría</button>
        </form>
        <div data-lista></div>
        <p data-estado role="status"></p>
    </section>

    <section class="tarjeta" id="usuarios">
        <h2>Usuarios</h2>
        <div data-lista></div>
        <div class="fila" data-paginacion></div>
        <p data-estado role="status"></p>
    </section>

    <section class="tarjeta" id="reportes">
        <h2>Reportes de establecimientos</h2>
        <div data-lista></div>
        <div class="fila" data-paginacion></div>
        <p data-estado role="status"></p>
    </section>

    <section class="tarjeta" id="importacion">
        <h2>Importar establecimientos</h2>
        <form id="importar-archivo" enctype="multipart/form-data">
            <div class="campo">
                <label for="archivo">Archivo Excel o CSV</label>
                <input id="archivo" name="archivo" type="file" accept=".xlsx,.csv" required>
            </div>
            <div class="fila">
                <button type="submit" name="simular" value="1" class="boton boton--secundario">Simular carga</button>
                <button type="submit" name="simular" value="0" class="boton">Importar filas válidas</button>
            </div>
        </form>
        <dl id="importacion-resumen"></dl>
        <p data-estado role="status"></p>
        <details id="filas-revisar" hidden>
            <summary>Filas pendientes de revisión</summary>
            <ul></ul>
        </details>
    </section>
    <script src="{{ asset('js/administracion.js') }}" defer></script>
</x-layout>
