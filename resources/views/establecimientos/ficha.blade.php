<x-layout titulo="{{ $establecimiento['nombre'] ?? 'Ficha' }}">
    <div style="display:flex; align-items:center; justify-content:space-between; gap:1rem; margin-bottom:1.5rem; flex-wrap:wrap;">
        <div>
            <h1 style="margin:0; font-size:1.35rem;">{{ $establecimiento['nombre'] ?? 'Comercio' }}</h1>
            <p style="color:var(--tinta-suave); font-size:0.9rem; margin-top:0.25rem;">
                {{ $establecimiento['tipoEtiqueta'] ?? '' }} ·
                {{ $establecimiento['parroquia'] ?? '' }}
            </p>
        </div>
    </div>

    @if ($error ?? null)
        <div class="aviso aviso--error">{{ $error }}</div>
    @endif

    @if (auth()->user()?->rol === 'administrador' && auth()->user()->suspendido_en === null)
        <div class="fila">
            <a href="{{ route('comercio.editar', $establecimiento['id']) }}">Editar ficha y ubicación</a>
            <form method="post" action="{{ route('admin.verificar', $establecimiento['id']) }}">
                @csrf
                <button type="submit" class="boton boton--secundario">Verificar ficha</button>
            </form>
        </div>
    @endif

    <div class="tarjeta">
        @if ($establecimiento['foto_url'])
            <img src="{{ $establecimiento['foto_url'] }}" alt="" loading="lazy" width="160" referrerpolicy="no-referrer">
        @endif
        <!-- Estado de recepción -->
        <div style="margin-bottom:1.5rem;">
            <h2 style="font-size:0.85rem; text-transform:uppercase; letter-spacing:0.08em; color:var(--tinta-suave); margin-bottom:0.5rem;">Recepción de propuestas</h2>
            <div class="ficha__estado ficha__estado--{{ $estadoTono ?? 'neutro' }}" style="padding:0.7rem 0.8rem; border-radius:var(--radio-chico); font-size:0.85rem; line-height:1.35; display:inline-flex; flex-direction:column; gap:0.4rem;">
                <span class="estado estado--{{ $estadoTono ?? 'neutro' }}" style="background:rgba(255,255,255,0.55);">
                    {{ $estadoEtiqueta ?? 'Sin definir' }}
                </span>
                <p style="margin:0;">{{ $estadoDescripcion ?? '' }}</p>
            </div>
        </div>

        <!-- Ubicación y categorías -->
        <div style="margin-bottom:1.5rem;">
            <h2 style="font-size:0.85rem; text-transform:uppercase; letter-spacing:0.08em; color:var(--tinta-suave); margin-bottom:0.5rem;">Ubicación y categorías</h2>
            <div class="ficha__rejilla" style="display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:0.5rem;">
                <div class="ficha__dato" style="padding:0.6rem 0.7rem; background:var(--superficie-suave); border-radius:10px; min-width:0;">
                    <p class="ficha__etiqueta">Dirección</p>
                    <p class="ficha__valor">{{ $establecimiento['direccion'] ?? 'No registrada' }}</p>
                </div>
                <div class="ficha__dato" style="padding:0.6rem 0.7rem; background:var(--superficie-suave); border-radius:10px; min-width:0;">
                    <p class="ficha__etiqueta">Sector</p>
                    <p class="ficha__valor">{{ $establecimiento['sector'] ?? 'No registrado' }}</p>
                </div>
                <div class="ficha__dato" style="padding:0.6rem 0.7rem; background:var(--superficie-suave); border-radius:10px; min-width:0; grid-column:1 / -1;">
                    <p class="ficha__etiqueta">Categorías</p>
                    <div class="ficha__chips" style="display:flex; flex-wrap:wrap; gap:0.35rem; margin-top:0.35rem;">
                        @php
                            $cats = $establecimiento['categorias'] ?? [];
                        @endphp
                        @forelse ($cats as $cat)
                            <span class="chip chip--dato">{{ $cat['nombre'] ?? $cat }}</span>
                        @empty
                            <p class="ficha__valor">Sin categoría asignada</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <!-- Lo que busca el comercio -->
        <div style="margin-bottom:1.5rem;">
            <h2 style="font-size:0.85rem; text-transform:uppercase; letter-spacing:0.08em; color:var(--tinta-suave); margin-bottom:0.5rem;">Lo que busca este comercio</h2>
            <div class="ficha__rejilla" style="display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:0.5rem;">
                <div class="ficha__dato" style="padding:0.6rem 0.7rem; background:var(--superficie-suave); border-radius:10px; min-width:0;">
                    <p class="ficha__etiqueta">Productos</p>
                    <div class="ficha__chips" style="display:flex; flex-wrap:wrap; gap:0.35rem; margin-top:0.35rem;">
                        @php
                            $prods = $establecimiento['productos_buscados'] ?? [];
                        @endphp
                        @forelse ($prods as $prod)
                            <span class="chip chip--dato">{{ $prod }}</span>
                        @empty
                            <p class="ficha__valor">Sin preferencia registrada</p>
                        @endforelse
                    </div>
                </div>
                <div class="ficha__dato" style="padding:0.6rem 0.7rem; background:var(--superficie-suave); border-radius:10px; min-width:0;">
                    <p class="ficha__etiqueta">Refrigeración</p>
                    <p class="ficha__valor">
                        @php
                            $ref = $establecimiento['tiene_refrigeracion'] ?? null;
                        @endphp
                        @if ($ref === true) Sí
                        @elseif ($ref === false) No
                        @else No sabemos
                        @endif
                    </p>
                </div>
                <div class="ficha__dato" style="padding:0.6rem 0.7rem; background:var(--superficie-suave); border-radius:10px; min-width:0;">
                    <p class="ficha__etiqueta">Pedido inicial</p>
                    <p class="ficha__valor">
                        {{ $establecimiento['pedido_inicial'] ?? 'No definido' }}
                        @if ($establecimiento['pedido_inicial'] ?? false) unidades @endif
                    </p>
                </div>
                <div class="ficha__dato" style="padding:0.6rem 0.7rem; background:var(--superficie-suave); border-radius:10px; min-width:0; grid-column:1 / -1;">
                    <p class="ficha__etiqueta">Horario para proveedores</p>
                    <p class="ficha__valor">{{ $establecimiento['horario_proveedores'] ?? 'No definido' }}</p>
                </div>
                <div class="ficha__dato" style="padding:0.6rem 0.7rem; background:var(--superficie-suave); border-radius:10px; min-width:0;">
                    <p class="ficha__etiqueta">Horario de atención</p>
                    <p class="ficha__valor">{{ $establecimiento['horario_atencion'] ?? 'No definido' }}</p>
                </div>
                <div class="ficha__dato" style="padding:0.6rem 0.7rem; background:var(--superficie-suave); border-radius:10px; min-width:0;">
                    <p class="ficha__etiqueta">Forma de compra</p>
                    <p class="ficha__valor">{{ $establecimiento['modalidadEtiqueta'] ?? 'No definida' }}</p>
                </div>
                <div class="ficha__dato" style="padding:0.6rem 0.7rem; background:var(--superficie-suave); border-radius:10px; min-width:0;">
                    <p class="ficha__etiqueta">Decisión de compra</p>
                    <p class="ficha__valor">{{ $establecimiento['decisionEtiqueta'] ?? 'No definida' }}</p>
                </div>
            </div>
        </div>

        <!-- Contacto -->
        @php
            $contacto = $establecimiento['contacto'] ?? null;
        @endphp
        @if ($contacto)
            <div style="margin-bottom:1.5rem;">
                <h2 style="font-size:0.85rem; text-transform:uppercase; letter-spacing:0.08em; color:var(--tinta-suave); margin-bottom:0.5rem;">Contacto autorizado</h2>
                <div class="ficha__rejilla" style="display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:0.5rem;">
                    @if ($contacto['nombre'] ?? null)
                        <div class="ficha__dato" style="padding:0.6rem 0.7rem; background:var(--superficie-suave); border-radius:10px; min-width:0; grid-column:1 / -1;">
                            <p class="ficha__etiqueta">Persona</p>
                            <p class="ficha__valor">{{ $contacto['nombre'] }}</p>
                        </div>
                    @endif
                    @if ($contacto['telefono'] ?? null)
                        <div class="ficha__dato" style="padding:0.6rem 0.7rem; background:var(--superficie-suave); border-radius:10px; min-width:0;">
                            <p class="ficha__etiqueta">Teléfono</p>
                            <a class="ficha__valor" href="tel:{{ $contacto['telefono'] }}">{{ $contacto['telefono'] }}</a>
                        </div>
                    @endif
                    @if ($contacto['email'] ?? null)
                        <div class="ficha__dato" style="padding:0.6rem 0.7rem; background:var(--superficie-suave); border-radius:10px; min-width:0; grid-column:1 / -1;">
                            <p class="ficha__etiqueta">Correo</p>
                            <a class="ficha__valor" href="mailto:{{ $contacto['email'] }}">{{ $contacto['email'] }}</a>
                        </div>
                    @endif
                    @if ($contacto['canales'] ?? null)
                        <div class="ficha__dato" style="padding:0.6rem 0.7rem; background:var(--superficie-suave); border-radius:10px; min-width:0; grid-column:1 / -1;">
                            <p class="ficha__etiqueta">Canales</p>
                            <div class="ficha__chips" style="display:flex; flex-wrap:wrap; gap:0.35rem; margin-top:0.35rem;">
                                @forelse ($contacto['canales'] as $canal)
                                    <span class="chip chip--dato">{{ $canal }}</span>
                                @empty
                                @endforelse
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        @else
            <div style="margin-bottom:1.5rem;">
                <h2 style="font-size:0.85rem; text-transform:uppercase; letter-spacing:0.08em; color:var(--tinta-suave); margin-bottom:0.5rem;">Contacto</h2>
                <p style="color:var(--tinta-suave); font-size:0.9rem;">
                    El comercio todavía no autoriza mostrar sus datos de contacto. Puedes visitarlo con la dirección de esta ficha.
                </p>
            </div>
        @endif

        @if ($establecimiento['verificado_en'] ?? null)
            <div style="display:inline-flex; align-items:center; gap:0.35rem; font-size:0.78rem; font-weight:600; color:var(--verde);">
                <span>✓</span>
                <span>Ficha verificada el {{ $establecimiento['verificado_en'] }}</span>
            </div>
        @endif
    </div>

    <!-- Cómo llegar -->
    @if ($establecimiento['como_llegar_url'] ?? null)
        <div style="margin-top:1.5rem;">
            <a href="{{ $establecimiento['como_llegar_url'] }}" class="boton" target="_blank" rel="noopener noreferrer">Cómo llegar</a>
        </div>
    @endif

    <!-- Crear oportunidad (solo si autenticado como emprendedor) -->
    @auth
        @if (auth()->user()->rol === 'emprendedor')
            <div style="margin-top:1.5rem;">
                <h2 style="font-size:0.85rem; text-transform:uppercase; letter-spacing:0.08em; color:var(--tinta-suave); margin-bottom:0.5rem;">Contactar a este comercio</h2>
                <p style="color:var(--tinta-suave); font-size:0.9rem; margin-bottom:1rem;">
                    Crea una oportunidad para hacerle seguimiento a este comercio y enviarle propuestas de tus productos.
                </p>
                <div class="fila">
                    <form method="post" action="{{ route('oportunidades.store') }}">
                        @csrf
                        <input type="hidden" name="establecimiento_id" value="{{ $establecimiento['id'] }}">
                        <button type="submit" class="boton">Guardar oportunidad</button>
                    </form>
                    @if ($establecimiento['recepcionProveedores'] !== 'no_recibe_por_ahora')
                        <a href="{{ route('oportunidades.crear', $establecimiento['id']) }}" class="boton boton--secundario">Enviar propuesta</a>
                    @endif
                </div>
            </div>
        @endif
        <details class="tarjeta">
            <summary>Reportar información incorrecta</summary>
            <form method="post" action="{{ route('establecimientos.reportar', $establecimiento['id']) }}">
                @csrf
                <label for="descripcion-reporte">¿Qué debemos corregir?</label>
                <textarea id="descripcion-reporte" name="descripcion" required maxlength="4000">{{ old('descripcion') }}</textarea>
                @error('descripcion') <p role="alert">{{ $message }}</p> @enderror
                <button type="submit" class="boton">Enviar reporte</button>
            </form>
        </details>
    @endauth
</x-layout>
