/**
 * Panel de recepción: marcar avisos de vencimiento como atendidos con AJAX.
 * La fila desaparece y el contador baja sin recargar la página.
 * Sin JavaScript, el formulario se envía normal y la página se recarga.
 */
(function () {
    const tabla = document.getElementById('tabla-vencimientos');
    if (!tabla) return;
    const contador = document.getElementById('contador-vencimientos');
    const alerta = document.getElementById('alerta-vencimientos');

    function mostrarAlerta(tipo, texto) {
        const div = document.createElement('div');
        div.className = 'alert alert-' + tipo + ' py-2 small';
        div.textContent = texto;
        alerta.replaceChildren(div);
    }

    tabla.addEventListener('submit', async (e) => {
        const form = e.target.closest('form.form-atender');
        if (!form) return;
        e.preventDefault();
        const boton = form.querySelector('button');
        boton.disabled = true;

        const r = await peticion(form.action, { method: 'POST', body: new FormData(form) });
        if (!r.ok) {
            boton.disabled = false;
            mostrarAlerta('danger', r.mensaje || 'No se pudo marcar el aviso. Intenta de nuevo.');
            return;
        }

        form.closest('tr').remove();
        contador.textContent = r.pendientes;
        mostrarAlerta('success', r.mensaje);
        if (!tabla.querySelector('tbody tr')) {
            const vacio = document.createElement('p');
            vacio.className = 'p-3 mb-0 text-body-secondary';
            vacio.textContent = 'No hay avisos pendientes.';
            tabla.replaceChildren(vacio);
        }
    });
})();
