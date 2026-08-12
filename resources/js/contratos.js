/*
 * Emisión de contratos: el valor mostrado es solo informativo, viene del
 * precio ya fijado en la reserva elegida (nunca se escribe a mano).
 */
export function iniciarContratos() {
    const select = document.querySelector('[data-reserva-monto-select]');
    const display = document.querySelector('[data-reserva-monto-display]');
    if (!select || !display) return;

    const actualizar = () => {
        display.value = select.selectedOptions[0]?.dataset.monto ?? '';
    };

    select.addEventListener('change', actualizar);
    actualizar();
}
