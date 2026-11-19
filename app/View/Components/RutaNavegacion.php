<?php

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\View\Component;

class RutaNavegacion extends Component
{
    private const FILTROS_DIRECTORIO = ['q', 'tipo', 'parroquia', 'categoria_id', 'recepcionProveedores', 'interes_declarado', 'ubicados', 'orden', 'vista'];

    /** @var list<array{nombre: string, url: string}> */
    public array $padres;

    public function __construct(public ?string $titulo = null)
    {
        $solicitud = request();
        $ruta = $solicitud->route()?->getName();
        $directorio = $this->enlace('Directorio', 'establecimientos.index', $this->filtrosAnteriores($solicitud));

        $seccion = match ($ruta) {
            'login' => $solicitud->boolean('evaluacion') || old('evaluacion')
                ? [$this->enlace('Evaluación', 'evaluacion')]
                : [],
            'productos.crear', 'productos.show' => [$this->enlace('Mis productos', 'productos.index')],
            'productos.edit', 'productos.compatibles' => [
                $this->enlace('Mis productos', 'productos.index'),
                $this->enlace($solicitud->route('producto')->nombre, 'productos.show', $solicitud->route('producto')),
            ],
            'oportunidades.show' => [$this->enlace('Mis oportunidades', 'oportunidades.index')],
            'oportunidades.crear' => $this->origenPropuesta($solicitud),
            'propuestas.responder' => [$this->enlace('Propuestas recibidas', 'propuestas.recibidas')],
            'comercio.crear', 'comercio.editar' => $this->origenFormularioComercio($solicitud),
            'establecimientos.ficha' => $this->origenFicha($solicitud),
            'admin.encuestas.index' => [$this->enlace('Panel administrativo', 'admin.indicadores')],
            'admin.encuestas.show' => [$this->enlace('Encuestas importadas', 'admin.encuestas.index')],
            'admin.encuestas.filas.show' => [
                $this->enlace('Encuestas importadas', 'admin.encuestas.index'),
                $this->enlace('Filas de la encuesta', 'admin.encuestas.show', $solicitud->route('encuesta')),
            ],
            default => [],
        };

        $this->padres = in_array($ruta, ['inicio', 'establecimientos.index'], true)
            ? []
            : [$directorio, ...$seccion];
    }

    private function enlace(string $nombre, string $ruta, mixed $parametros = []): array
    {
        return ['nombre' => $nombre, 'url' => route($ruta, $parametros)];
    }

    private function filtrosAnteriores(Request $solicitud): array
    {
        $anterior = parse_url((string) $solicitud->headers->get('referer'));
        $directorio = parse_url(route('establecimientos.index'));
        $portada = parse_url(route('inicio'), PHP_URL_PATH) ?? '/';
        if (! is_array($anterior)
            || ($anterior['scheme'] ?? null) !== $directorio['scheme']
            || ($anterior['host'] ?? null) !== $directorio['host']
            || ($anterior['port'] ?? null) !== ($directorio['port'] ?? null)
            || ! in_array($anterior['path'] ?? '/', [$directorio['path'], $portada], true)
            || strlen($anterior['query'] ?? '') > 2048) {
            return [];
        }

        parse_str($anterior['query'] ?? '', $parametros);

        return array_filter(array_intersect_key($parametros, array_flip(self::FILTROS_DIRECTORIO)), 'is_string');
    }

    private function origenFicha(Request $solicitud): array
    {
        $usuario = $solicitud->user();
        $productoId = $solicitud->query('desde_producto');
        if ($usuario?->rol !== 'emprendedor' || $usuario->suspendido_en !== null
            || ! is_string($productoId) || ! ctype_digit($productoId) || strlen($productoId) > 18) {
            return [];
        }

        $producto = $usuario->productos()->whereNull('archivado_en')->find($productoId);

        return $producto ? [
            $this->enlace('Mis productos', 'productos.index'),
            $this->enlace($producto->nombre, 'productos.show', $producto),
            $this->enlace('Comercios compatibles', 'productos.compatibles', $producto),
        ] : [];
    }

    private function origenPropuesta(Request $solicitud): array
    {
        $establecimiento = $solicitud->route('establecimiento');
        if ($solicitud->boolean('desde_oportunidad')) {
            $oportunidad = $solicitud->user()->oportunidades()
                ->where('establecimiento_id', $establecimiento->getKey())->first();
            if ($oportunidad) {
                return [
                    $this->enlace('Mis oportunidades', 'oportunidades.index'),
                    $this->enlace('Seguimiento', 'oportunidades.show', $oportunidad),
                ];
            }
        }

        return [$this->enlace('Ficha del comercio', 'establecimientos.ficha', $establecimiento)];
    }

    private function origenFormularioComercio(Request $solicitud): array
    {
        if ($solicitud->user()->rol !== 'administrador') {
            return [$this->enlace('Mis establecimientos', 'comercio.establecimientos')];
        }

        $destino = [$this->enlace('Panel administrativo', 'admin.indicadores')];
        if ($solicitud->routeIs('comercio.editar')) {
            $destino[] = $this->enlace('Ficha del comercio', 'establecimientos.ficha', $solicitud->route('establecimiento'));
        }

        return $destino;
    }

    public function render(): View
    {
        return view('components.ruta-navegacion');
    }
}
