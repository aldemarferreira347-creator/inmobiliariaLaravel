<?php

namespace Tests\Unit;

use Illuminate\Database\MySqlConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use PDO;
use Tests\TestCase;

/**
 * InfinityFree usa MyISAM con utf8mb4: una clave no puede superar 1000 bytes.
 * SQLite no guarda la longitud de los varchar, así que el SQL se genera con la
 * gramática de MySQL en modo «pretend» (sin conectarse a ningún servidor) y se
 * calcula el tamaño de cada índice a partir de ese SQL.
 */
class EsquemaCompatibleTest extends TestCase
{
    use RefreshDatabase;

    private const LIMITE_BYTES = 1000;

    public function test_ningun_indice_supera_1000_bytes_en_utf8mb4(): void
    {
        $excedidos = [];

        foreach ($this->indicesDelEsquema() as [$tabla, $indice, $columnas, $tipos]) {
            $bytes = 0;
            foreach ($columnas as $columna) {
                $bytes += $this->bytesDeTipo($tipos[$tabla][$columna] ?? 'bigint');
            }

            if ($bytes > self::LIMITE_BYTES) {
                $excedidos[] = "{$tabla}.{$indice} = {$bytes} bytes";
            }
        }

        $this->assertSame([], $excedidos, 'Índices por encima de 1000 bytes: '.implode('; ', $excedidos));
    }

    /**
     * @return list<array{string, string, list<string>, array<string, array<string, string>>}>
     */
    private function indicesDelEsquema(): array
    {
        Config::set('database.connections.mysql_pretend', ['driver' => 'mysql_pretend', 'database' => 'x', 'prefix' => '']);
        DB::extend('mysql_pretend', fn ($config) => new MySqlConnection(fn () => new PDO('sqlite::memory:'), 'x', '', $config));

        // Las migraciones usan la conexión por defecto: se cambia solo mientras se generan las sentencias
        $original = Config::get('database.default');
        Config::set('database.default', 'mysql_pretend');

        $sentencias = [];
        try {
            foreach (app('migrator')->getMigrationFiles(database_path('migrations')) as $archivo) {
                $migracion = require $archivo;
                foreach (DB::connection('mysql_pretend')->pretend(fn () => $migracion->up()) as $consulta) {
                    $sentencias[] = $consulta['query'];
                }
            }
        } finally {
            Config::set('database.default', $original);
        }

        $tipos = [];
        $indices = [];

        foreach ($sentencias as $sql) {
            if (preg_match('/^create table `(\w+)` \((.*)\)$/s', $sql, $m)) {
                foreach ($this->partesNivelSuperior($m[2]) as $parte) {
                    if (preg_match('/^`(\w+)` (\w+(?:\(\d+\))?)/', $parte, $c)) {
                        $tipos[$m[1]][$c[1]] = $c[2];
                    } elseif (preg_match('/^primary key \((.+)\)$/', $parte, $p)) {
                        $indices[] = [$m[1], 'primary', $this->nombresDeColumna($p[1])];
                    }
                }
            } elseif (preg_match('/^alter table `(\w+)` (?:add|modify) `(\w+)` (\w+(?:\(\d+\))?)/', $sql, $m)) {
                $tipos[$m[1]][$m[2]] = $m[3];
            } elseif (preg_match('/^alter table `(\w+)` add (?:unique|index) `(\w+)`\((.+)\)$/', $sql, $m)) {
                $indices[] = [$m[1], $m[2], $this->nombresDeColumna($m[3])];
            }
        }

        return array_map(fn ($i) => [$i[0], $i[1], $i[2], $tipos], $indices);
    }

    /** Separa por comas que no estén dentro de paréntesis (p. ej. enum(...) o decimal(12, 2)). */
    private function partesNivelSuperior(string $cuerpo): array
    {
        $partes = [];
        $actual = '';
        $nivel = 0;

        foreach (str_split($cuerpo) as $caracter) {
            if ($caracter === '(') {
                $nivel++;
            } elseif ($caracter === ')') {
                $nivel--;
            }

            if ($caracter === ',' && $nivel === 0) {
                $partes[] = trim($actual);
                $actual = '';
            } else {
                $actual .= $caracter;
            }
        }

        $partes[] = trim($actual);

        return $partes;
    }

    private function nombresDeColumna(string $lista): array
    {
        preg_match_all('/`(\w+)`/', $lista, $m);

        return $m[1];
    }

    private function bytesDeTipo(string $tipo): int
    {
        if (preg_match('/^(?:var)?char\((\d+)\)$/', $tipo, $m)) {
            return (int) $m[1] * 4;
        }

        return 8;
    }
}
