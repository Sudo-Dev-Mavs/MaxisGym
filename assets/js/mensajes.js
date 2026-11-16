/**
 * Mensajes de contacto: "Marcar como atendido" con AJAX.
 * En "Sin atender" el mensaje sale de la lista; en "Todos" se queda y muestra
 * quién lo atendió. Sin JavaScript el formulario se envía normal.
 */
(function () {
    const lista = document.getElementById('lista-mensajes');
    if (!lista) return;
    const contador = document.getElementById('contador-mensajes');
    const alerta = document.getElementById('alerta-mensajes');
    const enPendientes = lista.dataset.estado === 'pendientes';

    function mostrarAlerta(tipo, texto) {
        const div = document.createElement('div');
        div.className = 'alert alert-' + tipo + ' py-2';
        div.textContent = texto;
        alerta.replaceChildren(div);
    }

    // Cambia el número de la insignia del menú lateral y de la pestaña
    function actualizarContadores(n) {
        if (contador) contador.textContent = n;
        const menu = document.querySelector('a[href$="empleado/mensajes.php"] .badge');
        if (menu) {
            if (n > 0) {
                menu.firstChild.textContent = n + ' ';
            } else {
                menu.remove();
            }
        }
    }

    lista.addEventListener('submit', async (e) => {
        const form = e.target.closest('form.form-atender-mensaje');
        if (!form) return;
        e.preventDefault();
        const boton = form.querySelector('button');
        boton.disabled = true;

        const r = await peticion(form.action, { method: 'POST', body: new FormData(form) });
        if (!r.ok) {
            boton.disabled = false;
            mostrarAlerta('danger', r.mensaje || 'No se pudo actualizar el mensaje. Intenta de nuevo.');
            return;
        }

        const tarjeta = form.closest('article');
        actualizarContadores(r.pendientes);
        mostrarAlerta('success', r.mensaje);

        if (enPendientes) {
            tarjeta.remove();
            if (!lista.querySelector('article')) {
                const vacio = document.createElement('p');
                vacio.className = 'tarjeta text-body-secondary mb-0 h-auto';
                vacio.textContent = 'No hay mensajes sin atender.';
                lista.replaceChildren(vacio);
            }
            return;
        }

        // Vista "Todos": se reemplazan los botones por el texto de atención
        tarjeta.classList.remove('mensaje-pendiente');
        const acciones = tarjeta.querySelector('.mensaje-acciones');
        const texto = document.createElement('span');
        texto.className = 'mensaje-atendido';
        const icono = document.createElement('i');
        icono.className = 'bi bi-check-circle-fill';
        icono.setAttribute('aria-hidden', 'true');
        texto.append(icono, document.createTextNode(' ' + r.atencion));
        acciones.replaceChildren(texto);
    });
})();
