<?php

namespace App\Importacion;

class MapaColumnas
{
    private const ACENTOS = [
        'á' => 'a', 'à' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'ó' => 'o', 'ò' => 'o', 'ö' => 'o',
        'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'ñ' => 'n', 'ç' => 'c',
    ];

    private const ALIAS = [
        'nombre_del_establecimiento' => 'nombre',
        'nombre_del_propietario_administrador' => 'perfil.contacto_nombre',
        'tipo_de_establecimiento' => 'tipo',
        'direccion_completa' => 'perfil.direccion',
        'barrio_o_sector' => 'perfil.sector',
        'telefono_de_contacto' => 'perfil.contacto_telefono',
        'correo_electronico' => 'perfil.contacto_email',
        'horario_de_atencion' => 'perfil.horario_atencion',
        'horario_atencion' => 'perfil.horario_atencion',
        'perfil_horario_atencion' => 'perfil.horario_atencion',
        'cuenta_con_refrigeracion' => 'perfil.tiene_refrigeracion',
        'productos_lacteos_que_comercializa' => 'productos_lacteos',
        'comercializa_lacteos' => 'comercializa_lacteos',
        'latitud_gps' => 'latitud',
        'longitud_gps' => 'longitud',
        'nombre' => 'nombre',
        'nombre_comercial' => 'nombre',
        'razon_social' => 'nombre',
        'establecimiento' => 'nombre',
        'tipo' => 'tipo',
        'tipo_comercio' => 'tipo',
        'parroquia' => 'parroquia',
        'latitud' => 'latitud',
        'lat' => 'latitud',
        'longitud' => 'longitud',
        'lon' => 'longitud',
        'lng' => 'longitud',
        'interes_encuesta' => 'interes_encuesta',
        'interes_en_recibir_propuestas' => 'interes_encuesta',
        'interes_en_nuevos_proveedores' => 'interes_encuesta',
        'categorias' => 'categorias',
        'categoria' => 'categorias',
        'perfil_direccion' => 'perfil.direccion',
        'direccion' => 'perfil.direccion',
        'perfil_canton' => 'perfil.canton',
        'canton' => 'perfil.canton',
        'perfil_sector' => 'perfil.sector',
        'sector' => 'perfil.sector',
        'perfil_foto_url' => 'perfil.foto_url',
        'foto_url' => 'perfil.foto_url',
        'perfil_productos_buscados' => 'perfil.productos_buscados',
        'productos_buscados' => 'perfil.productos_buscados',
        'productos_de_interes' => 'perfil.productos_buscados',
        'perfil_tiene_refrigeracion' => 'perfil.tiene_refrigeracion',
        'tiene_refrigeracion' => 'perfil.tiene_refrigeracion',
        'refrigeracion' => 'perfil.tiene_refrigeracion',
        'perfil_horario_proveedores' => 'perfil.horario_proveedores',
        'horario_proveedores' => 'perfil.horario_proveedores',
        'horario' => 'perfil.horario_proveedores',
        'perfil_requisitos' => 'perfil.requisitos',
        'requisitos' => 'perfil.requisitos',
        'perfil_modalidad_compra' => 'perfil.modalidad_compra',
        'modalidad_compra' => 'perfil.modalidad_compra',
        'perfil_pedido_inicial' => 'perfil.pedido_inicial',
        'pedido_inicial' => 'perfil.pedido_inicial',
        'perfil_devoluciones' => 'perfil.devoluciones',
        'devoluciones' => 'perfil.devoluciones',
        'perfil_decision_compra' => 'perfil.decision_compra',
        'decision_compra' => 'perfil.decision_compra',
        'perfil_contacto_nombre' => 'perfil.contacto_nombre',
        'contacto_nombre' => 'perfil.contacto_nombre',
        'contacto' => 'perfil.contacto_nombre',
        'perfil_contacto_telefono' => 'perfil.contacto_telefono',
        'contacto_telefono' => 'perfil.contacto_telefono',
        'telefono' => 'perfil.contacto_telefono',
        'celular' => 'perfil.contacto_telefono',
        'perfil_contacto_email' => 'perfil.contacto_email',
        'contacto_email' => 'perfil.contacto_email',
        'correo' => 'perfil.contacto_email',
        'email' => 'perfil.contacto_email',
        'perfil_canales_contacto' => 'perfil.canales_contacto',
        'canales_contacto' => 'perfil.canales_contacto',
    ];

    public static function clave(string $texto): string
    {
        $texto = strtr(mb_strtolower(trim($texto), 'UTF-8'), self::ACENTOS);

        return trim(preg_replace('/[^a-z0-9]+/', '_', $texto) ?? '', '_');
    }

    public static function resolver(string $encabezado): ?string
    {
        $clave = self::clave($encabezado);

        return $clave === '' ? null : (self::ALIAS[$clave] ?? null);
    }

    public static function desdeEncabezados(array $encabezados): array
    {
        $columnas = [];
        $sinMapear = [];

        foreach ($encabezados as $indice => $encabezado) {
            if (self::clave((string) $encabezado) === '') {
                continue;
            }

            $destino = self::resolver((string) $encabezado);
            if ($destino === null) {
                $sinMapear[] = (string) $encabezado;

                continue;
            }
            $columnas[$indice] = $destino;
        }

        return [
            'columnas' => $columnas,
            'sin_mapear' => array_values(array_unique($sinMapear)),
        ];
    }
}
