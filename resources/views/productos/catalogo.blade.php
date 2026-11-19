<x-layout titulo="Catálogo de {{ $catalogo['nombre'] }}">
    <h1>Catálogo de {{ $catalogo['nombre'] }}</h1>
    @forelse ($catalogo['productos'] as $producto)
        <article class="tarjeta">
            <h2>{{ $producto['nombre'] }}</h2>
            @if ($producto['foto_url'])
                <img src="{{ $producto['foto_url'] }}" alt="" loading="lazy" width="120" referrerpolicy="no-referrer">
            @endif
            <p>{{ $producto['descripcion'] }}</p>
            <p>{{ $producto['presentacion'] }}</p>
            @if ($producto['precio_comercio'] !== null)
                <p>Precio al comercio USD {{ $producto['precio_comercio'] }}</p>
            @endif
        </article>
    @empty
        <p>Este catálogo aún no tiene productos activos.</p>
    @endforelse
</x-layout>
