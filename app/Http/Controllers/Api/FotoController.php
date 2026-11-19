<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FotoController
{
    public function __invoke(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'foto' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096', 'dimensions:max_width=4096,max_height=4096'],
        ]);
        $ruta = $datos['foto']->store('fotos', 'public');
        abort_if($ruta === false, 503, 'No se pudo guardar la foto.');

        return response()->json(['foto_url' => Storage::disk('public')->url($ruta)], 201);
    }
}
