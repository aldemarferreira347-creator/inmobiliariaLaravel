/*
 * Perfil: alterna entre la vista de lectura y el formulario de edición, y
 * previsualiza la foto antes de enviarla.
 */

function iniciarEdicion() {
    const boton = document.querySelector('[data-perfil-editar]');
    const lectura = document.getElementById('vista-lectura');
    const formulario = document.getElementById('form-editar');
    if (!boton || !lectura || !formulario) return;

    boton.addEventListener('click', () => {
        const editando = formulario.classList.toggle('hidden') === false;
        lectura.classList.toggle('hidden', editando);
        boton.classList.toggle('hidden', editando);
    });

    // Si la validación falló, el formulario se muestra abierto de entrada
    if (formulario.dataset.abierto === 'si') boton.click();
}

function iniciarFoto() {
    const campo = document.getElementById('foto-input');
    const formulario = document.getElementById('form-foto');
    if (!campo || !formulario) return;

    campo.addEventListener('change', () => {
        const archivo = campo.files?.[0];
        if (!archivo) return;

        const avatar = document.getElementById('avatar-img');
        if (avatar) avatar.src = URL.createObjectURL(archivo);
        formulario.submit();
    });
}

export function iniciarPerfil() {
    iniciarEdicion();
    iniciarFoto();
}
