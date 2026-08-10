/*
 * Galería del detalle de inmueble: carrusel con flechas, puntos, miniaturas
 * y desplazamiento táctil.
 */

const DESPLAZAMIENTO_MINIMO = 50;

export function iniciarGaleria() {
    const pista = document.getElementById('slider-track');
    if (!pista) return;

    const diapositivas = pista.querySelectorAll('.slide-item');
    const puntos = document.querySelectorAll('.slider-dot');
    const miniaturas = document.querySelectorAll('.thumb-item');
    const contador = document.getElementById('slide-current');
    const total = diapositivas.length;

    let indice = 0;

    function mostrar(nuevo) {
        indice = (nuevo + total) % total;
        pista.style.transform = `translateX(-${indice * 100}%)`;

        puntos.forEach((punto, i) => punto.classList.toggle('active', i === indice));
        miniaturas.forEach((miniatura, i) => miniatura.classList.toggle('active-thumb', i === indice));

        if (contador) contador.textContent = String(indice + 1);
        miniaturas[indice]?.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'nearest' });
    }

    document.getElementById('slider-prev')?.addEventListener('click', () => mostrar(indice - 1));
    document.getElementById('slider-next')?.addEventListener('click', () => mostrar(indice + 1));

    puntos.forEach((punto, i) => punto.addEventListener('click', () => mostrar(i)));
    miniaturas.forEach((miniatura, i) => miniatura.addEventListener('click', () => mostrar(i)));

    document.addEventListener('keydown', (evento) => {
        if (evento.key === 'ArrowLeft') mostrar(indice - 1);
        if (evento.key === 'ArrowRight') mostrar(indice + 1);
    });

    let inicioX = 0;
    pista.addEventListener('touchstart', (evento) => {
        inicioX = evento.changedTouches[0].screenX;
    }, { passive: true });

    pista.addEventListener('touchend', (evento) => {
        const recorrido = inicioX - evento.changedTouches[0].screenX;
        if (Math.abs(recorrido) < DESPLAZAMIENTO_MINIMO) return;
        mostrar(recorrido > 0 ? indice + 1 : indice - 1);
    }, { passive: true });

    mostrar(0);
}
