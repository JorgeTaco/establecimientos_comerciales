<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EstablecimientoResource;
use App\Models\Establecimiento;
use Illuminate\Http\JsonResponse;

class EstablecimientoController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $establecimientos = Establecimiento::query()
            ->orderBy('nombre')
            ->orderBy('id')
            ->get();

        return response()->json([
            'establecimientos' => EstablecimientoResource::collection($establecimientos),
        ]);
    }
}
