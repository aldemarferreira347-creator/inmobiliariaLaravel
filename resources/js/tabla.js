import * as XLSX from 'xlsx';

/*
 * Convierte una <table data-enhance-table> en una tabla con búsqueda, orden por
 * columna, paginación y exportación a Excel, sin dependencias de terceros en el
 * DOM. Se configura por atributos:
 *   data-page-size="10"      filas por página iniciales
 *   data-export-name="..."   nombre de la entidad exportada
 *   data-no-export           oculta el botón de exportar
 *   th[data-no-sort]         columna no ordenable
 *   th[data-no-export-col]   columna excluida de la exportación
 */

const ICONOS = {
    buscar: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>',
    anterior: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4"><path d="m15 18-6-6 6-6"/></svg>',
    siguiente: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4"><path d="m9 18 6-6-6-6"/></svg>',
    excel: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>',
};

function textoBuscable(fila) {
    const titulos = Array.from(fila.querySelectorAll('[title]'))
        .map((elemento) => elemento.getAttribute('title'))
        .join(' ');

    return `${fila.innerText} ${titulos}`.toLowerCase();
}

// Convierte «$1.250.000» en 1250000; devuelve null si la celda no es numérica
function comoNumero(texto) {
    const limpio = texto
        .replace(/[^0-9,.-]/g, '')
        .replace(/\.(?=\d{3}(\D|$))/g, '')
        .replace(',', '.');
    const numero = Number.parseFloat(limpio);

    return Number.isNaN(numero) ? null : numero;
}

function mejorarTabla(tabla) {
    const cuerpo = tabla.tBodies[0];
    if (!cuerpo) return;

    const filas = Array.from(cuerpo.rows).filter((fila) => !fila.dataset.tblEmpty);
    if (filas.length === 0) return;

    filas.forEach((fila) => {
        fila.dataset.busqueda = textoBuscable(fila);
    });

    const cabeceras = tabla.tHead ? Array.from(tabla.tHead.rows[0].cells) : [];
    const contenedor = tabla.closest('.table-responsive') ?? tabla.closest('.table-scroll') ?? tabla;
    const tamanoInicial = Number.parseInt(tabla.dataset.pageSize ?? '10', 10);

    const estado = { filtro: '', pagina: 1, tamano: tamanoInicial, columna: -1, direccion: 1 };

    const visibles = () => (estado.filtro
        ? filas.filter((fila) => fila.dataset.busqueda.includes(estado.filtro))
        : filas);

    // ── Barra superior ────────────────────────────────────────────
    const permiteExportar = !tabla.hasAttribute('data-no-export');
    const barra = document.createElement('div');
    barra.className = 'tbl-toolbar';
    barra.innerHTML = `
        <label class="tbl-search">${ICONOS.buscar}
            <input type="search" placeholder="Buscar..." aria-label="Buscar en la tabla">
        </label>
        <div class="tbl-tools">
            ${permiteExportar ? `<button type="button" class="tbl-btn" data-csv>${ICONOS.excel} Excel</button>` : ''}
            <select class="tbl-size" aria-label="Filas por página">
                <option value="10">10</option><option value="25">25</option>
                <option value="50">50</option><option value="0">Todas</option>
            </select>
        </div>`;
    contenedor.parentNode.insertBefore(barra, contenedor);
    barra.querySelector('.tbl-size').value = String(tamanoInicial);

    // ── Pie de paginación ─────────────────────────────────────────
    const pie = document.createElement('div');
    pie.className = 'tbl-pagination';
    pie.innerHTML = `
        <span class="tbl-count"></span>
        <div class="tbl-pager">
            <button type="button" class="tbl-btn" data-prev aria-label="Página anterior">${ICONOS.anterior} Anterior</button>
            <span class="tbl-pageinfo"></span>
            <button type="button" class="tbl-btn" data-next aria-label="Página siguiente">Siguiente ${ICONOS.siguiente}</button>
        </div>`;
    contenedor.parentNode.insertBefore(pie, contenedor.nextSibling);

    function ordenar() {
        if (estado.columna < 0) return;

        filas.sort((a, b) => {
            const valorA = a.cells[estado.columna]?.textContent.trim() ?? '';
            const valorB = b.cells[estado.columna]?.textContent.trim() ?? '';
            const numeroA = comoNumero(valorA);
            const numeroB = comoNumero(valorB);

            const comparacion = numeroA !== null && numeroB !== null
                ? numeroA - numeroB
                : valorA.localeCompare(valorB, 'es', { numeric: true, sensitivity: 'base' });

            return comparacion * estado.direccion;
        });

        filas.forEach((fila) => cuerpo.appendChild(fila));
    }

    function pintar() {
        const listado = visibles();
        const total = listado.length;
        const tamano = estado.tamano === 0 ? total || 1 : estado.tamano;
        const paginas = Math.max(1, Math.ceil(total / tamano));
        estado.pagina = Math.min(Math.max(1, estado.pagina), paginas);

        const inicio = (estado.pagina - 1) * tamano;
        const fin = estado.tamano === 0 ? total : Math.min(inicio + tamano, total);

        filas.forEach((fila) => { fila.style.display = 'none'; });
        listado.slice(inicio, fin).forEach((fila) => { fila.style.display = ''; });

        pie.querySelector('.tbl-count').textContent =
            `Mostrando registros del ${total === 0 ? 0 : inicio + 1} al ${fin} de un total de ${total} registros`;
        pie.querySelector('.tbl-pageinfo').textContent = `Página ${estado.pagina} de ${paginas}`;
        pie.querySelector('[data-prev]').disabled = estado.pagina <= 1;
        pie.querySelector('[data-next]').disabled = estado.pagina >= paginas;
    }

    cabeceras.forEach((cabecera, indice) => {
        if (cabecera.hasAttribute('data-no-sort')) return;

        cabecera.classList.add('th-sortable');
        cabecera.setAttribute('role', 'button');
        cabecera.setAttribute('tabindex', '0');

        const alOrdenar = () => {
            estado.direccion = estado.columna === indice ? -estado.direccion : 1;
            estado.columna = indice;
            cabeceras.forEach((otra) => otra.classList.remove('th-asc', 'th-desc'));
            cabecera.classList.add(estado.direccion === 1 ? 'th-asc' : 'th-desc');
            estado.pagina = 1;
            ordenar();
            pintar();
        };

        cabecera.addEventListener('click', alOrdenar);
        cabecera.addEventListener('keydown', (evento) => {
            if (evento.key !== 'Enter' && evento.key !== ' ') return;
            evento.preventDefault();
            alOrdenar();
        });
    });

    barra.querySelector('input').addEventListener('input', (evento) => {
        estado.filtro = evento.target.value.trim().toLowerCase();
        estado.pagina = 1;
        pintar();
    });

    barra.querySelector('.tbl-size').addEventListener('change', (evento) => {
        estado.tamano = Number.parseInt(evento.target.value, 10);
        estado.pagina = 1;
        pintar();
    });

    barra.querySelector('[data-csv]')?.addEventListener('click', () => {
        exportarExcel(tabla, cabeceras, visibles());
    });

    pie.querySelector('[data-prev]').addEventListener('click', () => { estado.pagina -= 1; pintar(); });
    pie.querySelector('[data-next]').addEventListener('click', () => { estado.pagina += 1; pintar(); });

    pintar();
}

// Exporta las filas visibles a .xlsx con encabezado de marca y fila de totales
function exportarExcel(tabla, cabeceras, filas) {
    const indices = cabeceras.reduce((acumulado, cabecera, indice) => {
        if (!cabecera.hasAttribute('data-no-export-col')) acumulado.push(indice);
        return acumulado;
    }, []);

    const titulos = indices.map((indice) => cabeceras[indice].textContent.trim());
    const datos = filas.map((fila) => indices.map(
        (indice) => fila.cells[indice]?.textContent.replace(/\s+/g, ' ').trim() ?? ''
    ));

    const esNumerica = titulos.map((_, columna) => datos
        .slice(0, 5)
        .some((fila) => comoNumero(String(fila[columna] ?? '')) !== null));

    const totales = titulos.map((_, columna) => {
        if (columna === 0) return 'TOTAL';
        if (!esNumerica[columna]) return '';
        return datos.reduce((suma, fila) => suma + (comoNumero(String(fila[columna] ?? '')) ?? 0), 0);
    });

    const entidad = tabla.dataset.exportName ?? 'datos';
    const etiqueta = entidad.charAt(0).toUpperCase() + entidad.slice(1);
    const momento = new Date().toLocaleString('es-CO', {
        day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit',
    });

    const vacias = Array(titulos.length - 1).fill('');
    const hoja = XLSX.utils.aoa_to_sheet([
        [`INMOBILIARIA GARCÍA — ${etiqueta.toUpperCase()}`, ...vacias],
        [`Sistema de Gestión Inmobiliaria · Exportado: ${momento}`, ...vacias],
        Array(titulos.length).fill(''),
        titulos,
        ...datos,
        totales,
    ]);

    if (titulos.length > 1) {
        hoja['!merges'] = [0, 1, 2].map((fila) => ({
            s: { r: fila, c: 0 },
            e: { r: fila, c: titulos.length - 1 },
        }));
    }

    hoja['!cols'] = titulos.map((titulo, columna) => {
        const ancho = datos.reduce(
            (maximo, fila) => Math.max(maximo, String(fila[columna] ?? '').length),
            titulo.length
        );
        return { wch: Math.min(Math.max(ancho + 3, 10), 52) };
    });

    const libro = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(libro, hoja, etiqueta.slice(0, 31));
    XLSX.writeFile(libro, `${entidad}_${new Date().toISOString().slice(0, 10)}.xlsx`);
}

export function iniciarTablas() {
    document.querySelectorAll('table[data-enhance-table]').forEach(mejorarTabla);
}
