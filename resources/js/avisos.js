/*
 * Distintivos de mensajes y notificaciones sin leer.
 * Un único sondeo alimenta ambos contadores; el prototipo tenía dos en paralelo
 * con intervalos distintos.
 */

const INTERVALO_MS = 30_000;
const TOPE_VISIBLE = 99;

function pintar(selector, cantidad) {
    document.querySelectorAll(selector).forEach((distintivo) => {
        distintivo.textContent = cantidad > TOPE_VISIBLE ? `${TOPE_VISIBLE}+` : String(cantidad);
        distintivo.classList.toggle('hidden', cantidad === 0);
    });
}

async function consultar(url) {
    try {
        const respuesta = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        if (!respuesta.ok) return;

        const { mensajes, notificaciones } = await respuesta.json();

        pintar('[data-badge-mensajes]', mensajes ?? 0);
        pintar('[data-badge-notificaciones]', notificaciones ?? 0);
    } catch {
        // Un fallo puntual de red no debe ensuciar la consola del usuario
    }
}

// Alterna los campos del formulario de notificación según el destinatario elegido
function iniciarSelectorDeDestino() {
    const selector = document.querySelector('[data-destino-notificacion]');
    if (!selector) return;

    const sincronizar = () => {
        document.querySelectorAll('[data-destino]').forEach((campo) => {
            campo.classList.toggle('hidden', campo.dataset.destino !== selector.value);
        });
    };

    selector.addEventListener('change', sincronizar);
    sincronizar();
}

export function iniciarAvisos() {
    iniciarSelectorDeDestino();

    const url = document.body.dataset.urlAvisos;
    if (!url) return;

    consultar(url);
    setInterval(() => {
        if (!document.hidden) consultar(url);
    }, INTERVALO_MS);
}
