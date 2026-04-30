/**
 * Codex Assist — AI Chat Widget
 * Floating chat bubble with real-time AI interaction.
 */
(() => {
    'use strict';

    const API_BASE = document.querySelector('meta[name="codex-api-base"]')?.content
                  || (window.location.origin + '/api/v1');
    const TOKEN_KEY = 'codex_jwt_token';

    // ── State ────────────────────────────────────────────
    let isOpen = false;
    let isLoading = false;
    const history = JSON.parse(sessionStorage.getItem('codex_assist_history') || '[]');

    // ── DOM creation ─────────────────────────────────────
    const fab = document.createElement('button');
    fab.className = 'codex-assist-fab';
    fab.setAttribute('aria-label', 'Abrir asistente Codex');
    fab.title = 'Codex Assist';
    fab.innerHTML = '<i class="bi bi-stars"></i>';

    const panel = document.createElement('div');
    panel.className = 'codex-assist-panel';
    panel.innerHTML = `
        <div class="codex-assist-header">
            <div>
                <h3>Codex Assist <small>Asistente inteligente del ERP</small></h3>
            </div>
            <button type="button" class="btn btn-sm p-0 border-0" id="codex-assist-close" aria-label="Cerrar">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="codex-assist-messages" id="codex-assist-msgs">
            <div class="codex-assist-msg is-bot">
                ¡Hola! Soy <strong>Codex Assist</strong>. Puedo ayudarte con facturación, stock, cobranzas, impuestos y más. ¿En qué te puedo ayudar?
            </div>
        </div>
        <div class="codex-assist-quick" id="codex-assist-quick">
            <button type="button" data-q="¿Cómo creo una factura?">📄 Facturar</button>
            <button type="button" data-q="¿Qué productos tienen stock bajo?">📦 Stock bajo</button>
            <button type="button" data-q="¿Cómo registro un cobro?">💰 Cobrar</button>
            <button type="button" data-q="Mostrame las alertas del sistema">🔔 Alertas</button>
        </div>
        <div class="codex-assist-input">
            <input type="text" id="codex-assist-field" placeholder="Escribí tu pregunta..." autocomplete="off">
            <button type="button" id="codex-assist-send" aria-label="Enviar"><i class="bi bi-send-fill"></i></button>
        </div>
    `;

    document.body.appendChild(fab);
    document.body.appendChild(panel);

    const msgs = document.getElementById('codex-assist-msgs');
    const field = document.getElementById('codex-assist-field');
    const sendBtn = document.getElementById('codex-assist-send');
    const closeBtn = document.getElementById('codex-assist-close');
    const quickBtns = document.getElementById('codex-assist-quick');

    // ── Helpers ──────────────────────────────────────────
    const scrollBottom = () => setTimeout(() => msgs.scrollTop = msgs.scrollHeight, 50);

    const detectModule = () => {
        const path = window.location.pathname;
        if (path.includes('/ventas'))      return 'ventas';
        if (path.includes('/inventario'))  return 'inventario';
        if (path.includes('/compras'))     return 'compras';
        if (path.includes('/caja'))        return 'caja';
        if (path.includes('/dashboard'))   return 'dashboard';
        if (path.includes('/configuracion')) return 'configuracion';
        return null;
    };

    const addMessage = (text, type) => {
        const div = document.createElement('div');
        div.className = `codex-assist-msg is-${type}`;
        // Simple markdown-like rendering for bot messages
        if (type === 'bot') {
            div.innerHTML = text
                .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                .replace(/`(.*?)`/g, '<code>$1</code>')
                .replace(/\n/g, '<br>');
        } else {
            div.textContent = text;
        }
        // Remove typing indicator if present
        msgs.querySelector('.codex-assist-typing')?.remove();
        msgs.appendChild(div);
        scrollBottom();
        history.push({ text, type, ts: Date.now() });
        sessionStorage.setItem('codex_assist_history', JSON.stringify(history.slice(-30)));
    };

    const showTyping = () => {
        let typing = msgs.querySelector('.codex-assist-typing');
        if (!typing) {
            typing = document.createElement('div');
            typing.className = 'codex-assist-typing';
            typing.innerHTML = '<span></span><span></span><span></span>';
            msgs.appendChild(typing);
        }
        typing.classList.add('is-visible');
        scrollBottom();
    };

    const hideTyping = () => {
        const typing = msgs.querySelector('.codex-assist-typing');
        if (typing) typing.remove();
    };

    // ── API call ─────────────────────────────────────────
    const askAssist = async (question) => {
        if (isLoading) return;
        isLoading = true;
        sendBtn.disabled = true;
        addMessage(question, 'user');
        showTyping();

        try {
            const token = localStorage.getItem(TOKEN_KEY) || '';
            const res = await fetch(API_BASE + '/assist/ask', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    ...(token ? { 'Authorization': 'Bearer ' + token } : {}),
                },
                body: JSON.stringify({
                    question,
                    module: detectModule(),
                }),
            });

            if (!res.ok) {
                throw new Error(`HTTP ${res.status}`);
            }

            const json = await res.json();
            const answer = json?.data?.answer || json?.answer || 'No pude obtener una respuesta. Verificá que el asistente esté configurado.';
            const provider = json?.data?.provider || json?.provider || 'unknown';
            const duration = json?.data?.duration_ms || json?.duration_ms || null;

            let meta = '';
            if (provider !== 'unknown') {
                meta = `\n\n_${provider}${duration ? ` · ${duration}ms` : ''}_`;
            }

            hideTyping();
            addMessage(answer + meta, 'bot');
        } catch (err) {
            hideTyping();
            addMessage('⚠️ No pude conectar con el asistente. Verificá que el servicio esté activo.\n\n`' + err.message + '`', 'bot');
        } finally {
            isLoading = false;
            sendBtn.disabled = false;
            field.focus();
        }
    };

    // ── Toggle ───────────────────────────────────────────
    const toggle = () => {
        isOpen = !isOpen;
        fab.classList.toggle('is-open', isOpen);
        panel.classList.toggle('is-open', isOpen);
        if (isOpen) {
            field.focus();
            scrollBottom();
        }
    };

    // ── Restore history ──────────────────────────────────
    if (history.length > 0) {
        history.forEach(m => {
            const div = document.createElement('div');
            div.className = `codex-assist-msg is-${m.type}`;
            if (m.type === 'bot') {
                div.innerHTML = m.text
                    .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                    .replace(/`(.*?)`/g, '<code>$1</code>')
                    .replace(/\n/g, '<br>');
            } else {
                div.textContent = m.text;
            }
            msgs.appendChild(div);
        });
        scrollBottom();
    }

    // ── Events ───────────────────────────────────────────
    fab.addEventListener('click', toggle);
    closeBtn.addEventListener('click', toggle);

    sendBtn.addEventListener('click', () => {
        const q = field.value.trim();
        if (q === '') return;
        field.value = '';
        askAssist(q);
    });

    field.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            sendBtn.click();
        }
    });

    quickBtns.addEventListener('click', (e) => {
        const btn = e.target.closest('button[data-q]');
        if (!btn) return;
        field.value = '';
        askAssist(btn.dataset.q);
    });

    // Global shortcut: Alt+A to toggle
    document.addEventListener('keydown', (e) => {
        if (e.altKey && e.key.toLowerCase() === 'a') {
            e.preventDefault();
            toggle();
        }
    });
})();
