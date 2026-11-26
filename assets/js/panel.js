/**
 * Tablas de los paneles en celular: cada fila se convierte en una tarjeta.
 * Copia el texto de cada encabezado (<th>) a sus celdas como data-etiqueta,
 * y el CSS (.tabla-apilable) lo muestra a la izquierda de cada dato.
 * También etiqueta las filas que se agregan después por AJAX (pagos, búsquedas).
 */
(function () {
    const tablas = document.querySelectorAll('.tarjeta-tabla table:not(.matriz-permisos):not(.no-apilar)');

    function etiquetar(tabla) {
        const encabezados = [...tabla.querySelectorAll('thead th')].map((th) => th.textContent.trim());
        if (!encabezados.length) return;
        tabla.querySelectorAll('tbody tr').forEach((tr) => {
            const celdas = [...tr.children];
            // Filas con celdas combinadas (mensajes de "sin resultados") se quedan como están
            if (celdas.length !== encabezados.length) return;
            celdas.forEach((celda, i) => {
                if (!celda.hasAttribute('data-etiqueta')) celda.setAttribute('data-etiqueta', encabezados[i]);
            });
        });
    }

    tablas.forEach((tabla) => {
        tabla.classList.add('tabla-apilable');
        etiquetar(tabla);
        const cuerpo = tabla.querySelector('tbody');
        if (cuerpo && 'MutationObserver' in window) {
            new MutationObserver(() => etiquetar(tabla)).observe(cuerpo, { childList: true });
        }
    });
})();
