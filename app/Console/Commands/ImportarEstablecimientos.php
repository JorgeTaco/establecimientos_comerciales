<?php

namespace App\Console\Commands;

use App\Importacion\DetectorDuplicados;
use App\Importacion\LectorHojaCalculo;
use App\Importacion\MapeadorEstablecimientos;
use App\Importacion\ResolutorCategoriasBD;
use App\Importacion\RevisorCoordenadas;
use App\Models\Categoria;
use App\Support\DatosEstablecimiento;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Validation\ValidationException;
use Throwable;

class ImportarEstablecimientos extends Command
{
    protected $signature = 'establecimientos:importar
        {archivo : Ruta del archivo .xlsx o .csv}
        {--hoja= : Nombre o número de hoja del .xlsx}
        {--fila-encabezado= : Número de fila del encabezado (1-based); se detecta si se omite}
        {--categoria=* : Categoría a asociar a los comercios que comercializan lácteos}
        {--dry-run : Analiza el archivo y muestra el reporte sin guardar nada}
        {--carga-web : Aplica los límites de la carga administrativa web}
        {--json= : Ruta del reporte JSON}
        {--limite= : Procesa solo las primeras N filas}
        {--corregir-signo : Corrige latitudes positivas evidentes}
        {--permitir-coordenadas-dudosas : Importa filas con coordenadas sospechosas}';

    protected $description = 'Importa establecimientos desde un Excel o CSV, valida coordenadas y detecta posibles duplicados';

    public function handle(
        MapeadorEstablecimientos $mapeador,
        RevisorCoordenadas $revisor,
        DetectorDuplicados $detector,
        DatosEstablecimiento $establecimientos,
        ResolutorCategoriasBD $catalogo
    ): int {
        $ruta = (string) $this->argument('archivo');

        try {
            $lector = new LectorHojaCalculo($ruta, (bool) $this->option('carga-web'));
            $hoja = $this->option('hoja');
            $filas = $lector->leer($hoja);
        } catch (Throwable $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }

        try {
            $indiceEncabezado = $this->option('fila-encabezado') !== null
                ? max(0, (int) $this->option('fila-encabezado') - 1)
                : $mapeador->detectarFilaEncabezado($filas);
        } catch (Throwable $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }

        if (! isset($filas[$indiceEncabezado])) {
            $this->error('La fila de encabezado indicada no existe en el archivo.');

            return self::FAILURE;
        }

        $mapa = $mapeador->mapa($filas[$indiceEncabezado]);

        foreach (['nombre', 'tipo', 'parroquia'] as $columna) {
            if (! array_key_exists($columna, $mapa)) {
                $this->error("No se reconoce la columna obligatoria «{$columna}» en el encabezado.");

                return self::FAILURE;
            }
        }

        if ($lector->hojas() === ['CSV'] && $hoja !== null) {
            $this->warn('La opción --hoja no aplica a archivos CSV; se ignoró.');
        }

        $categorias = $this->resolverCategorias();
        $limite = $this->option('limite') !== null ? max(1, (int) $this->option('limite')) : null;
        $resultados = [];

        for ($i = $indiceEncabezado + 1; $i < count($filas); $i++) {
            if ($this->filaVacia($filas[$i])) {
                continue;
            }

            if ($limite !== null && count($resultados) >= $limite) {
                break;
            }

            $resultado = $mapeador->mapear($filas[$i], $mapa);
            $resultado['fila'] = $i + 1;
            $resultado['estado'] = 'valida';
            $resultado['requiere_revision'] = false;
            $resultado['candidatos'] = [];
            $resultado['coincidencias'] = [];
            $resultado['candidatos_db'] = [];
            $resultado['establecimiento_id'] = null;
            $resultado['coordenadas'] = $revisor->revisar($resultado['latitud'], $resultado['longitud']);

            $coordenadas = $resultado['coordenadas'];

            if ($coordenadas['estado'] === RevisorCoordenadas::POSIBLE_SIGNO && $this->option('corregir-signo')) {
                $resultado['latitud'] = $revisor->corregirSigno($resultado['latitud']);
                $resultado['datos']['latitud'] = $resultado['latitud'];
                $coordenadas = [
                    'estado' => 'corregida',
                    'requiere_revision' => false,
                    'mensaje' => 'Latitud positiva corregida a negativa.',
                ];
                $resultado['avisos'][] = $coordenadas['mensaje'];
            }

            $resultado['coordenadas'] = $coordenadas;

            if (in_array($coordenadas['estado'], [RevisorCoordenadas::INCOMPLETAS, RevisorCoordenadas::FUERA_DE_RANGO], true)) {
                $resultado['errores']['coordenadas'][] = $coordenadas['mensaje'];
            }

            if (in_array($coordenadas['estado'], [RevisorCoordenadas::POSIBLE_SIGNO, RevisorCoordenadas::FUERA_DE_ZONA], true)
                && ! $this->option('permitir-coordenadas-dudosas')) {
                $resultado['requiere_revision'] = true;
            }

            $comercializaLacteos = $resultado['origen']['comercializa_lacteos'];
            if ($comercializaLacteos !== false) {
                $inferidas = $catalogo->resolver($resultado['origen']['categorias_lacteas']);
                $ids = array_values(array_unique([
                    ...($comercializaLacteos === true ? $categorias : []),
                    ...$inferidas['ids'],
                ]));
                if ($ids !== []) {
                    $resultado['datos']['categorias'] = $ids;
                }
                if ($inferidas['desconocidas'] !== []) {
                    $resultado['avisos'][] = 'Categorías sin asociar: '.implode(', ', $inferidas['desconocidas']);
                }
            }

            $erroresValidacion = $mapeador->validar($resultado['datos']);

            if ($erroresValidacion !== []) {
                $resultado['errores'] = array_merge_recursive($resultado['errores'], $erroresValidacion);
            }

            if ($resultado['errores'] !== []) {
                $resultado['estado'] = 'invalida';
            } elseif ($resultado['requiere_revision'] ?? false) {
                $resultado['estado'] = 'revisar';
            }

            $resultados[] = $resultado;
        }

        if ($resultados === []) {
            $this->error('No se encontraron filas de datos después del encabezado.');

            return self::FAILURE;
        }

        $this->marcarDuplicados($resultados, $detector);

        $resumenAntes = $this->resumen($resultados);
        $this->mostrarEncabezado($ruta, $hoja, $indiceEncabezado, $resumenAntes);
        $this->mostrarAvisos($resultados);
        $this->mostrarErrores($resultados);
        $this->mostrarDuplicados($resultados);
        $this->mostrarCoincidencias($resultados);
        $this->mostrarCoordenadas($resultados);

        if (! $this->option('dry-run')) {
            $this->newLine();
            $this->info('Guardando filas válidas...');
            $this->guardar($resultados, $establecimientos, $detector);
        }

        $resumen = $this->resumen($resultados);

        $this->newLine();
        $this->table(
            ['Resultado', 'Total'],
            [
                ['Filas de datos', $resumen['total']],
                ['Válidas para importar', $resumenAntes['validas']],
                ['Importadas', $resumen['importadas']],
                ['Con avisos', $resumen['con_avisos']],
                ['Inválidas', $resumen['invalidas']],
                ['Posibles duplicados', $resumen['posibles_duplicados']],
                ['Coincidencias leves (no bloquean)', $resumen['con_coincidencias']],
                ['Coordenadas a revisar', $resumen['coordenadas_a_revisar']],
                ['Para reintentar', $resumen['reintentar']],
            ]
        );

        if ($this->option('dry-run')) {
            $this->warn('Modo --dry-run: no se guardó ningún establecimiento.');
        }

        $this->guardarReporte($ruta, $hoja, $indiceEncabezado, $resultados, $resumen, $resumenAntes);

        return $resumen['errores'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  list<array<string, mixed>>  $resultados
     */
    private function marcarDuplicados(array &$resultados, DetectorDuplicados $detector): void
    {
        $candidatosArchivo = $detector->enArchivo($resultados);

        foreach ($resultados as $indice => &$resultado) {
            $todos = $candidatosArchivo[$indice] ?? [];

            $resultado['candidatos'] = array_values(array_filter(
                $todos,
                fn (array $candidato) => $candidato['confianza'] === DetectorDuplicados::ALTA
            ));
            $resultado['coincidencias'] = array_values(array_filter(
                $todos,
                fn (array $candidato) => $candidato['confianza'] !== DetectorDuplicados::ALTA
            ));

            if ($resultado['estado'] !== 'invalida' && $resultado['candidatos'] !== []) {
                $resultado['estado'] = 'posible_duplicado';
            }
        }

        unset($resultado);

        foreach ($resultados as &$resultado) {
            if (! in_array($resultado['estado'], ['valida', 'revisar'], true)) {
                continue;
            }

            $candidatos = $detector->enBaseDeDatos($resultado['datos']);

            if ($candidatos !== []) {
                $resultado['candidatos_db'] = $candidatos;
                $resultado['estado'] = 'posible_duplicado';
            }
        }

        unset($resultado);
    }

    /**
     * @param  list<array<string, mixed>>  $resultados
     */
    private function guardar(array &$resultados, DatosEstablecimiento $establecimientos, DetectorDuplicados $detector): void
    {
        $importadas = 0;

        foreach ($resultados as &$resultado) {
            if ($resultado['estado'] !== 'valida') {
                continue;
            }

            try {
                [$establecimiento, $candidatos] = $establecimientos->importar($resultado['datos']);

                if ($establecimiento === null) {
                    $resultado['estado'] = 'posible_duplicado';
                    $resultado['candidatos_db'] = $detector->enBaseDeDatos($resultado['datos']) ?: array_map(
                        fn (string $id) => ['id' => $id, 'nombre' => '', 'motivos' => []],
                        $candidatos
                    );
                } else {
                    $resultado['estado'] = 'importada';
                    $resultado['establecimiento_id'] = (string) $establecimiento->id;
                    $importadas++;
                }
            } catch (ValidationException $error) {
                $resultado['estado'] = 'invalida';
                $resultado['errores'] = array_merge_recursive($resultado['errores'], $error->errors());
            } catch (LockTimeoutException) {
                $resultado['estado'] = 'reintentar';
            } catch (Throwable $error) {
                $resultado['estado'] = 'error';
                $resultado['errores']['general'][] = $error->getMessage();
            }
        }

        unset($resultado);

        $this->line("  Importadas: {$importadas}");
    }

    /**
     * @param  list<array<string, mixed>>  $resultados
     * @return array<string, int>
     */
    private function resumen(array $resultados): array
    {
        $estados = array_count_values(array_column($resultados, 'estado'));

        return [
            'total' => count($resultados),
            'validas' => $estados['valida'] ?? 0,
            'importadas' => $estados['importada'] ?? 0,
            'invalidas' => $estados['invalida'] ?? 0,
            'posibles_duplicados' => $estados['posible_duplicado'] ?? 0,
            'revisar' => $estados['revisar'] ?? 0,
            'reintentar' => $estados['reintentar'] ?? 0,
            'errores' => $estados['error'] ?? 0,
            'con_avisos' => count(array_filter($resultados, fn (array $fila) => $fila['avisos'] !== [])),
            'con_coincidencias' => count(array_filter(
                $resultados,
                fn (array $fila) => ($fila['coincidencias'] ?? []) !== []
            )),
            'coordenadas_a_revisar' => count(array_filter(
                $resultados,
                fn (array $fila) => (bool) ($fila['coordenadas']['requiere_revision'] ?? false)
            )),
        ];
    }

    private function mostrarEncabezado(string $ruta, ?string $hoja, int $indiceEncabezado, array $resumen): void
    {
        $this->info('Importación de establecimientos');
        $this->line("Archivo: {$ruta}");

        if ($hoja !== null && $hoja !== '') {
            $this->line("Hoja: {$hoja}");
        }

        $this->line('Encabezado: fila '.($indiceEncabezado + 1));
        $this->line("Filas de datos: {$resumen['total']} | Válidas: {$resumen['validas']} | Inválidas: {$resumen['invalidas']} | Duplicados: {$resumen['posibles_duplicados']} | Coordenadas: {$resumen['coordenadas_a_revisar']}");
    }

    /**
     * @param  list<array<string, mixed>>  $resultados
     */
    private function mostrarAvisos(array $resultados): void
    {
        $conAvisos = array_values(array_filter($resultados, fn (array $fila) => $fila['avisos'] !== []));

        if ($conAvisos === []) {
            return;
        }

        $this->newLine();
        $this->warn('Avisos:');

        foreach (array_slice($conAvisos, 0, 10) as $fila) {
            $this->line("  Fila {$fila['fila']} | {$fila['nombre']} | ".implode(' ', $fila['avisos']));
        }

        if (count($conAvisos) > 10) {
            $this->line('  ... y '.(count($conAvisos) - 10).' más.');
        }
    }

    /**
     * @param  list<array<string, mixed>>  $resultados
     */
    private function mostrarErrores(array $resultados): void
    {
        $invalidas = array_values(array_filter(
            $resultados,
            fn (array $fila) => in_array($fila['estado'], ['invalida', 'error'], true)
        ));

        if ($invalidas === []) {
            return;
        }

        $this->newLine();
        $this->warn('Filas con errores:');

        foreach (array_slice($invalidas, 0, 15) as $fila) {
            $mensajes = collect($fila['errores'])->flatten()->implode(' | ');
            $this->line("  Fila {$fila['fila']} | {$fila['nombre']} | {$mensajes}");
        }

        if (count($invalidas) > 15) {
            $this->line('  ... y '.(count($invalidas) - 15).' más.');
        }
    }

    /**
     * @param  list<array<string, mixed>>  $resultados
     */
    private function mostrarDuplicados(array $resultados): void
    {
        $duplicados = array_values(array_filter(
            $resultados,
            fn (array $fila) => $fila['estado'] === 'posible_duplicado'
        ));

        if ($duplicados === []) {
            return;
        }

        $this->newLine();
        $this->warn('Posibles duplicados (revisar antes de fusionar):');

        foreach (array_slice($duplicados, 0, 15) as $fila) {
            $partes = [];

            foreach ($fila['candidatos'] as $candidato) {
                $partes[] = "fila {$candidato['fila']} (".implode(', ', $candidato['motivos']).')';
            }

            if ($fila['candidatos_db'] !== []) {
                $enBase = [];

                foreach ($fila['candidatos_db'] as $candidato) {
                    $enBase[] = "ID {$candidato['id']} «{$candidato['nombre']}» (".implode(', ', $candidato['motivos']).')';
                }

                $partes[] = 'En base: '.implode('; ', $enBase);
            }

            $this->line("  Fila {$fila['fila']} | {$fila['nombre']} | ".implode('; ', $partes));
        }

        if (count($duplicados) > 15) {
            $this->line('  ... y '.(count($duplicados) - 15).' más.');
        }
    }

    /**
     * @param  list<array<string, mixed>>  $resultados
     */
    private function mostrarCoincidencias(array $resultados): void
    {
        $conCoincidencias = array_values(array_filter(
            $resultados,
            fn (array $fila) => ($fila['coincidencias'] ?? []) !== []
        ));

        if ($conCoincidencias === []) {
            return;
        }

        $this->newLine();
        $this->line('Coincidencias leves (mismo nombre o misma dirección; no bloquean la importación):');

        foreach (array_slice($conCoincidencias, 0, 10) as $fila) {
            $partes = [];

            foreach ($fila['coincidencias'] as $candidato) {
                $partes[] = "fila {$candidato['fila']} (".implode(', ', $candidato['motivos']).')';
            }

            $this->line("  Fila {$fila['fila']} | {$fila['nombre']} | ".implode('; ', $partes));
        }

        if (count($conCoincidencias) > 10) {
            $this->line('  ... y '.(count($conCoincidencias) - 10).' más.');
        }
    }

    /**
     * @param  list<array<string, mixed>>  $resultados
     */
    private function mostrarCoordenadas(array $resultados): void
    {
        $revisar = array_values(array_filter(
            $resultados,
            fn (array $fila) => (bool) ($fila['coordenadas']['requiere_revision'] ?? false)
        ));

        if ($revisar === []) {
            return;
        }

        $this->newLine();
        $this->warn('Coordenadas a revisar:');

        foreach (array_slice($revisar, 0, 15) as $fila) {
            $this->line("  Fila {$fila['fila']} | {$fila['nombre']} | {$fila['coordenadas']['mensaje']}");
        }

        if (count($revisar) > 15) {
            $this->line('  ... y '.(count($revisar) - 15).' más.');
        }
    }

    /**
     * @return list<int>
     */
    private function resolverCategorias(): array
    {
        $nombres = array_values(array_unique(array_filter(array_map(
            'trim',
            (array) $this->option('categoria')
        ))));

        if ($nombres === []) {
            return [];
        }

        $encontradas = Categoria::query()
            ->whereIn('nombre', $nombres)
            ->where('activa', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (count($encontradas) !== count($nombres)) {
            $this->warn('Alguna categoría indicada no existe o está inactiva; revisa el nombre antes de importar.');
        }

        return $encontradas;
    }

    private function filaVacia(array $fila): bool
    {
        foreach ($fila as $valor) {
            if (trim((string) $valor) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<array<string, mixed>>  $resultados
     * @param  array<string, int>  $resumen
     * @param  array<string, int>  $resumenAntes
     */
    private function guardarReporte(string $ruta, ?string $hoja, int $indiceEncabezado, array $resultados, array $resumen, array $resumenAntes): void
    {
        $rutaJson = $this->option('json');

        if ($rutaJson === null || $rutaJson === '') {
            $rutaJson = storage_path('app/importaciones/importacion-'.date('Ymd-His').'.json');
        } elseif (! str_starts_with($rutaJson, DIRECTORY_SEPARATOR)) {
            $rutaJson = base_path($rutaJson);
        }

        $reporte = [
            'archivo' => $ruta,
            'hoja' => $hoja,
            'fila_encabezado' => $indiceEncabezado + 1,
            'resumen' => $resumen + ['validas_para_importar' => $resumenAntes['validas']],
            'filas' => $resultados,
        ];

        $directorio = dirname($rutaJson);

        if (! is_dir($directorio) && ! mkdir($directorio, 0775, true) && ! is_dir($directorio)) {
            $this->warn("No se pudo crear el directorio del reporte: {$directorio}");

            return;
        }

        $json = json_encode($reporte, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false || file_put_contents($rutaJson, $json) === false) {
            $this->warn("No se pudo escribir el reporte JSON en {$rutaJson}");

            return;
        }

        $this->line("Reporte JSON: {$rutaJson}");

        if (str_starts_with($rutaJson, base_path())) {
            $this->warn('El reporte puede contener datos de contacto; no lo subas a Git.');
        }
    }
}
