/*
 * Reproductor SCORM del aula.
 * Expone window.API (SCORM 1.2) y window.API_1484_11 (SCORM 2004) para el
 * contenido cargado en el iframe y guarda el progreso en el servidor.
 */
(function () {
    'use strict';

    var root = document.querySelector('[data-scorm]');
    if (!root) return;

    var cfg = JSON.parse(root.getAttribute('data-scorm'));
    var frame = root.querySelector('.scorm-frame');
    var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    var is2004 = cfg.version === '2004';
    var launch = cfg.items.filter(function (i) { return i.href; });
    var tracks = cfg.tracks || {};
    var st = null; // estado del apartado abierto
    var autosave = null;

    var ERR = {
        '0': 'No error', '101': 'General exception', '201': 'Invalid argument error', '301': 'Not initialized',
        '401': 'Not implemented error', '403': 'Element is read only', '404': 'Element is write only'
    };

    function itemById(id) {
        for (var i = 0; i < launch.length; i++) if (launch[i].id === id) return launch[i];
        return null;
    }

    function newState(item) {
        var t = tracks[item.id] || {};
        return {
            item: item,
            data: Object.assign({}, t.data || {}),
            changed: {},
            initialized: false,
            finished: false,
            error: '0',
            started: Date.now(),
            hadData: !!(t.data && Object.keys(t.data).length)
        };
    }

    function countOf(prefix) {
        var max = -1;
        var re = new RegExp('^' + prefix.replace(/\./g, '\\.') + '\\.(\\d+)\\.');
        Object.keys(st.data).forEach(function (k) {
            var m = k.match(re);
            if (m) max = Math.max(max, parseInt(m[1], 10));
        });
        return max + 1;
    }

    var CHILDREN12 = {
        'cmi.core._children': 'student_id,student_name,lesson_location,credit,lesson_status,entry,score,total_time,lesson_mode,exit,session_time',
        'cmi.core.score._children': 'raw,min,max',
        'cmi.objectives._children': 'id,score,status',
        'cmi.student_data._children': 'mastery_score,max_time_allowed,time_limit_action',
        'cmi.student_preference._children': 'audio,language,speed,text',
        'cmi.interactions._children': 'id,objectives,time,type,correct_responses,weighting,student_response,result,latency'
    };
    var CHILDREN2004 = {
        'cmi.score._children': 'scaled,raw,min,max',
        'cmi.objectives._children': 'id,score,success_status,completion_status,progress_measure,description',
        'cmi.interactions._children': 'id,type,objectives,timestamp,correct_responses,weighting,learner_response,result,latency,description',
        'cmi.learner_preference._children': 'audio_level,language,delivery_speed,audio_captioning',
        'cmi.comments_from_learner._children': 'comment,location,timestamp',
        'cmi.comments_from_lms._children': 'comment,location,timestamp'
    };

    function getValue(key) {
        if (!st) return '';
        var d = st.data;
        key = String(key);
        if (/\._count$/.test(key)) return String(countOf(key.slice(0, -7)));
        if (!is2004) {
            if (CHILDREN12[key]) return CHILDREN12[key];
            switch (key) {
                case 'cmi._version': return '3.4';
                case 'cmi.core.student_id': return cfg.learner.id;
                case 'cmi.core.student_name': return cfg.learner.name;
                case 'cmi.core.lesson_status': return d[key] || (st.hadData ? 'incomplete' : 'not attempted');
                case 'cmi.core.entry': return !st.hadData ? 'ab-initio' : (d['cmi.core.exit'] === 'suspend' ? 'resume' : '');
                case 'cmi.core.credit': return 'credit';
                case 'cmi.core.lesson_mode': return 'normal';
                case 'cmi.core.total_time': return d[key] || '0000:00:00';
                case 'cmi.launch_data': return st.item.data || '';
                case 'cmi.student_data.mastery_score': return st.item.mastery || '';
            }
        } else {
            if (CHILDREN2004[key]) return CHILDREN2004[key];
            switch (key) {
                case 'cmi._version': return '1.0';
                case 'cmi.learner_id': return cfg.learner.id;
                case 'cmi.learner_name': return cfg.learner.name;
                case 'cmi.completion_status': return d[key] || (st.hadData ? 'incomplete' : 'not attempted');
                case 'cmi.success_status': return d[key] || 'unknown';
                case 'cmi.entry': return !st.hadData ? 'ab-initio' : (d['cmi.exit'] === 'suspend' ? 'resume' : '');
                case 'cmi.credit': return 'credit';
                case 'cmi.mode': return 'normal';
                case 'cmi.total_time': return d[key] || 'PT0H0M0S';
                case 'cmi.launch_data': return st.item.data || '';
                case 'cmi.scaled_passing_score': return d[key] || '';
                case 'adl.nav.request_valid.continue': return String(indexOf(st.item.id) < launch.length - 1);
                case 'adl.nav.request_valid.previous': return String(indexOf(st.item.id) > 0);
            }
        }
        return d[key] !== undefined ? String(d[key]) : '';
    }

    function setValue(key, value) {
        if (!st) return false;
        key = String(key);
        if (/\._(children|count)$/.test(key)) { st.error = '402'; return false; }
        st.data[key] = String(value);
        st.changed[key] = String(value);
        st.error = '0';
        if (/lesson_status|completion_status|success_status/.test(key)) updateBadge(st.item.id, deriveStatus(st.data));
        return true;
    }

    function deriveStatus(d) {
        if (is2004) {
            if (d['cmi.success_status'] === 'passed' || d['cmi.success_status'] === 'failed') return d['cmi.success_status'];
            return d['cmi.completion_status'] === 'completed' ? 'completed' : 'incomplete';
        }
        var s = d['cmi.core.lesson_status'] || 'incomplete';
        return s === 'not attempted' ? 'incomplete' : s;
    }

    function commit(finish) {
        if (!st) return Promise.resolve();
        var payload = {
            resource: cfg.resource,
            sco: st.item.id,
            data: finish ? st.data : st.changed,
            finish: !!finish,
            elapsed: Math.round((Date.now() - st.started) / 1000)
        };
        st.changed = {};
        var item = st.item;
        if (finish) {
            // la sesión siguiente empieza a contar desde cero
            st.started = Date.now();
            tracks[item.id] = { data: Object.assign({}, st.data), status: deriveStatus(st.data) };
        }
        return fetch(cfg.commit, {
            method: 'POST',
            credentials: 'same-origin',
            keepalive: true,
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf, 'Accept': 'application/json' },
            body: JSON.stringify(payload)
        }).then(function (r) { return r.json(); }).then(function (res) {
            if (res && res.ok) {
                updateBadge(item.id, res.status);
                setState('Guardado');
            } else {
                setState(res && res.error ? res.error : 'No se pudo guardar', true);
            }
        }).catch(function () {
            setState('Sin conexión: no se pudo guardar', true);
        });
    }

    function finish() {
        if (!st || st.finished || !st.initialized) return;
        st.finished = true;
        commit(true);
        var req = st.data['adl.nav.request'];
        if (is2004 && req) {
            delete st.data['adl.nav.request'];
            if (req === 'continue') setTimeout(function () { go(1); }, 50);
            if (req === 'previous') setTimeout(function () { go(-1); }, 50);
        }
    }

    function initialize() {
        if (!st) return false;
        st.initialized = true;
        st.finished = false;
        st.error = '0';
        return true;
    }

    // ─── APIs ─────────────────────────────────────────────────────

    window.API = {
        LMSInitialize: function () { return initialize() ? 'true' : 'false'; },
        LMSFinish: function () { finish(); return 'true'; },
        LMSGetValue: function (k) { return getValue(k); },
        LMSSetValue: function (k, v) { return setValue(k, v) ? 'true' : 'false'; },
        LMSCommit: function () { commit(false); return 'true'; },
        LMSGetLastError: function () { return st ? st.error : '0'; },
        LMSGetErrorString: function (c) { return ERR[c] || ''; },
        LMSGetDiagnostic: function (c) { return ERR[c] || ''; }
    };

    window.API_1484_11 = {
        Initialize: function () { return initialize() ? 'true' : 'false'; },
        Terminate: function () { finish(); return 'true'; },
        GetValue: function (k) { return getValue(k); },
        SetValue: function (k, v) { return setValue(k, v) ? 'true' : 'false'; },
        Commit: function () { commit(false); return 'true'; },
        GetLastError: function () { return st ? st.error : '0'; },
        GetErrorString: function (c) { return ERR[c] || ''; },
        GetDiagnostic: function (c) { return ERR[c] || ''; }
    };

    // ─── Interfaz ─────────────────────────────────────────────────

    var titleEl = root.querySelector('[data-scorm-title]');
    var stateEl = root.querySelector('[data-scorm-state]');
    var stateTimer = null;

    function setState(text, isError) {
        if (!stateEl) return;
        stateEl.textContent = text;
        stateEl.classList.toggle('is-error', !!isError);
        clearTimeout(stateTimer);
        if (!isError) stateTimer = setTimeout(function () { stateEl.textContent = ''; }, 2500);
    }

    function updateBadge(id, status) {
        var b = root.querySelector('.toc-item[data-sco="' + CSS.escape(id) + '"] .toc-st');
        if (b) b.className = 'toc-st toc-st-' + String(status || 'none').replace(/\s+/g, '-');
    }

    function indexOf(id) {
        for (var i = 0; i < launch.length; i++) if (launch[i].id === id) return i;
        return -1;
    }

    function open(id) {
        var item = itemById(id) || launch[0];
        if (st && st.item.id === item.id) return;
        if (st && st.initialized && !st.finished) finish();
        st = newState(item);
        frame.src = cfg.base + item.href.split('/').map(function (p, i, arr) {
            // no recodificar la query string
            return i === arr.length - 1 && p.indexOf('?') !== -1 ? encodeURIComponent(p.split('?')[0]) + '?' + p.split('?').slice(1).join('?') : encodeURIComponent(p);
        }).join('/');
        if (titleEl) titleEl.textContent = item.title;
        root.querySelectorAll('.toc-item').forEach(function (b) {
            b.classList.toggle('is-current', b.getAttribute('data-sco') === item.id);
            if (b.getAttribute('data-sco') === item.id) b.setAttribute('aria-current', 'true'); else b.removeAttribute('aria-current');
        });
        var i = indexOf(item.id);
        var prev = root.querySelector('[data-scorm-prev]');
        var next = root.querySelector('[data-scorm-next]');
        if (prev) prev.disabled = i <= 0;
        if (next) next.disabled = i >= launch.length - 1;
        if (window.innerWidth < 900) root.classList.remove('toc-open');
    }

    function go(dir) {
        var i = indexOf(st ? st.item.id : '') + dir;
        if (i >= 0 && i < launch.length) open(launch[i].id);
    }

    root.addEventListener('click', function (e) {
        var t = e.target.closest('button');
        if (!t) return;
        if (t.hasAttribute('data-sco')) open(t.getAttribute('data-sco'));
        if (t.hasAttribute('data-scorm-prev')) go(-1);
        if (t.hasAttribute('data-scorm-next')) go(1);
        if (t.hasAttribute('data-scorm-toggle')) root.classList.toggle('toc-open');
        if (t.hasAttribute('data-scorm-full')) {
            if (document.fullscreenElement) document.exitFullscreen();
            else if (root.requestFullscreen) root.requestFullscreen();
            else root.classList.toggle('is-max');
        }
    });

    // Guardado periódico y al salir de la página
    autosave = setInterval(function () {
        if (st && Object.keys(st.changed).length) commit(false);
    }, 30000);

    window.addEventListener('pagehide', function () {
        if (st && st.initialized && !st.finished) finish();
    });

    if (window.innerWidth >= 900) root.classList.add('toc-open');
    open(cfg.start);
})();
