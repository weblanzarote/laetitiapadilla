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
    const submitBtn = form.querySelector('button[type="submit"]');
    const csrfInput = document.getElementById('csrf_token');
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

    const setFeedback = (type, message) => {
        if (!feedback) return;
        feedback.className = `form-feedback form-feedback--${type}`;
        feedback.textContent = message;
    };

    const setBusy = (busy) => {
        if (!submitBtn) return;
        submitBtn.disabled = busy;
        submitBtn.dataset.originalText = submitBtn.dataset.originalText || submitBtn.textContent;
        submitBtn.textContent = busy ? 'Enviando…' : submitBtn.dataset.originalText;
    };

    const fetchCsrfToken = async () => {
        if (!csrfInput) return;
        try {
            const res = await fetch('api/contact.php?action=token', {
                method: 'GET',
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin'
            });
            const data = await res.json().catch(() => null);
            if (!res.ok || !data || !data.ok || !data.token) throw new Error('No token');
            csrfInput.value = data.token;
        } catch {
            // Si no se puede cargar el token, dejamos que el formulario haga submit clásico (action=api/contact.php)
            csrfInput.value = '';
        }
    };

    fetchCsrfToken();

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!form.reportValidity()) return;

        // Si no hay token (p. ej. vista previa file://), hacemos submit clásico
        if (csrfInput && !csrfInput.value) {
            form.submit();
            return;
        }

        setBusy(true);
        setFeedback('info', 'Enviando…');
        try {
            const formData = new FormData(form);
            const res = await fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            });
            const data = await res.json().catch(() => null);

            if (!res.ok || !data) {
                const err = (data && data.error) ? data.error : 'No se pudo enviar. Inténtalo de nuevo.';
                throw new Error(err);
            }
            if (!data.ok) {
                throw new Error(data.error || 'No se pudo enviar. Revisa los campos.');
            }

            setFeedback('ok', data.warning ? `Mensaje enviado. ${data.warning}` : 'Mensaje enviado. Gracias, te responderé lo antes posible.');
            form.reset();
            await fetchCsrfToken();
        } catch (err) {
            setFeedback('error', err instanceof Error ? err.message : 'No se pudo enviar. Inténtalo de nuevo.');
            // Reintentamos token por si caducó
            await fetchCsrfToken();
        } finally {
            setBusy(false);
        }
    });
}
