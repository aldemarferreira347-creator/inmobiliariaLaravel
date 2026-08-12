/* Centro de notificaciones: filtro Todas / No leídas, sin recargar la página. */
export function iniciarFiltroNotificaciones() {
    const lista = document.querySelector('[data-notif-lista]');
    const tabs = document.querySelectorAll('[data-notif-filtro]');
    if (!lista || tabs.length === 0) return;

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            tabs.forEach((otro) => otro.classList.toggle('is-active', otro === tab));
            lista.classList.toggle('solo-no-leidas', tab.dataset.notifFiltro === 'no-leidas');
        });
    });
}
