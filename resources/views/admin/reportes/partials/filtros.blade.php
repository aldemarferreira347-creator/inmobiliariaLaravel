{{--
    Filtros comunes de los reportes: pestañas de periodo y rango de fechas.
    `$estados` solo llega en la pantalla de un tipo concreto.
--}}
@php($ruta = $tipo ?? null)

<div class="periodo-tabs">
    @foreach (\App\Enumerados\PeriodoReporte::cases() as $periodo)
        <a @class(['periodo-tab', 'active' => ! $filtro->usaRangoPropio() && $filtro->periodo === $periodo])
            href="{{ request()->fullUrlWithQuery(['periodo' => $periodo->value, 'desde' => null, 'hasta' => null]) }}">
            {{ $periodo->etiqueta() }}
        </a>
    @endforeach
</div>

<form method="GET" class="reporte-filters">
    <input type="hidden" name="periodo" value="{{ $filtro->periodo->value }}">

    <label>
        Desde
        <input type="date" name="desde" value="{{ $filtro->desde?->format('Y-m-d') }}">
    </label>

    <label>
        Hasta
        <input type="date" name="hasta" value="{{ $filtro->hasta?->format('Y-m-d') }}">
    </label>

    @isset($estados)
        <label>
            Estado
            <select name="estado">
                <option value="">Todos</option>
                @foreach ($estados as $valor => $etiqueta)
                    <option value="{{ $valor }}" @selected($filtro->estado === $valor)>{{ $etiqueta }}</option>
                @endforeach
            </select>
        </label>
    @endisset

    <button type="submit" class="btn-filter-apply">Aplicar</button>

    @if ($filtro->usaRangoPropio() || $filtro->estado)
        <a href="{{ url()->current() }}" class="btn-filter-reset">Limpiar</a>
    @endif
</form>
