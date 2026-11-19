<?php

namespace App\Console\Commands;

use App\Importacion\EnviadorLotes;
use App\Importacion\ImportadorEstablecimientos;
use Illuminate\Console\Command;
use Throwable;

class PrepararLotesEstablecimientos extends Command
{
    protected $signature = 'establecimientos:preparar-lotes
        {archivo : Ruta al CSV exportado desde Excel}
        {--destino=datos/importacion : Directorio donde se guardan los lotes JSON}
        {--enviar : Envía los lotes a la API con una cuenta de administrador}
        {--url= : URL base de la aplicación}
        {--correo= : Correo del administrador}
        {--clave= : Contraseña del administrador}
        {--tamano-lote=100 : Máximo de fichas por lote}';

    protected $description = 'Lee un CSV de Excel, normaliza las fichas, revisa coordenadas y duplicados, y prepara lotes para la API.';

    public function handle(ImportadorEstablecimientos $importador, EnviadorLotes $enviador): int
    {
        $tamano = max(1, min(100, (int) $this->option('tamano-lote')));

        try {
            $resultado = $importador->preparar((string) $this->argument('archivo'), $tamano);
        } catch (Throwable $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }

        $destino = (string) $this->option('destino');
        if (! is_dir($destino) && ! mkdir($destino, 0755, true) && ! is_dir($destino)) {
            $this->error("No se pudo crear el directorio: {$destino}");

            return self::FAILURE;
        }

        foreach ($resultado['lotes'] as $indice => $lote) {
            $this->guardar($destino.DIRECTORY_SEPARATOR.'lote-'.($indice + 1).'.json', ['filas' => $lote['filas']]);
        }

        $this->guardar($destino.DIRECTORY_SEPARATOR.'informe.json', [
            'totales' => $resultado['totales'],
            'columnas' => $resultado['columnas'],
            'sin_mapear' => $resultado['sin_mapear'],
            'lotes' => array_map(fn ($lote) => ['numeros' => $lote['numeros']], $resultado['lotes']),
            'invalidas' => $resultado['invalidas'],
            'duplicados' => $resultado['duplicados'],
        ]);

        $totales = $resultado['totales'];
        $this->table(
            ['Filas', 'Válidas', 'Inválidas', 'Posibles duplicados', 'Lotes'],
            [[$totales['filas'], $totales['validas'], $totales['invalidas'], $totales['duplicados'], $totales['lotes']]]
        );

        if ($resultado['sin_mapear'] !== []) {
            $this->warn('Columnas sin mapear: '.implode(', ', $resultado['sin_mapear']));
        }

        $this->info("Lotes JSON guardados en {$destino}");

        if (! $this->option('enviar')) {
            return self::SUCCESS;
        }

        if ($resultado['lotes'] === []) {
            $this->warn('No hay lotes válidos para enviar.');

            return self::SUCCESS;
        }

        $url = (string) ($this->option('url') ?: config('app.url'));
        $correo = (string) ($this->option('correo') ?: env('IMPORTADOR_CORREO', ''));
        $clave = (string) ($this->option('clave') ?: env('IMPORTADOR_CLAVE', ''));

        try {
            $respuestas = $enviador->enviar($resultado['lotes'], $url, $correo, $clave);
        } catch (Throwable $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }

        foreach ($respuestas as $respuesta) {
            $estados = array_count_values(array_column($respuesta['resultados'], 'estado'));
            $detalle = [];
            foreach ($estados as $estado => $cantidad) {
                $detalle[] = "{$estado}: {$cantidad}";
            }
            $this->line('Lote '.$respuesta['lote'].' (HTTP '.$respuesta['estado_http'].'): '.implode(', ', $detalle));
        }

        $this->guardar($destino.DIRECTORY_SEPARATOR.'respuestas.json', $respuestas);

        return self::SUCCESS;
    }

    private function guardar(string $ruta, array $contenido): void
    {
        file_put_contents($ruta, json_encode(
            $contenido,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ).PHP_EOL);
    }
}
