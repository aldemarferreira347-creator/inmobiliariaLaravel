@extends('layouts.panel')

@section('titulo', 'Franjas de citas')

@section('panel')
    <div class="panel-topbar">
        <div>
            <h1>Franjas de citas</h1>
            <p class="subtitle">Define el horario en que los clientes pueden agendar visitas, día por día.</p>
        </div>
    </div>

    <div class="panel-card">
        <div class="table-responsive">
            <table class="panel-table franjas-tabla">
                <thead>
                    <tr>
                        <th>Día</th>
                        <th data-no-sort>Horario, intervalo y estado</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($dias as $dia)
                        @php($franja = $franjas->get($dia))
                        <tr>
                            <td class="td-title">{{ $dia }}</td>
                            <td>
                                <form method="POST" action="{{ route('admin.franjas.update') }}"
                                    id="form-franja-{{ $dia }}" class="franjas-fila-inputs">
                                    @csrf
                                    <input type="hidden" name="dia_semana" value="{{ $dia }}">

                                    <input type="time" name="hora_inicio"
                                        value="{{ old('hora_inicio', $franja?->hora_inicio ? substr($franja->hora_inicio, 0, 5) : '08:00') }}"
                                        required>
                                    <span>a</span>
                                    <input type="time" name="hora_fin"
                                        value="{{ old('hora_fin', $franja?->hora_fin ? substr($franja->hora_fin, 0, 5) : '18:00') }}"
                                        required>
                                    <input type="number" name="intervalo_minutos" min="5" max="480" step="5"
                                        value="{{ old('intervalo_minutos', $franja?->intervalo_minutos ?? 30) }}"
                                        title="Intervalo en minutos" required>
                                    <span class="td-subtitle">min</span>

                                    <label class="franjas-toggle">
                                        <input type="checkbox" name="activo" value="1"
                                            @checked(old('activo', $franja?->activo ?? false))>
                                        Activo
                                    </label>

                                    <button type="submit" class="btn-icon btn-icon--success" title="Guardar franja">
                                        <x-icon name="save" class="h-4 w-4" />
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
