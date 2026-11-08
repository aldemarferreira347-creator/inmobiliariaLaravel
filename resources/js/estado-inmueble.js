import Swal from 'sweetalert2';

// Cada 30 s (límite de peticiones del hosting) y solo con la pestaña a la vista
const INTERVALO_MS = 30_000;

// Ficha del inmueble: avisa si su estado cambió mientras el usuario la miraba (HU-09.3)
export function iniciarEstadoInmueble() {
    const ficha = document.querySelector('[data-url-estado]');
    if (!ficha) return;

    const { urlEstado, estado } = ficha.dataset;
    let avisado = false;

    setInterval(async () => {
        if (document.hidden || avisado) return;

        try {
            const respuesta = await fetch(urlEstado, { headers: { Accept: 'application/json' } });
            if (!respuesta.ok) return;

            const datos = await respuesta.json();
            if (datos.estado === estado) return;

            avisado = true;
            await Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'info',
                title: 'Este inmueble acaba de cambiar de estado',
                showConfirmButton: false,
                timer: 2500,
                background: getComputedStyle(document.documentElement).getPropertyValue('--color-card').trim(),
                color: getComputedStyle(document.documentElement).getPropertyValue('--color-ink').trim(),
            });
            window.location.reload();
        } catch {
            // Sin red: se reintenta en el siguiente ciclo
        }
    }, INTERVALO_MS);
}
