/**
 * Comportamiento del sitio público:
 *  - La barra se vuelve sólida al bajar (en páginas que abren con foto).
 *  - El menú del celular se cierra con Escape o al elegir una opción.
 *  - [data-resaltar]: la declaración se enciende palabra por palabra con el scroll.
 *  - [data-revelar]: la foto se "revela" una vez al entrar en pantalla.
 *  - .anillo.al-verlo: el anillo de la membresía se llena al entrar en pantalla.
 * Sin JavaScript o con movimiento reducido, todo se ve completo desde el inicio.
 */
(function () {
    const reducido = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // Barra sólida al hacer scroll
    const barra = document.getElementById('barra');
    if (barra && document.body.classList.contains('con-foto')) {
        const revisar = () => barra.classList.toggle('solida', window.scrollY > 40);
        revisar();
        window.addEventListener('scroll', revisar, { passive: true });
    }

    // Menú del celular
    const menu = document.getElementById('menu');
    if (menu && window.bootstrap) {
        const colapso = () => bootstrap.Collapse.getOrCreateInstance(menu, { toggle: false });
        menu.addEventListener('click', (ev) => {
            if (ev.target.closest('a') && menu.classList.contains('show')) colapso().hide();
        });
        document.addEventListener('keydown', (ev) => {
            if (ev.key === 'Escape' && menu.classList.contains('show')) {
                colapso().hide();
                document.querySelector('[data-bs-target="#menu"]')?.focus();
            }
        });
    }

    // Declaración que se enciende palabra por palabra
    document.querySelectorAll('[data-resaltar]').forEach((texto) => {
        const palabras = [];
        const partir = (nodo) => {
            [...nodo.childNodes].forEach((hijo) => {
                if (hijo.nodeType === Node.TEXT_NODE) {
                    const trozos = hijo.textContent.split(/(\s+)/);
                    const frag = document.createDocumentFragment();
                    trozos.forEach((t) => {
                        if (t.trim() === '') { frag.append(t); return; }
                        const s = document.createElement('span');
                        s.className = 'palabra';
                        s.textContent = t;
                        palabras.push(s);
                        frag.append(s);
                    });
                    hijo.replaceWith(frag);
                } else if (hijo.nodeType === Node.ELEMENT_NODE) {
                    partir(hijo);
                }
            });
        };
        partir(texto);
        if (reducido) { palabras.forEach((p) => p.classList.add('encendida')); return; }

        let ultimo = -1;
        let pendiente = false;
        const actualizar = () => {
            pendiente = false;
            const r = texto.getBoundingClientRect();
            const alto = window.innerHeight;
            // Empieza cuando el texto entra al 90 % de la pantalla y termina cerca de la mitad
            const inicio = alto * 0.9;
            const fin = alto * 0.5;
            const avance = Math.min(1, Math.max(0, (inicio - r.top) / ((inicio - fin) + r.height * 0.5)));
            const k = Math.round(avance * palabras.length);
            if (k === ultimo) return;
            ultimo = k;
            palabras.forEach((p, i) => p.classList.toggle('encendida', i < k));
        };
        const pedir = () => { if (!pendiente) { pendiente = true; requestAnimationFrame(actualizar); } };
        window.addEventListener('scroll', pedir, { passive: true });
        window.addEventListener('resize', pedir);
        actualizar();
    });

    // Fotos que se revelan y anillos que se llenan, una sola vez al entrar en pantalla
    const unaVez = document.querySelectorAll('[data-revelar], .anillo.al-verlo');
    if (unaVez.length && 'IntersectionObserver' in window && !reducido) {
        const obs = new IntersectionObserver((entradas) => {
            entradas.forEach((en) => {
                if (!en.isIntersecting) return;
                const el = en.target;
                const retraso = Number(el.dataset.retraso || 0);
                setTimeout(() => el.classList.add(el.matches('[data-revelar]') ? 'revelada' : 'visible'), retraso);
                obs.unobserve(el);
            });
        }, { rootMargin: '0px 0px -15% 0px', threshold: 0.2 });
        unaVez.forEach((el) => obs.observe(el));
    } else {
        unaVez.forEach((el) => el.classList.add('revelada', 'visible'));
    }
})();
