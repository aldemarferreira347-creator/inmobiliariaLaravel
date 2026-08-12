/*
 * Guardado de tarjetas con Stripe Elements (HU-20.1). El número, el CVV y la
 * fecha de expiración solo los ve Stripe.js dentro del iframe del Element:
 * nunca pasan por nuestro formulario ni por nuestro servidor.
 */

function mostrarError(contenedor, mensaje) {
    contenedor.textContent = mensaje ?? '';
}

async function pedirJson(url, body) {
    const respuesta = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        },
        body: body ? JSON.stringify(body) : undefined,
    });

    const datos = await respuesta.json().catch(() => ({}));
    if (!respuesta.ok) {
        throw new Error(datos.message ?? 'Ocurrió un error inesperado.');
    }

    return datos;
}

export function iniciarTarjetas() {
    const contenedor = document.getElementById('stripe-card-element');
    const formulario = document.getElementById('form-tarjeta-nueva');
    if (!contenedor || !formulario || typeof window.Stripe !== 'function') return;

    const errores = document.getElementById('stripe-card-errors');

    // El formulario vive dentro de un modal oculto por defecto: montar el
    // Element de Stripe ahí lo deja mal renderizado, así que se monta recién
    // cuando el modal se abre (una sola vez).
    let cardElement = null;

    function montarSiHaceFalta() {
        if (cardElement) return;
        const stripe = window.Stripe(formulario.dataset.stripeKey);
        const elements = stripe.elements();
        cardElement = elements.create('card');
        cardElement.mount('#stripe-card-element');
        cardElement.on('change', (evento) => mostrarError(errores, evento.error?.message));
    }

    document.querySelectorAll('[data-modal-abrir="modal-tarjetas"]')
        .forEach((boton) => boton.addEventListener('click', montarSiHaceFalta));

    if (formulario.closest('.modal-overlay')?.classList.contains('is-open')) montarSiHaceFalta();

    formulario.addEventListener('submit', async (evento) => {
        evento.preventDefault();
        if (!cardElement) return;
        mostrarError(errores, '');

        const boton = formulario.querySelector('button[type="submit"]');
        boton.disabled = true;

        try {
            const { client_secret: clientSecret, customer_id: customerId } = await pedirJson(formulario.dataset.setupIntentUrl);

            const resultado = await stripe.confirmCardSetup(clientSecret, {
                payment_method: { card: cardElement },
            });

            if (resultado.error) {
                throw new Error(resultado.error.message);
            }

            await pedirJson(formulario.dataset.guardarUrl, {
                payment_method_id: resultado.setupIntent.payment_method,
                customer_id: customerId,
            });

            window.location.reload();
        } catch (error) {
            mostrarError(errores, error.message);
            boton.disabled = false;
        }
    });
}
