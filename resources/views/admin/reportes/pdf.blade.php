{{--
    Documento PDF de un reporte. Es autónomo: Dompdf no carga hojas de estilo
    externas y no soporta flex ni grid, así que el diseño va con tablas y
    estilos en línea.
--}}
@php
    $filas = $reporte->filas();
    $numericas = $reporte->columnasNumericas();
    $columnas = $reporte->columnas();
@endphp

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Reporte de {{ $reporte->titulo() }}</title>
    <style>
        /* El margen superior reserva el espacio de la cabecera fija */
        @page { margin: 120px 24px 60px 24px; }

        body { font-family: Helvetica, Arial, sans-serif; font-size: 9pt; color: #0f172a; margin: 0; }

        .cabecera {
            position: fixed; top: -96px; left: 0; right: 0; height: 80px;
            background-color: #0f1e4a; color: #ffffff; padding: 14px 18px;
        }
        .cabecera h1 { margin: 0; font-size: 15pt; }
        .cabecera p { margin: 4px 0 0 0; font-size: 8pt; color: #fad080; }
        .franja { height: 3px; background-color: #f5a623; margin-top: 10px; }

        .pie {
            position: fixed; bottom: -40px; left: 0; right: 0;
            font-size: 7pt; color: #64748b; text-align: center;
        }

        .resumen { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin-bottom: 14px; }
        .resumen td {
            background-color: #f8fafc; border: 1px solid #e8edf7; border-radius: 6px;
            padding: 8px; text-align: center;
        }
        .resumen .valor { font-size: 13pt; font-weight: bold; color: #1e3177; display: block; }
        .resumen .etiqueta { font-size: 7pt; color: #64748b; }

        table.datos { width: 100%; border-collapse: collapse; }
        table.datos thead th {
            background-color: #1e3177; color: #ffffff; font-size: 8pt; text-align: left;
            padding: 6px; border-bottom: 2px solid #f5a623;
        }
        table.datos td { padding: 5px 6px; border-bottom: 1px solid #eef1f8; }
        table.datos tbody tr:nth-child(even) td { background-color: #f8fafc; }
        table.datos .numero { text-align: right; white-space: nowrap; }
        /* Evita que una fila se parta entre dos páginas */
        table.datos tr { page-break-inside: avoid; }

        .vacio { padding: 24px; text-align: center; color: #64748b; }
    </style>
</head>

<body>
    <div class="cabecera">
        <h1>Inmobiliaria García — {{ mb_strtoupper($reporte->titulo()) }}</h1>
        <p>Periodo: {{ $reporte->filtro()->descripcion() }} · Generado: {{ now()->format('d/m/Y H:i') }}</p>
        <div class="franja"></div>
    </div>

    <div class="pie">Inmobiliaria García — Neiva, Huila · Uso interno exclusivo</div>

    <table class="resumen">
        <tr>
            @foreach ($reporte->resumen() as $etiqueta => $valor)
                <td>
                    <span class="valor">{{ $valor }}</span>
                    <span class="etiqueta">{{ $etiqueta }}</span>
                </td>
            @endforeach
        </tr>
    </table>

    @if ($filas->isEmpty())
        <p class="vacio">No hay registros disponibles para los filtros seleccionados.</p>
    @else
        <table class="datos">
            <thead>
                <tr>
                    @foreach ($columnas as $columna)
                        <th>{{ $columna }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($filas as $fila)
                    <tr>
                        @foreach ($fila as $indice => $celda)
                            @php($esNumero = in_array($indice, $numericas, true))
                            <td @class(['numero' => $esNumero])>
                                {{ $esNumero ? '$'.number_format((float) $celda, 0, ',', '.') : $celda }}
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>

</html>
