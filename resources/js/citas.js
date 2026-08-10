/*
 * Selector de hora del formulario «Agendar visita» (HU-27.1/27.2). Las horas
 * dependen de la fecha elegida, así que se cargan por AJAX en vez de listar
 * un horario fijo que podría no respetar la franja configurada u ocuparse.
 */

function poblarHoras(select, hint, horas, mensajeVacio) {
    if (horas.length === 0) {
        select.disabled = true;
        select.innerHTML = '<option value="">Sin horarios disponibles</option>';
        hint.textContent = mensajeVacio;
        return;
    }

    select.disabled = false;
    select.innerHTML = ['<option value="">Elige una hora</option>']
        .concat(horas.map((hora) => `<option value="${hora}">${hora}</option>`))
        .join('');
    hint.textContent = 'Lunes a sábado, 08:00 a 18:00.';
}

async function cargarHoras(formulario) {
    const fecha = formulario.querySelector('#fecha_cita').value;
    const select = formulario.querySelector('#hora_cita');
    const hint = formulario.querySelector('[data-cita-hint]');
    const inmuebleId = formulario.querySelector('input[name="inmueble_id"]').value;

    if (!fecha) return;

    select.disabled = true;
    select.innerHTML = '<option value="">Cargando horarios...</option>';

    try {
        const url = new URL(formulario.dataset.franjasUrl, window.location.origin);
        url.searchParams.set('inmueble_id', inmuebleId);
        url.searchParams.set('fecha', fecha);

        const respuesta = await fetch(url, { headers: { Accept: 'application/json' } });
        const datos = await respuesta.json();

        poblarHoras(select, hint, datos.horas ?? [], 'No hay horarios disponibles para esta fecha. Elige otra.');
    } catch {
        poblarHoras(select, hint, [], 'No se pudieron cargar los horarios. Intenta nuevamente.');
    }
}

export function iniciarCitas() {
    document.querySelectorAll('[data-cita-form]').forEach((formulario) => {
        formulario.querySelector('#fecha_cita')?.addEventListener('change', () => cargarHoras(formulario));
    });
}
