/*
 * Cuenta atrás del plazo de pago de una reserva.
 * Cualquier elemento con `data-expira="<fecha ISO>"` se actualiza cada segundo
 * y cambia de aspecto cuando queda menos de una hora o cuando ya venció.
 */

const UNA_HORA_EN_MS = 3_600_000;

function formatear(restanteMs) {
    const total = Math.floor(restanteMs / 1000);
    const horas = Math.floor(total / 3600);
    const minutos = Math.floor((total % 3600) / 60);
    const segundos = total % 60;

    return `${horas} h ${String(minutos).padStart(2, '0')} m ${String(segundos).padStart(2, '0')} s`;
}

function actualizar(elementos) {
    const ahora = Date.now();

    elementos.forEach((elemento) => {
        const restante = new Date(elemento.dataset.expira).getTime() - ahora;
        const texto = elemento.querySelector('[data-cuenta-atras-texto]') ?? elemento;

        if (restante <= 0) {
            texto.textContent = 'Plazo vencido';
            elemento.classList.add('reserva-countdown--vencida');
            elemento.classList.remove('reserva-countdown--urgente');
            return;
        }

        texto.textContent = `Vence en ${formatear(restante)}`;
        elemento.classList.toggle('reserva-countdown--urgente', restante < UNA_HORA_EN_MS);
    });
}

export function iniciarCuentaAtras() {
    const elementos = document.querySelectorAll('[data-expira]');
    if (elementos.length === 0) return;

    actualizar(elementos);
    setInterval(() => actualizar(elementos), 1000);
}
