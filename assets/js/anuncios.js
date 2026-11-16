/**
 * Publicar avisos con AJAX: el aviso nuevo aparece arriba de la lista sin recargar.
 */
(function () {
    const form = document.getElementById('form-aviso');
    if (!form) return;
    const lista = document.getElementById('lista-avisos');
    const alerta = document.getElementById('alerta-aviso');
    const boton = document.getElementById('boton-aviso');

    function mostrarAlerta(tipo, texto) {
        const div = document.createElement('div');
        div.className = 'alert alert-' + tipo;
        div.textContent = texto;
        alerta.replaceChildren(div);
    }

    function crearAviso(a) {
        const art = document.createElement('article');
        art.className = 'aviso fila-nueva' + (a.importante ? ' aviso-importante' : '');

        const cabecera = document.createElement('div');
        cabecera.className = 'd-flex justify-content-between gap-3';
        const h3 = document.createElement('h3');
        h3.className = 'aviso-titulo';
        if (a.importante) {
            const i = document.createElement('i');
            i.className = 'bi bi-exclamation-triangle-fill';
            i.setAttribute('aria-hidden', 'true');
            h3.append(i, document.createTextNode(' '));
        }
        h3.append(document.createTextNode(a.titulo));
        const insignia = document.createElement('span');
        insignia.className = 'badge align-self-start ' + a.clase;
        insignia.textContent = a.estado;
        cabecera.append(h3, insignia);

        const texto = document.createElement('p');
        texto.className = 'aviso-texto';
        texto.style.whiteSpace = 'pre-line';
        texto.textContent = a.contenido;

        const meta = document.createElement('p');
        meta.className = 'aviso-meta';
        meta.textContent = a.para + '. Publicó ' + a.autor + ' el ' + a.publicado + '. Expira: ' + a.expira + '.';

        art.append(cabecera, texto, meta);
        return art;
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        form.querySelectorAll('[data-error]').forEach((el) => { el.textContent = ''; });
        form.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
        boton.disabled = true;
        const r = await peticion(form.action, { method: 'POST', body: new FormData(form) });
        boton.disabled = false;

        if (r.ok) {
            mostrarAlerta('success', r.mensaje + ' Para eliminarlo, recarga la página.');
            document.getElementById('sin-avisos')?.remove();
            lista.prepend(crearAviso(r.aviso));
            form.reset();
            return;
        }
        mostrarAlerta('danger', r.mensaje || 'No se pudo publicar el aviso.');
        Object.entries(r.errores || {}).forEach(([campo, texto]) => {
            const control = form.querySelector('[name="' + campo + '"]');
            if (control) control.classList.add('is-invalid');
            const destino = form.querySelector('[data-error="' + campo + '"]');
            if (destino) destino.textContent = texto;
        });
    });
})();
