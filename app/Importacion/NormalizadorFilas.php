<?php

namespace App\Importacion;

class NormalizadorFilas
{
    private ResolutorCategorias $categorias;

    private const TIPOS = [
        'tienda' => 'tienda', 'tiendas' => 'tienda', 'abasto' => 'tienda', 'deposito' => 'tienda',
        'minimercado' => 'minimercado', 'minimarket' => 'minimercado',
        'mini_mercado' => 'minimercado', 'mercado' => 'minimercado',
        'supermercado' => 'supermercado', 'super_mercado' => 'supermercado',
    ];

    private const MODALIDADES = [
        'contado' => 'contado', 'efectivo' => 'contado', 'credito' => 'credito',
        'consignacion' => 'consignacion',
    ];

    private const DECISIONES = [
        'sucursal' => 'sucursal', 'oficina_central' => 'oficina_central',
        'central' => 'oficina_central',
    ];

    private const CANALES = [
        'telefono' => 'telefono', 'celular' => 'telefono', 'whatsapp' => 'whatsapp',
        'correo' => 'email', 'email' => 'email', 'correo_electronico' => 'email',
    ];

    private const AUSENCIA = [
        'no_tiene', 'no_dispone', 'no_dispone_de_correo_electronico',
        'no_cuenta_con_correo_electronico', 'ninguno', 'ninguna',
        'n_a', 'na', 'sin_dato', 'sin_datos', 'no_aplica',
    ];

    public function __construct(?ResolutorCategorias $categorias = null)
    {
        $this->categorias = $categorias ?? new ResolutorCategoriasBD;
    }

    public function normalizar(array $valores, int $numero): array
    {
        $errores = [];
        $datos = [];

        foreach (['nombre', 'tipo', 'parroquia'] as $campo) {
            $texto = $this->valor($valores, $campo);
            if ($texto === '') {
                $errores[$campo][] = 'Este campo es obligatorio.';

                continue;
            }
            $datos[$campo] = $campo === 'tipo'
                ? (self::TIPOS[MapaColumnas::clave($texto)] ?? mb_strtolower($texto, 'UTF-8'))
                : $texto;
        }

        [$latitud, $longitud] = $this->coordenadas($valores, $errores);
        $datos['latitud'] = $latitud;
        $datos['longitud'] = $longitud;
        $datos['recepcion_proveedores'] = 'por_confirmar';

        $interes = $this->booleano($this->valor($valores, 'interes_encuesta'));
        if ($interes === null && $this->valor($valores, 'interes_encuesta') !== '') {
            $errores['interes_encuesta'][] = 'Usa Sí, No, 1 o 0.';
        }
        if ($interes !== null) {
            $datos['interes_encuesta'] = $interes;
        }

        $perfil = [
            'direccion' => $this->opcional($valores, 'perfil.direccion') ?: null,
            'canton' => $this->opcional($valores, 'perfil.canton') ?: null,
            'sector' => $this->opcional($valores, 'perfil.sector') ?: null,
            'foto_url' => $this->opcional($valores, 'perfil.foto_url') ?: null,
            'horario_proveedores' => $this->opcional($valores, 'perfil.horario_proveedores') ?: null,
            'requisitos' => $this->opcional($valores, 'perfil.requisitos') ?: null,
            'devoluciones' => $this->opcional($valores, 'perfil.devoluciones') ?: null,
            'contacto_nombre' => $this->opcional($valores, 'perfil.contacto_nombre') ?: null,
            'contacto_email' => $this->opcional($valores, 'perfil.contacto_email') ?: null,
            'contacto_telefono' => $this->telefono($this->opcional($valores, 'perfil.contacto_telefono'), $errores),
        ];

        $productos = $this->lista($this->valor($valores, 'perfil.productos_buscados'));
        if ($productos !== []) {
            $perfil['productos_buscados'] = $productos;
        }

        $refrigeracion = $this->booleano($this->valor($valores, 'perfil.tiene_refrigeracion'));
        if ($refrigeracion === null && $this->valor($valores, 'perfil.tiene_refrigeracion') !== '') {
            $errores['perfil.tiene_refrigeracion'][] = 'Usa Sí, No, 1 o 0.';
        }
        if ($refrigeracion !== null) {
            $perfil['tiene_refrigeracion'] = $refrigeracion;
        }

        $modalidad = MapaColumnas::clave($this->valor($valores, 'perfil.modalidad_compra'));
        if ($modalidad !== '') {
            if (isset(self::MODALIDADES[$modalidad])) {
                $perfil['modalidad_compra'] = self::MODALIDADES[$modalidad];
            } else {
                $errores['perfil.modalidad_compra'][] = 'Usa contado, crédito o consignación.';
            }
        }

        $pedido = $this->valor($valores, 'perfil.pedido_inicial');
        if ($pedido !== '') {
            if (ctype_digit($pedido) && (int) $pedido >= 1 && (int) $pedido <= 1000000000) {
                $perfil['pedido_inicial'] = (int) $pedido;
            } else {
                $errores['perfil.pedido_inicial'][] = 'Debe ser un entero positivo.';
            }
        }

        $decision = MapaColumnas::clave($this->valor($valores, 'perfil.decision_compra'));
        if ($decision !== '' && isset(self::DECISIONES[$decision])) {
            $perfil['decision_compra'] = self::DECISIONES[$decision];
        } elseif ($decision !== '') {
            $errores['perfil.decision_compra'][] = 'Usa sucursal u oficina central.';
        }

        $canales = $this->canales($valores, $perfil, $errores);
        if ($canales !== []) {
            $perfil['canales_contacto'] = $canales;
        }
        $perfil['autorizar_contacto'] = false;
        $datos['perfil'] = $perfil;

        $categorias = $this->lista($this->valor($valores, 'categorias'));
        if ($categorias !== []) {
            $resueltas = $this->categorias->resolver($categorias);
            if ($resueltas['desconocidas'] !== []) {
                $errores['categorias'][] = 'No existen o están inactivas: '.implode(', ', $resueltas['desconocidas']);
            }
            if ($resueltas['ids'] !== []) {
                $datos['categorias'] = $resueltas['ids'];
            }
        }

        return ['fila' => $numero, 'datos' => $datos, 'errores' => $errores];
    }

    private function coordenadas(array $valores, array &$errores): array
    {
        $coordenadas = [];

        foreach (['latitud' => 90.0, 'longitud' => 180.0] as $campo => $limite) {
            $texto = $this->valor($valores, $campo);
            if ($texto === '') {
                $coordenadas[$campo] = null;

                continue;
            }

            $numero = str_replace([' ', ','], ['', '.'], $texto);
            if (! is_numeric($numero)) {
                $errores[$campo][] = 'Debe ser un número decimal.';
                $coordenadas[$campo] = null;

                continue;
            }

            $numero = (float) $numero;
            if ($numero < -$limite || $numero > $limite) {
                $errores[$campo][] = "Debe estar entre -{$limite} y {$limite}.";
                $coordenadas[$campo] = null;

                continue;
            }

            $coordenadas[$campo] = round($numero, 6);
        }

        if (($coordenadas['latitud'] === null) !== ($coordenadas['longitud'] === null)
            && ! isset($errores['latitud']) && ! isset($errores['longitud'])) {
            $errores['latitud'][] = 'Indica las dos coordenadas o deja ambas vacías.';
        }

        return [$coordenadas['latitud'], $coordenadas['longitud']];
    }

    private function canales(array $valores, array $perfil, array &$errores): array
    {
        $texto = $this->opcional($valores, 'perfil.canales_contacto');
        $canales = [];

        if ($texto !== '') {
            foreach ($this->lista($texto) as $canal) {
                $clave = MapaColumnas::clave($canal);
                if (! isset(self::CANALES[$clave])) {
                    $errores['perfil.canales_contacto'][] = "Canal no reconocido: {$canal}.";

                    continue;
                }
                $canales[] = self::CANALES[$clave];
            }
        } else {
            if ($perfil['contacto_telefono'] !== null) {
                $canales[] = 'telefono';
            }
            if ($perfil['contacto_email'] !== null) {
                $canales[] = 'email';
            }
        }

        $canales = array_values(array_unique($canales));
        if (count($canales) > 3) {
            $errores['perfil.canales_contacto'][] = 'Como máximo tres canales.';

            return [];
        }

        if (array_intersect($canales, ['telefono', 'whatsapp']) !== [] && $perfil['contacto_telefono'] === null) {
            $errores['perfil.canales_contacto'][] = 'Falta el teléfono para los canales indicados.';
        }
        if (in_array('email', $canales, true) && $perfil['contacto_email'] === null) {
            $errores['perfil.canales_contacto'][] = 'Falta el correo para el canal indicado.';
        }

        return $canales;
    }

    private function telefono(string $texto, array &$errores): ?string
    {
        if ($texto === '') {
            return null;
        }

        $telefono = preg_replace('/[ ()-]/', '', $texto) ?? $texto;
        if (! preg_match('/\A\+?[0-9]{7,39}\z/', $telefono)) {
            $errores['perfil.contacto_telefono'][] = 'Usa entre 7 y 39 dígitos, con prefijo opcional +.';

            return null;
        }

        return $telefono;
    }

    private function booleano(string $texto): ?bool
    {
        return match (MapaColumnas::clave($texto)) {
            '1', 'si', 's', 'true', 't', 'yes', 'y', 'afirmativo', 'interesado' => true,
            '0', 'no', 'n', 'false', 'f', 'negativo', 'sin_interes' => false,
            default => null,
        };
    }

    private function lista(string $texto): array
    {
        return array_values(array_unique(array_filter(
            array_map(fn ($parte) => trim($parte), preg_split('/[;|\n]/', $texto) ?: []),
            fn ($parte) => $parte !== ''
        )));
    }

    private function valor(array $valores, string $clave): string
    {
        return trim((string) ($valores[$clave] ?? ''));
    }

    private function opcional(array $valores, string $clave): string
    {
        $texto = $this->valor($valores, $clave);

        return in_array(MapaColumnas::clave($texto), self::AUSENCIA, true) ? '' : $texto;
    }
}
