<?php

namespace App\Importacion;

use App\Support\DatosEstablecimiento;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Convierte una fila del Excel/CSV en la estructura que consume la API.
 *
 * No guarda nada: solo mapea, normaliza y devuelve avisos y errores para que
 * el comando decida si la fila se puede importar.
 */
class MapeadorEstablecimientos
{
    /**
     * Encabezados exactos después de normalizar (minúsculas, sin acentos ni
     * puntuación). Se complementan con las reglas por fragmento de texto.
     *
     * @var array<string, string>
     */
    private const ALIAS_EXACTOS = [
        'n' => 'numero',
        'no' => 'numero',
        'nro' => 'numero',
        'numero' => 'numero',
        'num' => 'numero',
        'grupo' => 'grupo',
        'nombre' => 'nombre',
        'nombre del establecimiento' => 'nombre',
        'establecimiento' => 'nombre',
        'propietario' => 'propietario',
        'nombre del propietario administrador' => 'propietario',
        'tipo' => 'tipo',
        'tipo de establecimiento' => 'tipo',
        'direccion' => 'direccion',
        'direccion completa' => 'direccion',
        'provincia' => 'provincia',
        'canton' => 'canton',
        'parroquia' => 'parroquia',
        'barrio' => 'sector',
        'sector' => 'sector',
        'barrio o sector' => 'sector',
        'telefono' => 'telefono',
        'telefono de contacto' => 'telefono',
        'correo' => 'correo',
        'correo electronico' => 'correo',
        'email' => 'correo',
        'horario' => 'horario',
        'horario de atencion' => 'horario',
        'clientes dia' => 'clientes_dia',
        'n aprox clientes dia' => 'clientes_dia',
        'comercializa lacteos' => 'comercializa_lacteos',
        'productos lacteos que comercializa' => 'productos_lacteos',
        'marcas que vende actualmente' => 'marcas',
        'vende de productores locales' => 'vende_locales',
        'producto lacteo con mayor demanda' => 'mayor_demanda',
        'volumen promedio compra semanal' => 'volumen_semanal',
        'frecuencia de abastecimiento' => 'frecuencia',
        'principal proveedor' => 'proveedor',
        'cuenta con refrigeracion' => 'refrigeracion',
        'capacidad almacenamiento refrigerado' => 'capacidad_refrigerada',
        'interes en nuevos proveedores' => 'interes_nuevos_proveedores',
        'interes en productos artesanales asociaciones' => 'interes_artesanales',
        'observaciones' => 'observaciones',
        'local propio o arrendado' => 'local',
        'latitud gps' => 'latitud',
        'longitud gps' => 'longitud',
    ];

    /**
     * @var list<string>
     */
    private const CENTINELAS = [
        'no tiene',
        'no dispone',
        'no dispone de correo electronico',
        'no dispone de telefono',
        'ninguno',
        'ninguna',
        'n a',
        'na',
        'sin correo',
        'sin telefono',
        'sin datos',
        '-',
        '--',
    ];

    public function __construct(private DatosEstablecimiento $reglas)
    {
    }

    /**
     * Encuentra la fila que contiene los encabezados. El Excel real trae
     * títulos y subtítulos antes de la fila de encabezados.
     */
    public function detectarFilaEncabezado(array $filas): int
    {
        $mejor = null;
        $mejorPuntaje = 0;

        foreach ($filas as $indice => $fila) {
            $puntaje = 0;

            foreach ($fila as $celda) {
                if ($this->campoDeEncabezado((string) $celda) !== null) {
                    $puntaje++;
                }
            }

            if ($puntaje > $mejorPuntaje) {
                $mejor = $indice;
                $mejorPuntaje = $puntaje;
            }
        }

        if ($mejor === null || $mejorPuntaje < 3) {
            throw new RuntimeException('No se encontró una fila de encabezado reconocible. Usa --fila-encabezado=N.');
        }

        return $mejor;
    }

    /**
     * @param  list<string>  $encabezados
     * @return array<string, int>
     */
    public function mapa(array $encabezados): array
    {
        $mapa = [];

        foreach ($encabezados as $indice => $titulo) {
            $campo = $this->campoDeEncabezado((string) $titulo);

            // En el Excel real la columna A no tiene encabezado y contiene el grupo.
            if ($campo === null && $indice === 0 && trim((string) $titulo) === '') {
                $campo = 'grupo';
            }

            if ($campo !== null && ! array_key_exists($campo, $mapa)) {
                $mapa[$campo] = $indice;
            }
        }

        return $mapa;
    }

    /**
     * @param  list<string>  $fila
     * @param  array<string, int>  $mapa
     * @return array{
     *     numero: ?string,
     *     nombre: ?string,
     *     datos: array<string, mixed>,
     *     origen: array<string, mixed>,
     *     latitud: ?float,
     *     longitud: ?float,
     *     avisos: list<string>,
     *     errores: array<string, list<string>>
     * }
     */
    public function mapear(array $fila, array $mapa): array
    {
        $leer = fn (string $campo): string => isset($mapa[$campo])
            ? trim((string) ($fila[$mapa[$campo]] ?? ''))
            : '';

        $avisos = [];

        $nombre = $this->texto($leer('nombre'));
        $tipo = $this->normalizarTipo($leer('tipo'), $avisos);
        $parroquia = $this->normalizarParroquia($leer('parroquia'));
        $direccion = $this->normalizarDireccion($leer('direccion'));
        $sector = $this->normalizarDireccion($leer('sector'));
        $canton = $this->titulo($leer('canton'));
        $provincia = $this->titulo($leer('provincia'));
        $telefono = $this->normalizarTelefono($leer('telefono'), $avisos);
        $correo = $this->normalizarCorreo($leer('correo'), $avisos);
        $horario = $this->texto($leer('horario'));
        $productos = $this->lista($leer('productos_lacteos'));
        $comercializa = $this->booleano($leer('comercializa_lacteos'), $avisos, 'Comercializa lácteos');
        $refrigeracion = $this->booleano($leer('refrigeracion'), $avisos, 'Refrigeración');
        $interes = $this->booleano($leer('interes_nuevos_proveedores'), $avisos, 'Interés en nuevos proveedores');
        $latitud = $this->numero($leer('latitud'), $avisos, 'Latitud');
        $longitud = $this->numero($leer('longitud'), $avisos, 'Longitud');

        if ($provincia !== null && $this->normalizarClave($provincia) !== 'cotopaxi') {
            $avisos[] = "La provincia es «{$provincia}»; el piloto está definido para Cotopaxi.";
        }

        $canales = [];

        if ($telefono !== null) {
            $canales[] = 'telefono';
        }

        if ($correo !== null) {
            $canales[] = 'email';
        }

        $perfil = [
            'direccion' => $direccion,
            'canton' => $canton,
            'sector' => $sector,
            'productos_buscados' => $productos,
            'tiene_refrigeracion' => $refrigeracion,
            'contacto_nombre' => $this->texto($leer('propietario')),
            'contacto_telefono' => $telefono,
            'contacto_email' => $correo,
            'canales_contacto' => $canales === [] ? null : $canales,
            'autorizar_contacto' => false,
            'horario_proveedores' => $horario,
        ];

        $perfil = array_filter(
            $perfil,
            fn ($valor, $clave) => $valor !== null || $clave === 'autorizar_contacto',
            ARRAY_FILTER_USE_BOTH
        );

        $datos = [
            'nombre' => $nombre,
            'tipo' => $tipo,
            'parroquia' => $parroquia,
            'recepcion_proveedores' => 'por_confirmar',
            'interes_encuesta' => $interes,
            'perfil' => $perfil,
        ];

        if ($latitud !== null || $longitud !== null) {
            $datos['latitud'] = $latitud;
            $datos['longitud'] = $longitud;
        }

        $origen = [
            'grupo' => $this->texto($leer('grupo')),
            'propietario' => $this->texto($leer('propietario')),
            'provincia' => $provincia,
            'clientes_dia' => $this->texto($leer('clientes_dia')),
            'comercializa_lacteos' => $comercializa,
            'productos_lacteos' => $this->texto($leer('productos_lacteos')),
            'marcas' => $this->texto($leer('marcas')),
            'vende_locales' => $this->texto($leer('vende_locales')),
            'mayor_demanda' => $this->texto($leer('mayor_demanda')),
            'volumen_semanal' => $this->texto($leer('volumen_semanal')),
            'frecuencia' => $this->texto($leer('frecuencia')),
            'proveedor' => $this->texto($leer('proveedor')),
            'capacidad_refrigerada' => $this->texto($leer('capacidad_refrigerada')),
            'interes_artesanales' => $this->texto($leer('interes_artesanales')),
            'observaciones' => $this->texto($leer('observaciones')),
            'local' => $this->texto($leer('local')),
            'latitud_cruda' => $this->texto($leer('latitud')),
            'longitud_cruda' => $this->texto($leer('longitud')),
        ];

        return [
            'numero' => $this->texto($leer('numero')),
            'nombre' => $nombre,
            'datos' => $datos,
            'origen' => $origen,
            'latitud' => $latitud,
            'longitud' => $longitud,
            'avisos' => array_values(array_unique($avisos)),
            'errores' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, list<string>>
     */
    public function validar(array $datos): array
    {
        return Validator::make(
            $datos,
            $this->reglas->reglas(true, importacion: true),
            $this->mensajes(),
            $this->atributos()
        )->errors()->toArray();
    }

    /**
     * @return array<string, string>
     */
    private function atributos(): array
    {
        return [
            'perfil.direccion' => 'dirección',
            'perfil.canton' => 'cantón',
            'perfil.sector' => 'sector',
            'perfil.productos_buscados' => 'productos buscados',
            'perfil.tiene_refrigeracion' => 'refrigeración',
            'perfil.contacto_nombre' => 'nombre de contacto',
            'perfil.contacto_telefono' => 'teléfono de contacto',
            'perfil.contacto_email' => 'correo de contacto',
            'perfil.canales_contacto' => 'canales de contacto',
            'perfil.autorizar_contacto' => 'autorización de contacto',
            'perfil.horario_proveedores' => 'horario para proveedores',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function mensajes(): array
    {
        return [
            'nombre.required' => 'Falta el nombre del establecimiento.',
            'tipo.required' => 'Falta el tipo de establecimiento.',
            'parroquia.required' => 'Falta la parroquia.',
            'required' => 'El campo :attribute es obligatorio.',
            'string' => 'El campo :attribute debe ser texto.',
            'max' => 'El campo :attribute no debe superar :max caracteres.',
            'numeric' => 'El campo :attribute debe ser numérico.',
            'between' => 'El campo :attribute debe estar entre :min y :max.',
            'boolean' => 'El campo :attribute debe ser verdadero o falso.',
            'email' => 'El campo :attribute debe ser un correo válido.',
            'regex' => 'El campo :attribute tiene un formato inválido.',
            'array' => 'El campo :attribute debe ser una lista.',
            'list' => 'El campo :attribute debe ser una lista.',
            'distinct' => 'El campo :attribute tiene valores repetidos.',
            'exists' => 'El valor de :attribute no existe o está inactivo.',
            'in' => 'El valor de :attribute no es válido.',
            'integer' => 'El campo :attribute debe ser un número entero.',
        ];
    }

    public function campoDeEncabezado(string $titulo): ?string
    {
        $clave = $this->normalizarClave($titulo);

        if ($clave === '') {
            return null;
        }

        if (isset(self::ALIAS_EXACTOS[$clave])) {
            return self::ALIAS_EXACTOS[$clave];
        }

        return match (true) {
            str_contains($clave, 'nombre') && str_contains($clave, 'propietario') => 'propietario',
            str_contains($clave, 'nombre') && str_contains($clave, 'establecimiento') => 'nombre',
            str_contains($clave, 'propietario') => 'propietario',
            str_contains($clave, 'tipo') => 'tipo',
            str_contains($clave, 'direccion') => 'direccion',
            str_contains($clave, 'parroquia') => 'parroquia',
            str_contains($clave, 'canton') => 'canton',
            str_contains($clave, 'provincia') => 'provincia',
            str_contains($clave, 'barrio') || str_contains($clave, 'sector') => 'sector',
            str_contains($clave, 'telefono') => 'telefono',
            str_contains($clave, 'correo') || str_contains($clave, 'email') => 'correo',
            str_contains($clave, 'horario') => 'horario',
            str_contains($clave, 'productos') && str_contains($clave, 'lacteo') => 'productos_lacteos',
            str_contains($clave, 'comercializa') && str_contains($clave, 'lacteo') => 'comercializa_lacteos',
            str_contains($clave, 'marcas') => 'marcas',
            str_contains($clave, 'vende') && str_contains($clave, 'local') => 'vende_locales',
            str_contains($clave, 'mayor') && str_contains($clave, 'demanda') => 'mayor_demanda',
            str_contains($clave, 'volumen') => 'volumen_semanal',
            str_contains($clave, 'frecuencia') => 'frecuencia',
            str_contains($clave, 'capacidad') && str_contains($clave, 'refriger') => 'capacidad_refrigerada',
            str_contains($clave, 'refriger') => 'refrigeracion',
            str_contains($clave, 'interes') && str_contains($clave, 'nuevo') => 'interes_nuevos_proveedores',
            str_contains($clave, 'interes') && str_contains($clave, 'artesanal') => 'interes_artesanales',
            str_contains($clave, 'observaciones') => 'observaciones',
            str_contains($clave, 'local') => 'local',
            str_contains($clave, 'latitud') => 'latitud',
            str_contains($clave, 'longitud') => 'longitud',
            str_contains($clave, 'clientes') => 'clientes_dia',
            str_contains($clave, 'proveedor') => 'proveedor',
            $clave === 'n' || str_starts_with($clave, 'n ') || str_contains($clave, 'numero') => 'numero',
            default => null,
        };
    }

    private function normalizarClave(string $valor): string
    {
        $valor = Str::ascii(trim($valor));
        $valor = mb_strtolower($valor, 'UTF-8');
        $valor = preg_replace('/[^a-z0-9]+/u', ' ', $valor) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $valor) ?? '');
    }

    private function texto(string $valor): ?string
    {
        $valor = trim(preg_replace('/\s+/u', ' ', $valor) ?? $valor);

        if ($valor === '' || $this->esCentinela($valor)) {
            return null;
        }

        return $valor;
    }

    private function titulo(string $valor): ?string
    {
        $texto = $this->texto($valor);

        return $texto === null ? null : mb_convert_case($texto, MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * @param  list<string>  $avisos
     */
    private function normalizarTipo(string $valor, array &$avisos): ?string
    {
        $clave = $this->normalizarClave($valor);

        if ($clave === '') {
            return null;
        }

        $tipos = [
            'minimarket' => 'minimercado',
            'mini market' => 'minimercado',
            'minimercado' => 'minimercado',
            'micro mercado' => 'minimercado',
            'micromercado' => 'minimercado',
            'tienda' => 'tienda',
            'tienda de abarrotes' => 'tienda',
            'tienda de barrio' => 'tienda',
            'supermercado' => 'supermercado',
            'super mercado' => 'supermercado',
            'panaderia' => 'panaderia',
            'distribuidor' => 'distribuidor',
            'distribuidora' => 'distribuidor',
            'mayorista' => 'distribuidor',
        ];

        if (isset($tipos[$clave])) {
            return $tipos[$clave];
        }

        $normalizado = Str::slug($clave, '_');

        if ($normalizado === '') {
            return null;
        }

        $avisos[] = "Tipo no reconocido «{$valor}»; se guardó como «{$normalizado}».";

        return $normalizado;
    }

    private function normalizarParroquia(string $valor): ?string
    {
        $clave = $this->normalizarClave($valor);

        if ($clave === '') {
            return null;
        }

        $parroquias = [
            'mulalo' => 'Mulaló',
            'tanicuchi' => 'Tanicuchí',
            'guaytacama' => 'Guaytacama',
            'lasso' => 'Lasso',
            'belisario quevedo' => 'Belisario Quevedo',
            'salache' => 'Salache',
            'toacaso' => 'Toacaso',
            'pastocalle' => 'Pastocalle',
            'latacunga' => 'Latacunga',
            'san felipe' => 'San Felipe',
            'eloy alfaro' => 'Eloy Alfaro',
            'juan montalvo' => 'Juan Montalvo',
            'ignacio flores' => 'Ignacio Flores',
        ];

        if (isset($parroquias[$clave])) {
            return $parroquias[$clave];
        }

        return $this->titulo($valor);
    }

    private function normalizarDireccion(string $valor): ?string
    {
        $texto = $this->texto($valor);

        if ($texto === null) {
            return null;
        }

        return str_ireplace(
            ['mulalo', 'tanicuchi'],
            ['Mulaló', 'Tanicuchí'],
            $texto
        );
    }

    /**
     * @param  list<string>  $avisos
     */
    private function normalizarTelefono(string $valor, array &$avisos): ?string
    {
        if ($this->texto($valor) === null) {
            return null;
        }

        $digitos = preg_replace('/\D/', '', $valor) ?? '';

        if ($digitos === '') {
            $avisos[] = "Teléfono sin dígitos: «{$valor}».";

            return null;
        }

        if (mb_strlen($digitos) < 7 || mb_strlen($digitos) > 15) {
            $avisos[] = "Teléfono con longitud inusual: «{$valor}».";

            return null;
        }

        return str_starts_with(trim($valor), '+') ? "+{$digitos}" : $digitos;
    }

    /**
     * @param  list<string>  $avisos
     */
    private function normalizarCorreo(string $valor, array &$avisos): ?string
    {
        if ($this->texto($valor) === null) {
            return null;
        }

        $correo = mb_strtolower(trim($valor), 'UTF-8');

        // Los correos no admiten tildes ni eñes. En la encuesta casi siempre
        // son errores de digitación, así que se corrigen y se deja constancia.
        if (preg_match('/[^\x20-\x7E]/', $correo) === 1) {
            $correo = mb_strtolower(Str::ascii($correo), 'UTF-8');
            $avisos[] = "Correo con tildes o eñes corregido: «{$valor}» → «{$correo}».";
        }

        $correo = preg_replace('/\s+/', '', $correo) ?? $correo;

        if (! filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $avisos[] = "Correo inválido: «{$valor}».";

            return null;
        }

        return $correo;
    }

    /**
     * @param  list<string>  $avisos
     */
    private function booleano(string $valor, array &$avisos, string $etiqueta): ?bool
    {
        $texto = trim($valor);

        if ($texto === '') {
            return null;
        }

        $clave = $this->normalizarClave($texto);

        if (in_array($clave, ['si', 's', '1', 'true', 'verdadero', 'x', 'yes'], true)) {
            return true;
        }

        if (in_array($clave, ['no', 'n', '0', 'false', 'falso'], true)) {
            return false;
        }

        $avisos[] = "{$etiqueta}: valor no reconocido «{$texto}»; se dejó vacío.";

        return null;
    }

    /**
     * @return list<string>|null
     */
    private function lista(string $valor): ?array
    {
        $texto = $this->texto($valor);

        if ($texto === null) {
            return null;
        }

        $partes = preg_split('/\s*[,;]\s*/u', $texto) ?: [];
        $partes = array_values(array_unique(array_filter(array_map(
            fn (string $parte) => $this->texto($parte),
            $partes
        ))));

        return $partes === [] ? null : array_slice($partes, 0, 30);
    }

    /**
     * @param  list<string>  $avisos
     */
    private function numero(string $valor, array &$avisos, string $etiqueta): ?float
    {
        if ($this->texto($valor) === null) {
            return null;
        }

        $texto = str_replace([' ', ','], ['', '.'], trim($valor));

        if (! is_numeric($texto)) {
            $avisos[] = "{$etiqueta} no numérica: «{$valor}».";

            return null;
        }

        return round((float) $texto, 6);
    }

    private function esCentinela(string $valor): bool
    {
        $clave = $this->normalizarClave($valor);

        if ($clave === '') {
            return true;
        }

        if (in_array($clave, self::CENTINELAS, true)) {
            return true;
        }

        return str_starts_with($clave, 'no dispone')
            || str_starts_with($clave, 'sin correo')
            || str_starts_with($clave, 'sin telefono');
    }
}
