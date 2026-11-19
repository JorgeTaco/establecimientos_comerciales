<x-layout titulo="Oportunidad #{{ $oportunidad['id'] }}">
    <h1>{{ $oportunidad['establecimiento']['nombre'] }}</h1>
    <p>{{ $oportunidad['establecimiento']['parroquia'] }}</p>
    <div class="fila">
        <a href="{{ route('establecimientos.ficha', $oportunidad['establecimiento_id']) }}">Ver ficha del comercio</a>
        <a href="{{ route('oportunidades.index') }}">Volver a mis oportunidades</a>
    </div>

    <section class="tarjeta">
        <h2>Seguimiento</h2>
        @if ($errors->any())
            <p class="aviso aviso--error" role="alert">{{ $errors->first() }}</p>
        @endif
        <form method="post" action="{{ route('oportunidades.update', $oportunidad['id']) }}">
            @csrf
            @method('PATCH')
            <div class="campo">
                <label for="estado">Estado de la oportunidad</label>
                <select id="estado" name="estado" required>
                    @foreach ($estados as $estado)
                        <option value="{{ $estado }}" @selected(old('estado', $oportunidad['estado']) === $estado)>
                            {{ ucfirst(str_replace('_', ' ', $estado)) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="campo">
                <label for="notas">Notas privadas</label>
                <textarea id="notas" name="notas" maxlength="4000">{{ old('notas', $oportunidad['notas']) }}</textarea>
            </div>
            <div class="campo">
                <label for="proxima-gestion">Próxima gestión</label>
                <input id="proxima-gestion" name="proxima_gestion" type="date" value="{{ old('proxima_gestion', $oportunidad['proxima_gestion']) }}">
            </div>
            <div class="campo">
                <label for="condiciones">Condiciones acordadas</label>
                <textarea id="condiciones" name="condiciones_acordadas" maxlength="4000">{{ old('condiciones_acordadas', $oportunidad['condiciones_acordadas']) }}</textarea>
            </div>
            <button type="submit" class="boton">Guardar seguimiento</button>
        </form>
    </section>

    <section class="tarjeta">
        <h2>Propuestas enviadas</h2>
        <a href="{{ route('oportunidades.crear', $oportunidad['establecimiento_id']) }}">Enviar propuesta</a>
        @forelse ($oportunidad['propuestas'] as $propuesta)
            <article>
                <h3>Propuesta #{{ $propuesta['id'] }}</h3>
                <p>{{ $propuesta['mensaje'] }}</p>
                <ul>
                    @foreach ($propuesta['productos'] as $producto)
                        <li>{{ $producto['nombre'] }}</li>
                    @endforeach
                </ul>
                @if ($propuesta['respondida_en'])
                    <p>{{ ucfirst(str_replace('_', ' ', $propuesta['respuesta'])) }}</p>
                    <p>{{ $propuesta['mensaje_respuesta'] }}</p>
                @else
                    <p>Pendiente de respuesta.</p>
                @endif
            </article>
        @empty
            <p>Todavía no se han enviado propuestas a este comercio.</p>
        @endforelse
    </section>
</x-layout>
