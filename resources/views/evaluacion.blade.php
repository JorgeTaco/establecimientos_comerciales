<x-layout titulo="Evaluación del piloto">
    <section class="tarjeta evaluacion" aria-labelledby="titulo-evaluacion">
        <h1 id="titulo-evaluacion">Evaluación del piloto de lácteos</h1>
        <p>Prueba cómo un emprendedor encuentra comercios en Latacunga, compara sus requisitos y da seguimiento a una oportunidad de venta.</p>

        @guest
            <p>Usa el correo y la contraseña que te entregó el equipo.</p>
        @endguest

        <div class="evaluacion__acciones">
            @guest
                <a class="boton" href="{{ route('login', ['evaluacion' => 1]) }}">Entrar con la cuenta de evaluación</a>
            @else
                @if (auth()->user()->rol === 'emprendedor' && auth()->user()->suspendido_en === null)
                    <a class="boton" href="{{ route('productos.crear') }}">Registrar un producto</a>
                @endif
            @endguest
            <a class="boton boton--secundario" href="{{ route('establecimientos.index') }}">Explorar el directorio</a>
        </div>

        <h2>Recorrido de prueba</h2>
        <ol class="evaluacion__pasos">
            <li>Registra un producto en <strong>Mis productos</strong> y consulta sus comercios compatibles.</li>
            <li>Abre una ficha completa para revisar ubicación, refrigeración, pedido inicial y contacto autorizado.</li>
            <li>Guarda la ficha en <strong>Mis oportunidades</strong> y registra el estado y la próxima gestión.</li>
        </ol>
        <p class="evaluacion__nota">Para ver los comercios en Google Maps, abre la pestaña <strong>Mapa</strong> y pulsa <strong>Mostrar mapa</strong> si está habilitado.</p>
    </section>

    @push('styles')
        <style>
            .evaluacion h1 { margin: 0; font: 700 clamp(1.8rem, 5vw, 2.6rem)/1.15 Georgia, serif; }
            .evaluacion h2 { margin: 1.75rem 0 0.75rem; font-size: 1.2rem; }
            .evaluacion__acciones { display: flex; flex-wrap: wrap; gap: 0.75rem; }
            .evaluacion__acciones .boton { text-align: center; }
            .evaluacion__pasos { margin: 0; padding-left: 1.3rem; }
            .evaluacion__pasos li + li { margin-top: 0.55rem; }
            .evaluacion__nota { margin-bottom: 0; color: var(--tinta-suave); }
        </style>
    @endpush
</x-layout>
