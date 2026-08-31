/**
 * Codex Assist — AI Chat Widget (Alpine.js Powered Component)
 * Floating chat drawer with real-time AI interaction & reactive state management.
 */
document.addEventListener('alpine:init', () => {
    Alpine.data('codexAssist', () => ({
        isOpen: false,
        isLoading: false,
        inputQuestion: '',
        messages: JSON.parse(sessionStorage.getItem('codex_assist_history') || '[]'),
        
        init() {
            if (this.messages.length === 0) {
                this.messages.push({
                    text: '¡Hola! Soy **Codex Assist**. Puedo ayudarte con facturación, stock, cobranzas, impuestos y más. ¿En qué te puedo ayudar?',
                    type: 'bot',
                    ts: Date.now()
                });
            }
            this.scrollBottom();

            // Shortcut: Alt+A to toggle assistant
            window.addEventListener('keydown', (e) => {
                if (e.altKey && e.key.toLowerCase() === 'a') {
                    e.preventDefault();
                    this.toggle();
                }
            });
        },

        toggle() {
            this.isOpen = !this.isOpen;
            if (this.isOpen) {
                this.$nextTick(() => {
                    this.$refs.field?.focus();
                    this.scrollBottom();
                });
            }
        },

        scrollBottom() {
            this.$nextTick(() => {
                const container = this.$refs.msgContainer;
                if (container) {
                    container.scrollTop = container.scrollHeight;
                }
            });
        },

        detectModule() {
            const path = window.location.pathname;
            if (path.includes('/ventas'))        return 'ventas';
            if (path.includes('/inventario'))    return 'inventario';
            if (path.includes('/compras'))       return 'compras';
            if (path.includes('/caja'))          return 'caja';
            if (path.includes('/dashboard'))     return 'dashboard';
            if (path.includes('/configuracion'))   return 'configuracion';
            return null;
        },

        formatMsg(text, type) {
            if (type === 'bot') {
                return text
                    .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                    .replace(/`(.*?)`/g, '<code>$1</code>')
                    .replace(/\n/g, '<br>');
            }
            return text;
        },

        async send(question) {
            const q = (question || this.inputQuestion).trim();
            if (q === '' || this.isLoading) return;

            this.inputQuestion = '';
            this.messages.push({ text: q, type: 'user', ts: Date.now() });
            this.isLoading = true;
            this.scrollBottom();

            const apiBase = document.querySelector('meta[name="codex-api-base"]')?.content
                         || (window.location.origin + '/api/v1');
            const token = localStorage.getItem('codex_jwt_token') || '';

            try {
                const res = await fetch(apiBase + '/assist/ask', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        ...(token ? { 'Authorization': 'Bearer ' + token } : {}),
                    },
                    body: JSON.stringify({
                        question: q,
                        module: this.detectModule(),
                    }),
                });

                if (!res.ok) throw new Error(`HTTP ${res.status}`);

                const json = await res.json();
                const answer = json?.data?.answer || json?.answer || 'No pude obtener una respuesta.';
                const provider = json?.data?.provider || json?.provider || 'unknown';
                const duration = json?.data?.duration_ms || json?.duration_ms || null;

                let meta = '';
                if (provider !== 'unknown') {
                    meta = `\n\n_${provider}${duration ? ` · ${duration}ms` : ''}_`;
                }

                this.messages.push({ text: answer + meta, type: 'bot', ts: Date.now() });
            } catch (err) {
                this.messages.push({
                    text: '⚠️ No pude conectar con el asistente.\n\n`' + err.message + '`',
                    type: 'bot',
                    ts: Date.now()
                });
            } finally {
                this.isLoading = false;
                sessionStorage.setItem('codex_assist_history', JSON.stringify(this.messages.slice(-30)));
                this.scrollBottom();
                this.$nextTick(() => this.$refs.field?.focus());
            }
        }
    }));
});

// Auto-mount widget container if not present
document.addEventListener('DOMContentLoaded', () => {
    if (!document.getElementById('codex-assist-app')) {
        const root = document.createElement('div');
        root.id = 'codex-assist-app';
        root.setAttribute('x-data', 'codexAssist');
        root.innerHTML = `
            <button type="button" class="codex-assist-fab" :class="{ 'is-open': isOpen }" @click="toggle()" aria-label="Abrir asistente Codex" title="Codex Assist (Alt+A)">
                <i class="bi bi-stars"></i>
            </button>
            <div class="codex-assist-panel" :class="{ 'is-open': isOpen }" x-cloak>
                <div class="codex-assist-header">
                    <div>
                        <h3>Codex Assist <small>Asistente inteligente ERP</small></h3>
                    </div>
                    <button type="button" class="btn btn-sm p-0 border-0 text-dark" @click="toggle()" aria-label="Cerrar">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <div class="codex-assist-messages" x-ref="msgContainer">
                    <template x-for="(m, idx) in messages" :key="idx">
                        <div class="codex-assist-msg" :class="'is-' + m.type" x-html="formatMsg(m.text, m.type)"></div>
                    </template>
                    <div class="codex-assist-typing" :class="{ 'is-visible': isLoading }" x-show="isLoading" x-cloak>
                        <span></span><span></span><span></span>
                    </div>
                </div>
                <div class="codex-assist-quick">
                    <button type="button" @click="send('¿Cómo creo una factura?')">📄 Facturar</button>
                    <button type="button" @click="send('¿Qué productos tienen stock bajo?')">📦 Stock bajo</button>
                    <button type="button" @click="send('¿Cómo registro un cobro?')">💰 Cobrar</button>
                    <button type="button" @click="send('Mostrame las alertas del sistema')">🔔 Alertas</button>
                </div>
                <div class="codex-assist-input">
                    <input type="text" x-ref="field" x-model="inputQuestion" @keydown.enter.prevent="send()" placeholder="Escribí tu pregunta..." autocomplete="off">
                    <button type="button" @click="send()" :disabled="isLoading" aria-label="Enviar"><i class="bi bi-send-fill"></i></button>
                </div>
            </div>
        `;
        document.body.appendChild(root);
    }
});
