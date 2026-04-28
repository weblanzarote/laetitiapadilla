const CONTACT_EMAIL = 'contacto@laetitiapadilla.com';
const MAILTO_MAX_LENGTH = 2000;

document.addEventListener('DOMContentLoaded', () => {
    const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
    const nav = document.querySelector('nav');

    if (mobileMenuBtn && nav) {
        mobileMenuBtn.addEventListener('click', () => {
            nav.classList.toggle('open');
            const icon = mobileMenuBtn.querySelector('i');
            if (nav.classList.contains('open')) {
                icon.classList.remove('fa-bars');
                icon.classList.add('fa-times');
            } else {
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
            }
        });
    }

    initContactForm();
});

function initContactForm() {
    const form = document.getElementById('contactForm');
    if (!form) return;

    const feedback = document.getElementById('formFeedback');
    const params = new URLSearchParams(window.location.search);
    const asuntoParam = params.get('asunto');
    if (asuntoParam) {
        const map = {
            clases: 'Clases de Francés',
            interprete: 'Intérprete',
            acompanamiento: 'Acompañamiento cultural'
        };
        const select = document.getElementById('asunto');
        const target = map[asuntoParam];
        if (select && target) {
            for (const opt of select.options) {
                if (opt.value === target) {
                    opt.selected = true;
                    break;
                }
            }
        }
    }

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        if (!form.reportValidity()) return;

        const nombre = document.getElementById('nombre').value.trim();
        const email = document.getElementById('email').value.trim();
        const telefono = document.getElementById('telefono').value.trim();
        const asunto = document.getElementById('asunto').value;
        const mensaje = document.getElementById('mensaje').value.trim();

        const subject = `[Web] ${asunto} — ${nombre}`;
        const bodyLines = [
            `Nombre: ${nombre}`,
            `Email: ${email}`,
            `Teléfono: ${telefono || '—'}`,
            '',
            `Interés: ${asunto}`,
            '',
            mensaje
        ];
        let body = bodyLines.join('\n');

        const buildUrl = () =>
            `mailto:${CONTACT_EMAIL}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;

        let url = buildUrl();
        while (url.length > MAILTO_MAX_LENGTH && body.length > 120) {
            body = body.slice(0, Math.floor(body.length * 0.88)) + '\n\n[…mensaje acortado; completa el resto en el correo si hace falta…]';
            url = buildUrl();
        }

        if (url.length > MAILTO_MAX_LENGTH) {
            if (feedback) {
                feedback.className = 'form-feedback form-feedback--error';
                feedback.textContent =
                    'El mensaje es demasiado largo para abrirlo automáticamente. Acórtalo un poco o escríbeme directamente a ' +
                    CONTACT_EMAIL;
            }
            return;
        }

        openMailtoUrl(url);

        if (feedback) {
            feedback.className = 'form-feedback form-feedback--info';
            feedback.replaceChildren();
            const p = document.createElement('p');
            p.textContent =
                'Debería abrirse tu correo con el mensaje preparado. Si no pasa nada (p. ej. al abrir la web desde un archivo o una vista previa), pulsa el enlace de abajo.';
            feedback.appendChild(p);
            const link = document.createElement('a');
            link.href = url;
            link.className = 'form-feedback__mailto';
            link.textContent = 'Abrir el correo con este enlace';
            link.rel = 'noopener noreferrer';
            link.target = '_top';
            feedback.appendChild(link);
        }
    });
}

/**
 * Evita window.location.href = mailto:… (Chrome y otros lo bloquean con file://
 * o dentro de iframes: orígenes file únicos / políticas de seguridad).
 */
function openMailtoUrl(url) {
    const a = document.createElement('a');
    a.href = url;
    a.rel = 'noopener noreferrer';
    // Con file:// o la página dentro de un iframe (p. ej. vista previa del editor),
    // _self a veces dispara el error de orígenes; _top suele respetar el gesto del usuario.
    a.target = '_top';
    a.style.position = 'fixed';
    a.style.left = '-9999px';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
}
