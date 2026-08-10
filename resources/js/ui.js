import Swal from 'sweetalert2';

/*
 * Comportamientos de interfaz compartidos por todo el sitio.
 * Todo se resuelve por delegación sobre atributos `data-*`, de modo que las
 * vistas Blade no necesitan manejadores `onclick` en línea.
 */

// ── Menú móvil ────────────────────────────────────────────────────
function iniciarMenuMovil() {
    const boton = document.getElementById('menuToggle');
    const enlaces = document.getElementById('navLinks');
    if (!boton || !enlaces) return;

    boton.addEventListener('click', () => {
        const abierto = enlaces.classList.toggle('is-open');
        boton.setAttribute('aria-expanded', abierto ? 'true' : 'false');
    });
}

// ── Modales ───────────────────────────────────────────────────────
export function abrirModal(id) {
    const modal = document.getElementById(id);
    if (!modal) return;
    modal.classList.add('is-open');
    document.body.style.overflow = 'hidden';
}

export function cerrarModal(id) {
    const modal = document.getElementById(id);
    if (!modal) return;
    modal.classList.remove('is-open');
    document.body.style.overflow = '';
}

function cerrarTodosLosModales() {
    document.querySelectorAll('.modal-overlay.is-open').forEach((modal) => cerrarModal(modal.id));
}

function iniciarModales() {
    document.addEventListener('click', (evento) => {
        const abrir = evento.target.closest('[data-modal-abrir]');
        if (abrir) {
            abrirModal(abrir.dataset.modalAbrir);
            return;
        }

        const cerrar = evento.target.closest('[data-modal-cerrar]');
        if (cerrar) {
            cerrarModal(cerrar.dataset.modalCerrar);
            return;
        }

        // Clic sobre el fondo del modal
        if (evento.target.classList.contains('modal-overlay')) {
            cerrarModal(evento.target.id);
        }
    });

    document.addEventListener('keydown', (evento) => {
        if (evento.key === 'Escape') cerrarTodosLosModales();
    });

    // Un modal marcado como abierto en el servidor reaparece tras un error de validación
    document.querySelectorAll('[data-modal-abierto]').forEach((modal) => abrirModal(modal.id));
}

// ── Mostrar/ocultar contraseña ────────────────────────────────────
function iniciarToggleContrasena() {
    document.addEventListener('click', (evento) => {
        const boton = evento.target.closest('[data-toggle-password]');
        if (!boton) return;

        const contenedor = boton.closest('.password-input-wrapper');
        const campo = contenedor?.querySelector('input');
        if (!campo) return;

        const mostrar = campo.type === 'password';
        campo.type = mostrar ? 'text' : 'password';
        contenedor.classList.toggle('is-visible', mostrar);
        boton.setAttribute('aria-label', mostrar ? 'Ocultar contraseña' : 'Mostrar contraseña');
    });
}

// ── Confirmación de acciones destructivas ─────────────────────────
/*
 * Un formulario con `data-confirmar` pide confirmación antes de enviarse.
 * Los textos se leen de data-confirmar-titulo / -texto / -boton / -tono.
 */
function iniciarConfirmaciones() {
    document.addEventListener('submit', (evento) => {
        const formulario = evento.target.closest('form[data-confirmar]');
        if (!formulario || formulario.dataset.confirmado === 'si') return;

        evento.preventDefault();

        const tono = formulario.dataset.confirmarTono ?? 'peligro';
        const colores = { peligro: '#dc2626', aviso: '#d97706', exito: '#059669' };

        Swal.fire({
            title: formulario.dataset.confirmarTitulo ?? '¿Confirmar acción?',
            text: formulario.dataset.confirmarTexto ?? 'Esta acción no se puede deshacer.',
            icon: tono === 'exito' ? 'question' : 'warning',
            showCancelButton: true,
            confirmButtonColor: colores[tono] ?? colores.peligro,
            cancelButtonColor: '#64748b',
            confirmButtonText: formulario.dataset.confirmarBoton ?? 'Sí, continuar',
            cancelButtonText: 'Cancelar',
        }).then((resultado) => {
            if (!resultado.isConfirmed) {
                // Un <select> con envío automático (ej. cambiar rol) ya cambió su
                // valor visualmente al disparar el 'change'; si se cancela, hay
                // que devolverlo a lo que en verdad sigue guardado.
                formulario.querySelectorAll('[data-valor-original]').forEach((campo) => {
                    campo.value = campo.dataset.valorOriginal;
                });
                return;
            }
            formulario.dataset.confirmado = 'si';
            formulario.submit();
        });
    });
}

// ── Campos de precio según modalidad ──────────────────────────────
/*
 * El <select> de modalidad muestra u oculta los precios que le aplican:
 * venta y arriendo se excluyen entre sí, «ambos» deja los dos visibles.
 */
function sincronizarPrecios(selector) {
    const formulario = selector.closest('form');
    if (!formulario) return;

    formulario.querySelectorAll('[data-precio]').forEach((campo) => {
        const aplica = selector.value === 'ambos' || selector.value === campo.dataset.precio;
        campo.classList.toggle('hidden', !aplica);
        campo.querySelector('input')?.toggleAttribute('required', aplica);
    });
}

function iniciarCamposDePrecio() {
    document.querySelectorAll('[data-modalidad]').forEach((selector) => {
        sincronizarPrecios(selector);
        selector.addEventListener('change', () => sincronizarPrecios(selector));
    });
}

export function iniciarInterfaz() {
    iniciarMenuMovil();
    iniciarModales();
    iniciarToggleContrasena();
    iniciarConfirmaciones();
    iniciarCamposDePrecio();
}
