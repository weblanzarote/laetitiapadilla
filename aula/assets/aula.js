/* Aula virtual · comportamiento común */
(function () {
    'use strict';

    var $ = function (sel, ctx) { return (ctx || document).querySelector(sel); };
    var $$ = function (sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); };
    var csrf = ($('meta[name="csrf-token"]') || {}).content || '';
    var base = ($('meta[name="aula-base"]') || {}).content || './';

    function route(r, params) {
        var q = 'r=' + encodeURIComponent(r);
        Object.keys(params || {}).forEach(function (k) { q += '&' + encodeURIComponent(k) + '=' + encodeURIComponent(params[k]); });
        return base + 'index.php?' + q;
    }

    // ─── Menú y desplegables ──────────────────────────────────────

    var toggle = $('.nav-toggle');
    if (toggle) {
        toggle.addEventListener('click', function () {
            var nav = $('#mainnav');
            var open = nav.classList.toggle('open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            toggle.innerHTML = open ? '<i class="fa-solid fa-xmark"></i>' : '<i class="fa-solid fa-bars"></i>';
        });
    }

    document.addEventListener('click', function (e) {
        $$('details.dropdown[open]').forEach(function (d) {
            if (!d.contains(e.target)) d.removeAttribute('open');
        });
    });

    // ─── Utilidades de formularios ───────────────────────────────

    document.addEventListener('submit', function (e) {
        var form = e.target;
        var msg = form.getAttribute('data-confirm');
        if (msg && !window.confirm(msg)) {
            e.preventDefault();
            return;
        }
        if (e.defaultPrevented) return;
        if (hasPendingUploads(form)) {
            e.preventDefault();
            uploadThenSubmit(form);
            return;
        }
        if (form.hasAttribute('data-keep-enabled')) return;
        $$('button[type="submit"], button:not([type])', form).forEach(function (b) {
            setTimeout(function () { b.disabled = true; }, 0);
        });
    });

    document.addEventListener('click', function (e) {
        var copyBtn = e.target.closest('[data-copy]');
        if (copyBtn) {
            var text = copyBtn.getAttribute('data-copy');
            var done = function () {
                copyBtn.classList.add('copied');
                var old = copyBtn.getAttribute('title');
                copyBtn.setAttribute('title', '¡Copiado!');
                setTimeout(function () { copyBtn.classList.remove('copied'); if (old) copyBtn.setAttribute('title', old); }, 1600);
            };
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(done, function () { window.prompt('Copia este texto:', text); });
            } else {
                window.prompt('Copia este texto:', text);
            }
        }

        var tgl = e.target.closest('[data-toggle]');
        if (tgl) {
            var target = $(tgl.getAttribute('data-toggle'));
            if (target) {
                target.hidden = !target.hidden;
                if (!target.hidden) {
                    var first = $('input:not([type=hidden]), textarea', target);
                    if (first) first.focus();
                }
            }
        }

        // Enlaces que abren un <details> de la página (p. ej. «Nuevo apartado»)
        var opener = e.target.closest('[data-open]');
        if (opener) {
            var box = $(opener.getAttribute('data-open'));
            if (box) {
                e.preventDefault();
                box.open = true;
                box.scrollIntoView({ behavior: 'smooth', block: 'center' });
                var field = $('input:not([type=hidden])', box);
                if (field) setTimeout(function () { field.focus({ preventScroll: true }); }, 300);
            }
        }

        var pw = e.target.closest('.password-toggle');
        if (pw) {
            var input = pw.parentNode.querySelector('input');
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            pw.innerHTML = show ? '<i class="fa-solid fa-eye-slash"></i>' : '<i class="fa-solid fa-eye"></i>';
        }

        var rate = e.target.closest('[data-rate]');
        if (rate) {
            var box = rate.closest('.audio-box');
            var audio = box && $('audio', box);
            if (audio) {
                audio.playbackRate = parseFloat(rate.getAttribute('data-rate'));
                $$('[data-rate]', box).forEach(function (b) { b.classList.toggle('active', b === rate); });
            }
        }
    });

    // Textareas que crecen solas y Ctrl+Intro para enviar
    $$('textarea[data-autosize]').forEach(function (t) {
        var fit = function () {
            t.style.height = '';
            if (t.scrollHeight > t.clientHeight) t.style.height = Math.min(t.scrollHeight + 4, 420) + 'px';
        };
        t.addEventListener('input', fit);
        fit();
    });
    $$('textarea[data-ctrl-enter]').forEach(function (t) {
        t.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                e.preventDefault();
                if (t.form.requestSubmit) t.form.requestSubmit(); else t.form.submit();
            }
        });
    });

    // Título automático a partir del nombre del archivo
    $$('input[type=file]').forEach(function (inp) {
        inp.addEventListener('change', function () {
            var title = $('[data-title-target]');
            if (title && !title.value && inp.files.length) {
                title.value = inp.files[0].name.replace(/\.[^.]+$/, '').replace(/[_-]+/g, ' ').trim();
            }
            var names = inp.closest('form') && $('[data-file-names]', inp.closest('form'));
            if (names) {
                names.textContent = Array.prototype.map.call(inp.files, function (f) { return f.name; }).join(', ');
            }
        });
    });

    // ─── Subidas por partes ──────────────────────────────────────

    function hasPendingUploads(form) {
        return $$('input[type=file][data-chunked]', form).some(function (i) { return !i.disabled && i.files && i.files.length; });
    }

    function randomId() {
        var a = new Uint8Array(16);
        (window.crypto || window.msCrypto).getRandomValues(a);
        return Array.prototype.map.call(a, function (b) { return ('0' + b.toString(16)).slice(-2); }).join('');
    }

    function formatSize(n) {
        return n > 1048576 ? (n / 1048576).toFixed(1).replace('.', ',') + ' MB' : Math.round(n / 1024) + ' KB';
    }

    function uploadFile(file, chunkSize, onProgress) {
        var id = randomId();
        var total = Math.max(1, Math.ceil(file.size / chunkSize));
        var index = 0;
        function sendChunk(attempt) {
            var fd = new FormData();
            fd.append('upload_id', id);
            fd.append('index', index);
            fd.append('total', total);
            fd.append('name', file.name);
            fd.append('size', file.size);
            fd.append('chunk', file.slice(index * chunkSize, Math.min(file.size, (index + 1) * chunkSize)), 'chunk');
            return fetch(route('upload/chunk'), {
                method: 'POST', body: fd, credentials: 'same-origin',
                headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' }
            }).then(function (r) {
                return r.json().catch(function () { return { ok: false, error: 'Respuesta inesperada del servidor (' + r.status + ').' }; });
            }).then(function (res) {
                if (!res.ok) throw new Error(res.error || 'Error al subir');
                index++;
                onProgress(Math.min(1, index / total));
                if (index < total) return sendChunk(0);
                return id;
            }).catch(function (err) {
                if (attempt < 2 && !/tamaño|válid|orden|perdió/i.test(err.message)) {
                    return new Promise(function (ok) { setTimeout(ok, 1200); }).then(function () { return sendChunk(attempt + 1); });
                }
                throw err;
            });
        }
        return sendChunk(0);
    }

    function uploadThenSubmit(form) {
        var inputs = $$('input[type=file][data-chunked]', form).filter(function (i) { return !i.disabled && i.files && i.files.length; });
        var buttons = $$('button[type="submit"], button:not([type])', form);
        buttons.forEach(function (b) { b.disabled = true; });

        var jobs = [];
        for (var k = 0; k < inputs.length; k++) {
            var max = parseInt(inputs[k].getAttribute('data-max-size'), 10) || 0;
            for (var f = 0; f < inputs[k].files.length; f++) {
                var file = inputs[k].files[f];
                if (max && file.size > max) {
                    buttons.forEach(function (b) { b.disabled = false; });
                    window.alert('«' + file.name + '» es demasiado grande (máximo ' + formatSize(max) + ').');
                    return;
                }
                jobs.push({ input: inputs[k], file: file });
            }
        }

        var box = document.createElement('div');
        box.className = 'upload-progress';
        var label = document.createElement('div');
        var bar = document.createElement('div');
        bar.className = 'progress';
        bar.innerHTML = '<span style="width:0%"></span>';
        box.appendChild(label);
        box.appendChild(bar);
        inputs[0].parentNode.appendChild(box);
        var totalBytes = jobs.reduce(function (s, j) { return s + j.file.size; }, 0) || 1;
        var doneBytes = 0;

        var chain = Promise.resolve();
        jobs.forEach(function (job, n) {
            chain = chain.then(function () {
                var chunk = parseInt(job.input.getAttribute('data-chunk-size'), 10) || 1048576;
                label.textContent = 'Subiendo «' + job.file.name + '» (' + (n + 1) + ' de ' + jobs.length + ')…';
                return uploadFile(job.file, chunk, function (p) {
                    bar.firstChild.style.width = Math.round((doneBytes + p * job.file.size) * 100 / totalBytes) + '%';
                }).then(function (token) {
                    doneBytes += job.file.size;
                    var h = document.createElement('input');
                    h.type = 'hidden';
                    h.name = '_uploads[' + job.input.getAttribute('data-chunked') + '][]';
                    h.value = token;
                    form.appendChild(h);
                });
            });
        });

        chain.then(function () {
            label.textContent = 'Archivos subidos. Guardando…';
            bar.firstChild.style.width = '100%';
            inputs.forEach(function (i) { i.disabled = true; });
            syncEditors(form);
            HTMLFormElement.prototype.submit.call(form);
        }).catch(function (err) {
            label.textContent = '';
            box.remove();
            buttons.forEach(function (b) { b.disabled = false; });
            window.alert('No se pudo subir el archivo: ' + err.message);
        });
    }

    // ─── Editor de texto enriquecido (Quill) ─────────────────────

    var editorAreas = $$('textarea[data-editor]');
    var editors = [];

    function syncEditors(form) {
        editors.forEach(function (ed) {
            if (!form || form.contains(ed.textarea)) ed.sync();
        });
    }

    function loadQuill(cb) {
        var css = document.createElement('link');
        css.rel = 'stylesheet';
        css.href = 'https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.snow.min.css';
        document.head.appendChild(css);
        var s = document.createElement('script');
        s.src = 'https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.min.js';
        s.onload = cb;
        document.head.appendChild(s);
    }

    if (editorAreas.length) {
        loadQuill(function () {
            if (!window.Quill) return;
            editorAreas.forEach(function (ta) {
                var holder = document.createElement('div');
                ta.parentNode.insertBefore(holder, ta.nextSibling);
                ta.style.display = 'none';
                var q = new window.Quill(holder, {
                    theme: 'snow',
                    modules: {
                        toolbar: [
                            [{ header: [2, 3, false] }],
                            ['bold', 'italic', 'underline', 'strike'],
                            [{ list: 'ordered' }, { list: 'bullet' }, { indent: '-1' }, { indent: '+1' }],
                            [{ align: [] }],
                            ['blockquote', 'link', 'video'],
                            ['clean']
                        ]
                    }
                });
                if (ta.value.trim()) q.clipboard.dangerouslyPasteHTML(ta.value);
                var ed = {
                    textarea: ta,
                    sync: function () {
                        var empty = q.getText().trim() === '' && !holder.querySelector('.ql-editor iframe, .ql-editor img');
                        ta.value = empty ? '' : q.root.innerHTML;
                    }
                };
                q.on('text-change', ed.sync);
                editors.push(ed);
                var label = ta.id && $('label[for="' + ta.id + '"]');
                if (label) label.addEventListener('click', function () { q.focus(); });
            });
        });
        document.addEventListener('submit', function (e) { syncEditors(e.target); }, true);
    }

    // ─── Contadores (mensajes sin leer...) ───────────────────────

    var badges = window.AULA_BADGES || {};
    var baseTitle = document.title;
    Object.keys(badges).forEach(function (id) {
        var cfg = badges[id];
        var poll = function () {
            if (document.hidden) return;
            fetch(cfg.url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (!res || !res.ok) return;
                    $$('[data-badge="' + id + '"]').forEach(function (b) {
                        b.textContent = res.count;
                        b.hidden = !res.count;
                    });
                    if (id === 'messages') document.title = (res.count ? '(' + res.count + ') ' : '') + baseTitle;
                }).catch(function () {});
        };
        setInterval(poll, Math.max(15, cfg.every || 60) * 1000);
        document.addEventListener('visibilitychange', function () { if (!document.hidden) poll(); });
    });

    // ─── Conversación abierta: mensajes nuevos ───────────────────

    var thread = $('#thread[data-poll-url]');
    if (thread) {
        var lastId = function () {
            var msgs = $$('.msg[data-id]', thread);
            return msgs.length ? msgs[msgs.length - 1].getAttribute('data-id') : 0;
        };
        if (!location.hash) {
            var lastMsg = $$('.msg', thread).pop();
            if (lastMsg) lastMsg.scrollIntoView({ block: 'center' });
        }
        var every = Math.max(5, parseInt(thread.getAttribute('data-poll-every'), 10) || 20) * 1000;
        setInterval(function () {
            if (document.hidden) return;
            fetch(thread.getAttribute('data-poll-url') + '&after=' + lastId(), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (!res || !res.ok || !res.html) return;
                    var nearBottom = window.innerHeight + window.scrollY > document.body.scrollHeight - 300;
                    var tmp = document.createElement('div');
                    tmp.innerHTML = res.html;
                    $$('.msg', tmp).forEach(function (m) {
                        if (!thread.querySelector('#' + m.id)) {
                            m.classList.add('is-new');
                            thread.appendChild(m);
                        }
                    });
                    if (nearBottom) $$('.msg', thread).pop().scrollIntoView({ behavior: 'smooth', block: 'center' });
                }).catch(function () {});
        }, every);
    }

    // ─── Nuevo mensaje ───────────────────────────────────────────

    var msgForm = $('[data-msg-form]');
    if (msgForm) {
        var applyMode = function () {
            var checked = $('input[name="mode"]:checked', msgForm);
            var mode = checked ? checked.value : 'people';
            $$('[data-mode]', msgForm).forEach(function (el) {
                var on = el.getAttribute('data-mode') === mode;
                el.hidden = !on;
                $$('input, select', el).forEach(function (i) { i.disabled = !on; });
            });
        };
        $$('input[name="mode"]', msgForm).forEach(function (r) { r.addEventListener('change', applyMode); });
        applyMode();

        var picker = $('[data-picker]', msgForm);
        if (picker) {
            var counter = $('[data-picked-count]', msgForm);
            var count = function () {
                var ids = {};
                $$('input[type=checkbox]:checked', picker).forEach(function (c) { ids[c.value] = true; });
                var n = Object.keys(ids).length;
                if (counter) counter.textContent = n ? '(' + n + ' seleccionados)' : '';
            };
            picker.addEventListener('change', function (e) {
                // un mismo alumno puede salir en varios cursos: sincroniza sus casillas
                if (e.target.type === 'checkbox') {
                    $$('input[value="' + e.target.value + '"]', picker).forEach(function (c) { c.checked = e.target.checked; });
                }
                count();
            });
            picker.addEventListener('click', function (e) {
                var all = e.target.closest('[data-pick-all]');
                if (!all) return;
                var boxes = $$('input[type=checkbox]', all.closest('fieldset'));
                var target = !boxes.every(function (c) { return c.checked; });
                boxes.forEach(function (c) {
                    $$('input[value="' + c.value + '"]', picker).forEach(function (x) { x.checked = target; });
                });
                count();
            });
            var search = $('[data-picker-search]', picker);
            search.addEventListener('input', function () {
                var q = search.value.toLowerCase().trim();
                $$('.picker-item', picker).forEach(function (it) {
                    it.hidden = q !== '' && it.getAttribute('data-name').indexOf(q) === -1;
                });
            });
            msgForm.addEventListener('submit', function (e) {
                // Evita enviar duplicados del mismo alumno
                var seen = {};
                $$('input[type=checkbox][name="to[]"]', picker).forEach(function (c) {
                    if (c.checked && seen[c.value]) c.disabled = true;
                    if (c.checked) seen[c.value] = true;
                });
                var mode = $('input[name="mode"]:checked', msgForm);
                if ((!mode || mode.value === 'people') && !Object.keys(seen).length) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    window.alert('Elige al menos a una persona.');
                }
            }, true);
            count();
        }
    }

    // ─── Visor de imágenes a pantalla completa ──────────────────
    // Enlaces con data-lightbox e imágenes de los textos (.prose img).
    // Primer clic: imagen ajustada a la pantalla; otro clic: ampliada.

    function openLightbox(src, alt) {
        var box = document.createElement('div');
        box.className = 'lightbox';
        box.setAttribute('role', 'dialog');
        box.setAttribute('aria-modal', 'true');
        box.setAttribute('aria-label', alt || 'Imagen');
        box.innerHTML = '<div class="lightbox-bar">'
            + '<span class="lightbox-hint">Pulsa la imagen para ampliar o reducir</span>'
            + '<a class="lightbox-btn" target="_blank" rel="noopener" title="Abrir en otra pestaña" aria-label="Abrir en otra pestaña"><i class="fa-solid fa-up-right-from-square"></i></a>'
            + '<button type="button" class="lightbox-btn" data-lb-close title="Cerrar (Esc)" aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>'
            + '</div><div class="lightbox-stage"><img alt=""></div>';
        var stage = $('.lightbox-stage', box);
        var img = $('img', box);
        $('a.lightbox-btn', box).href = src;
        img.src = src;
        img.alt = alt || '';
        var lastFocus = document.activeElement;
        var close = function () {
            box.remove();
            document.documentElement.classList.remove('lightbox-open');
            document.removeEventListener('keydown', onKey);
            if (lastFocus && lastFocus.focus) lastFocus.focus();
        };
        var onKey = function (e) {
            if (e.key === 'Escape') close();
        };
        var zoom = function (e) {
            if (box.classList.contains('zoomed')) {
                box.classList.remove('zoomed');
                img.style.width = '';
                return;
            }
            var r = img.getBoundingClientRect();
            var relX = (e.clientX - r.left) / r.width;
            var relY = (e.clientY - r.top) / r.height;
            var width = Math.min(Math.max(img.naturalWidth, r.width * 2), r.width * 4);
            box.classList.add('zoomed');
            img.style.width = width + 'px';
            var height = width * r.height / r.width;
            stage.scrollLeft = relX * width - stage.clientWidth / 2;
            stage.scrollTop = relY * height - stage.clientHeight / 2;
        };

        // Con ratón, arrastrar mueve la imagen ampliada; un clic sin arrastrar amplía/reduce.
        var drag = null;
        stage.addEventListener('pointerdown', function (e) {
            if (e.pointerType !== 'mouse' || e.button !== 0) return;
            drag = { x: e.clientX, y: e.clientY, left: stage.scrollLeft, top: stage.scrollTop, moved: false };
        });
        stage.addEventListener('pointermove', function (e) {
            if (!drag || !box.classList.contains('zoomed')) return;
            if (Math.abs(e.clientX - drag.x) + Math.abs(e.clientY - drag.y) > 5) drag.moved = true;
            if (drag.moved) {
                e.preventDefault();
                stage.scrollLeft = drag.left - (e.clientX - drag.x);
                stage.scrollTop = drag.top - (e.clientY - drag.y);
            }
        });
        stage.addEventListener('click', function (e) {
            var moved = drag && drag.moved;
            drag = null;
            if (moved) return;
            if (e.target === img) zoom(e); else close();
        });
        img.addEventListener('dragstart', function (e) { e.preventDefault(); });
        $('[data-lb-close]', box).addEventListener('click', close);
        document.addEventListener('keydown', onKey);
        document.body.appendChild(box);
        document.documentElement.classList.add('lightbox-open');
        $('[data-lb-close]', box).focus();
    }

    document.addEventListener('click', function (e) {
        if (e.defaultPrevented || e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey) return;
        var link = e.target.closest('a[data-lightbox]');
        var img = !link && e.target.closest('.prose img');
        if (img && img.closest('a')) return;
        if (!link && !img) return;
        e.preventDefault();
        var inner = link && $('img', link);
        openLightbox(link ? link.href : img.src, inner ? inner.alt : (img ? img.alt : ''));
    });
})();
