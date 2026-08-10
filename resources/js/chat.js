/*
 * Chat: envío sin recargar, carga del tramo anterior del hilo y sondeo de
 * mensajes nuevos. El servidor sigue aceptando el envío por formulario normal,
 * así que sin JavaScript la conversación continúa funcionando.
 */

const INTERVALO_SONDEO_MS = 15_000;
const UMBRAL_AUTOSCROLL_PX = 80;

function estaAbajo(caja) {
    return caja.scrollHeight - caja.scrollTop - caja.clientHeight < UMBRAL_AUTOSCROLL_PX;
}

function irAbajo(caja) {
    caja.scrollTop = caja.scrollHeight;
}

function ultimoId(caja) {
    const burbujas = caja.querySelectorAll('[data-msg-id]');
    return burbujas.length > 0 ? Number(burbujas[burbujas.length - 1].dataset.msgId) : 0;
}

function primerId(caja) {
    return Number(caja.querySelector('[data-msg-id]')?.dataset.msgId ?? 0);
}

function crearBurbuja(mensaje) {
    const grupo = document.createElement('div');
    grupo.className = `msg-group ${mensaje.propio ? 'outgoing' : 'incoming'}`;
    if (mensaje.id) grupo.dataset.msgId = String(mensaje.id);

    const autor = document.createElement('span');
    autor.className = 'msg-sender-label';
    autor.textContent = mensaje.emisor;
    grupo.append(autor);

    if (mensaje.adjunto) {
        const enlace = document.createElement('a');
        enlace.className = 'msg-attachment';
        enlace.href = mensaje.adjunto;
        enlace.target = '_blank';
        enlace.rel = 'noopener';

        const imagen = document.createElement('img');
        imagen.src = mensaje.adjunto;
        imagen.alt = 'Imagen adjunta';
        enlace.append(imagen);
        grupo.append(enlace);
    }

    if (mensaje.contenido) {
        const burbuja = document.createElement('div');
        burbuja.className = 'msg-bubble';
        burbuja.textContent = mensaje.contenido;
        grupo.append(burbuja);
    }

    const hora = document.createElement('span');
    hora.className = 'msg-time';
    hora.textContent = mensaje.hora ?? '';
    grupo.append(hora);

    return grupo;
}

// ── Filtro de la lista de conversaciones ──────────────────────────
function iniciarFiltro() {
    const campo = document.querySelector('[data-filtro-chats]');
    if (!campo) return;

    campo.addEventListener('input', () => {
        const consulta = campo.value.trim().toLowerCase();

        document.querySelectorAll('.chat-item').forEach((item) => {
            item.classList.toggle('hidden', !item.dataset.nombre.includes(consulta));
        });
    });
}

// ── Envío del mensaje ─────────────────────────────────────────────
function iniciarEnvio(caja) {
    const formulario = document.querySelector('[data-chat-form]');
    if (!formulario) return;

    const texto = formulario.querySelector('[data-chat-texto]');
    const adjunto = formulario.querySelector('[data-adjunto-input]');

    formulario.querySelector('[data-adjuntar]')?.addEventListener('click', () => adjunto.click());
    adjunto?.addEventListener('change', () => formulario.requestSubmit());

    // Enter envía, Mayús+Enter salta de línea
    texto?.addEventListener('keydown', (evento) => {
        if (evento.key !== 'Enter' || evento.shiftKey) return;
        evento.preventDefault();
        formulario.requestSubmit();
    });

    formulario.addEventListener('submit', async (evento) => {
        if (!texto.value.trim() && !adjunto.files.length) {
            evento.preventDefault();
            return;
        }

        evento.preventDefault();

        const datos = new FormData(formulario);
        const provisional = crearBurbuja({ contenido: texto.value.trim(), propio: true, emisor: 'Tú' });
        provisional.classList.add('msg-pending');
        caja.append(provisional);
        irAbajo(caja);

        texto.value = '';
        adjunto.value = '';

        try {
            const respuesta = await fetch(formulario.action, {
                method: 'POST',
                body: datos,
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });

            if (!respuesta.ok) throw new Error('Envío rechazado');

            // El mensaje real llega con su id en el siguiente sondeo
            provisional.remove();
            await consultarNuevos(caja);
        } catch {
            provisional.classList.remove('msg-pending');
            provisional.classList.add('msg-error');
            provisional.title = 'No se pudo enviar. Vuelve a intentarlo.';
        }
    });
}

// ── Tramo anterior del hilo ───────────────────────────────────────
function iniciarCargarMas(caja) {
    const contenedor = caja.querySelector('[data-cargar-mas-contenedor]');
    const boton = caja.querySelector('[data-cargar-mas]');
    if (!boton) return;

    boton.addEventListener('click', async () => {
        boton.disabled = true;

        const respuesta = await fetch(`${caja.dataset.urlAnteriores}?antes_de=${primerId(caja)}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        const { mensajes, hay_mas: hayMas } = await respuesta.json();

        const alturaPrevia = caja.scrollHeight;
        mensajes.reverse().forEach((mensaje) => contenedor.after(crearBurbuja(mensaje)));
        caja.scrollTop = caja.scrollHeight - alturaPrevia;

        boton.disabled = false;
        contenedor.classList.toggle('hidden', !hayMas);
    });
}

// ── Sondeo de mensajes nuevos ─────────────────────────────────────
async function consultarNuevos(caja) {
    const respuesta = await fetch(`${caja.dataset.urlNuevos}?desde=${ultimoId(caja)}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
    });
    const { mensajes } = await respuesta.json();
    if (mensajes.length === 0) return;

    const abajo = estaAbajo(caja);

    mensajes
        .filter((mensaje) => !caja.querySelector(`[data-msg-id="${mensaje.id}"]`))
        .forEach((mensaje) => caja.append(crearBurbuja(mensaje)));

    if (abajo) irAbajo(caja);
}

export function iniciarChat() {
    iniciarFiltro();

    const caja = document.querySelector('[data-chat]');
    if (!caja) return;

    irAbajo(caja);
    iniciarEnvio(caja);
    iniciarCargarMas(caja);

    // El sondeo se detiene mientras la pestaña no está a la vista
    setInterval(() => {
        if (!document.hidden) consultarNuevos(caja);
    }, INTERVALO_SONDEO_MS);
}
