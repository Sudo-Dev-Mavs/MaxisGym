/**
 * Buscador de socios con AJAX (lo usan Registrar pagos y Consultar socios).
 *
 *   buscadorSocios({
 *       input:  document.getElementById('buscar-socio'),
 *       lista:  document.getElementById('resultados-socio'),
 *       api:    'URL de api/buscar_socios.php',
 *       alElegir: (socio) => { ... },          // opcional: muestra lista desplegable
 *       alRecibir: (socios, total, q) => { },  // opcional: maneja los resultados (sin lista)
 *   });
 *
 * Espera 250 ms después de la última tecla para no hacer una petición por letra.
 * Los datos se insertan con textContent (nunca innerHTML) para evitar XSS.
 */
function buscadorSocios(opciones) {
    const { input, lista, api, alElegir, alRecibir } = opciones;
    let temporizador = null;
    let ultimaConsulta = '';

    const ocultar = () => { if (lista) { lista.hidden = true; lista.replaceChildren(); } };

    async function buscar() {
        const q = input.value.trim();
        ultimaConsulta = q;
        if (q === '') {
            ocultar();
            if (alRecibir) alRecibir([], 0, q);
            return;
        }
        const r = await peticion(api + '?q=' + encodeURIComponent(q));
        if (q !== ultimaConsulta) return; // llegó tarde una respuesta vieja
        if (!r.ok) {
            if (alRecibir) alRecibir([], 0, q, r.mensaje);
            return;
        }
        if (alRecibir) alRecibir(r.socios, r.total, q);
        if (lista && alElegir) mostrarLista(r.socios);
    }

    function mostrarLista(socios) {
        lista.replaceChildren();
        if (!socios.length) {
            const vacio = document.createElement('div');
            vacio.className = 'list-group-item text-body-secondary small';
            vacio.textContent = 'Ningún socio activo coincide.';
            lista.append(vacio);
        }
        socios.forEach((s) => {
            const boton = document.createElement('button');
            boton.type = 'button';
            boton.className = 'list-group-item list-group-item-action';
            boton.setAttribute('role', 'option');
            const nombre = document.createElement('strong');
            nombre.textContent = s.nombre;
            const detalle = document.createElement('span');
            detalle.className = 'd-block small text-body-secondary';
            detalle.textContent = '#' + s.id + ', ' + s.email + (s.plan ? ' · ' + s.plan + ' (' + s.estado + ')' : ' · sin membresía');
            boton.append(nombre, detalle);
            boton.addEventListener('click', () => {
                ocultar();
                input.value = '';
                alElegir(s);
            });
            lista.append(boton);
        });
        lista.hidden = false;
    }

    input.addEventListener('input', () => {
        clearTimeout(temporizador);
        temporizador = setTimeout(buscar, 250);
    });
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') ocultar();
        if (e.key === 'ArrowDown' && lista && !lista.hidden) {
            e.preventDefault();
            lista.querySelector('button')?.focus();
        }
    });
    if (lista) {
        lista.addEventListener('keydown', (e) => {
            const actual = document.activeElement;
            if (e.key === 'ArrowDown') { e.preventDefault(); actual.nextElementSibling?.focus(); }
            if (e.key === 'ArrowUp') { e.preventDefault(); (actual.previousElementSibling || input).focus(); }
            if (e.key === 'Escape') { ocultar(); input.focus(); }
        });
        document.addEventListener('click', (e) => {
            if (!lista.contains(e.target) && e.target !== input) ocultar();
        });
    }
    return { buscar };
}
