<?php

namespace App\Support;

final class FlujoComercial
{
    public const ROLES = ['emprendedor', 'comercio', 'administrador'];

    public const RECEPCION = ['recibe_propuestas', 'no_recibe_por_ahora', 'por_confirmar'];

    public const ESTADOS = [
        'por_contactar', 'contactado', 'muestra_entregada', 'en_negociacion',
        'primer_pedido', 'punto_venta_activo', 'no_concretada',
    ];

    public const RESPUESTAS = ['me_interesa', 'solicito_informacion', 'agendar_visita', 'no_interesado_por_ahora'];
}
