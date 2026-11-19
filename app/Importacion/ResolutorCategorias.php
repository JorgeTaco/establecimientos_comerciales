<?php

namespace App\Importacion;

interface ResolutorCategorias
{
    public function resolver(array $nombres): array;
}
