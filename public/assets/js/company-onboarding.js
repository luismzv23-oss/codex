import {stepStatus, stepLocked, resumeStep, withDependencies, validCuit} from './wizard-steps.mjs?v=1';

const configNode = document.getElementById('onboarding-config');
if (configNode) {
    const config = JSON.parse(configNode.textContent);
    const byId = id => document.getElementById(id);
    const form = byId('onboarding-form');
    let csrf = config.csrfToken, companyId = config.companyId || null, data = null, stage = 1, busy = false;
    const STEP_COUNT = Object.keys(config.steps).length;

    // ---------- helpers ----------
    function el(tag, props = {}, ...children) {
        const node = document.createElement(tag);
        for (const [key, value] of Object.entries(props)) {
            if (key === 'class') node.className = value; else if (key === 'text') node.textContent = value;
            else if (key.startsWith('on')) node.addEventListener(key.slice(2), value);
            else if (key === 'checked' || key === 'disabled' || key === 'selected' || key === 'required') node[key] = Boolean(value);
            else if (value !== null && value !== undefined && value !== false) node.setAttribute(key, value === true ? '' : String(value));
        }
        for (const child of children.flat()) if (child !== null && child !== undefined && child !== false) node.append(child instanceof Node ? child : document.createTextNode(String(child)));
        return node;
    }
    const field = (label, control, help, wide) => el('label', {class: 'wizard-field' + (wide ? ' span-2' : '')}, el('span', {text: label}), control, help ? el('small', {text: help}) : null);
    const input = (name, value, attrs = {}) => el('input', {class: 'form-control', name, value: value ?? '', ...attrs});
    const select = (name, options, value, attrs = {}) => el('select', {class: 'form-select', name, ...attrs},
        Object.entries(options).map(([key, label]) => el('option', {value: key, selected: String(key) === String(value ?? '')}, label)));
    const check = (name, checked, attrs = {}) => el('input', {type: 'checkbox', class: 'form-check-input', name, checked, ...attrs});
    const value = (root, name) => root.querySelector(`[name="${name}"]`);

    function message(text, error = false) {
        const node = byId('onboarding-message');
        node.textContent = text || ''; node.hidden = !text; node.classList.toggle('is-error', error);
        if (text) node.scrollIntoView({block: 'nearest', behavior: 'smooth'});
    }
    async function request(path, method = 'GET', body = null) {
        const headers = {Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest'};
        if (method !== 'GET') headers[config.csrfHeader] = csrf;
        if (body && !(body instanceof FormData)) headers['Content-Type'] = 'application/json';
        const response = await fetch(config.baseUrl + path, {method, headers, credentials: 'same-origin', cache: 'no-store',
            body: body instanceof FormData ? body : body ? JSON.stringify(body) : undefined});
        let result;
        try { result = await response.json(); } catch { throw new Error('El servidor no devolvió una respuesta válida. Comprueba la sesión.'); }
        if (result.csrf_token) csrf = result.csrf_token;
        if (!response.ok) throw new Error(result.error || 'No se pudo completar la operación.');
        return result;
    }
    const steps = () => data?.state?.steps || {};
    const locked = step => stepLocked(step, {role: config.role, companyId, superadminSteps: config.superadminSteps});

    // ---------- navigation ----------
    function renderSteps() {
        const list = byId('onboarding-steps'); list.replaceChildren();
        for (let step = 1; step <= STEP_COUNT; step++) {
            const status = stepStatus(steps(), step);
            const item = el('li', {class: (status === 'done' ? 'is-done ' : status === 'skipped' ? 'is-skipped ' : '') + (locked(step) ? 'is-locked' : ''),
                title: locked(step) ? (companyId ? 'Lo define el superadministrador' : 'Primero guarda los datos de la empresa') : '',
                onclick: () => { if (!locked(step)) go(step); }},
                el('span', {}, status === 'done' ? el('i', {class: 'bi bi-check-lg', 'aria-hidden': 'true'}) : String(step)), config.steps[step]);
            if (step === stage) item.setAttribute('aria-current', 'step');
            list.append(item);
        }
    }
    function go(step) {
        stage = Math.max(1, Math.min(STEP_COUNT, step));
        byId('onboarding-caption').textContent = `PASO ${stage} DE ${STEP_COUNT}` + (stepStatus(steps(), stage) === 'done' ? ' · COMPLETO' : stepStatus(steps(), stage) === 'skipped' ? ' · OMITIDO' : '');
        byId('onboarding-title').textContent = config.steps[stage];
        byId('onboarding-company').textContent = data?.company?.name || 'Nueva empresa';
        byId('onboarding-prev').disabled = stage <= 1 || locked(stage - 1);
        byId('onboarding-skip').hidden = !config.optionalSteps.includes(stage);
        byId('onboarding-save').querySelector('span').textContent = stage === STEP_COUNT ? 'Finalizar' : 'Guardar y continuar';
        byId('onboarding-save').disabled = locked(stage) || (stage === STEP_COUNT && data?.state?.status === 'completed');
        byId('onboarding-help').textContent = locked(stage) ? 'Este paso lo completa el superadministrador.' : 'Cada paso se guarda al confirmarlo.';
        renderSteps(); render();
    }
    async function load() {
        data = await request(companyId ? `/${encodeURIComponent(companyId)}/datos` : '/datos');
        const start = resumeStep(steps(), {total: STEP_COUNT, role: config.role, companyId, superadminSteps: config.superadminSteps});
        go(data?.state?.status === 'completed' ? STEP_COUNT : start);
    }

    // ---------- step forms ----------
    const renderers = {
        1() {
            const c = data.company || {};
            form.append(el('p', {class: 'wizard-help', text: 'Datos legales y fiscales. Al guardar se crean la empresa, su moneda base, la sucursal Casa Matriz y el plan de cuentas.'}),
                el('div', {class: 'wizard-grid'},
                    field('Razón social *', input('legal_name', c.legal_name, {maxlength: 180, required: true})),
                    field('Nombre comercial *', input('name', c.name, {maxlength: 150, required: true})),
                    field('CUIT *', input('tax_id', c.tax_id, {maxlength: 13, required: true, inputmode: 'numeric', placeholder: '30-12345678-9'}), '11 dígitos; se valida el dígito verificador.'),
                    field('Condición frente al IVA *', select('iva_condition', {'': 'Selecciona…', ...data.ivaConditions}, c.iva_condition)),
                    field('Ingresos brutos', input('iibb', c.iibb, {maxlength: 40})),
                    field('Inicio de actividades', input('start_activities', c.start_activities, {type: 'date'})),
                    field('Moneda base *', select('currency_code', {'': 'Selecciona…', ...data.currencies}, c.currency_code || 'ARS')),
                    field('Correo', input('email', c.email, {type: 'email', maxlength: 150})),
                    field('Teléfono', input('phone', c.phone, {maxlength: 40})),
                    field('Dirección', input('address', c.address, {maxlength: 500}), null, true)));
        },
        2() {
            const box = el('div', {class: 'wizard-modules'});
            for (const module of data.modules) {
                const box_ = check('systems[]', module.active, {value: module.id, 'data-id': module.id});
                box_.addEventListener('change', () => {
                    if (!box_.checked) return;
                    const all = withDependencies([...form.querySelectorAll('[name="systems[]"]:checked')].map(n => n.value), data.modules);
                    form.querySelectorAll('[name="systems[]"]').forEach(node => { node.checked = all.includes(node.value); });
                });
                box.append(el('label', {}, box_, el('div', {}, el('strong', {text: module.name}),
                    module.dependencies.length ? el('small', {text: 'Requiere: ' + module.dependencies.join(', ')}) : null)));
            }
            form.append(el('p', {class: 'wizard-help', text: 'Módulos habilitados para la empresa. El administrador recibe acceso completo a cada uno.'}), box);
        },
        3() {
            const a = data.admin || {};
            form.append(el('p', {class: 'wizard-help', text: a.username ? 'La empresa ya tiene administrador. Puedes actualizar sus datos; deja la contraseña vacía para conservarla.' : 'Usuario administrador de la empresa, en la sucursal Casa Matriz. Deberá cambiar la contraseña en su primer ingreso y podrá completar los pasos 4 a 9.'}),
                el('div', {class: 'wizard-grid'},
                    field('Nombre y apellido *', input('name', a.name, {maxlength: 150})),
                    field('Usuario *', input('username', a.username, {maxlength: 50, autocomplete: 'off'})),
                    field('Correo *', input('email', a.email, {type: 'email', maxlength: 150})),
                    field(a.username ? 'Nueva contraseña' : 'Contraseña *', input('password', '', {type: 'password', autocomplete: 'new-password'}), 'Mínimo 8 caracteres, con mayúsculas, minúsculas y números.')));
        },
        4() {
            const branches = Object.fromEntries(data.branches.map(b => [b.id, b.name]));
            form.append(el('p', {class: 'wizard-help', text: 'Al guardar se crean los valores de cada módulo: configuración de inventario, tipos de comprobante con su código AFIP, puntos de venta, numeraciones, cliente Consumidor Final, lista de precios y cajas.'}),
                el('h3', {class: 'h6', text: 'Sucursales'}),
                el('table', {class: 'wizard-table'}, el('thead', {}, el('tr', {}, el('th', {text: 'Código'}), el('th', {text: 'Nombre'}), el('th', {text: 'Dirección'}))),
                    el('tbody', {}, data.branches.map(b => el('tr', {}, el('td', {text: b.code}), el('td', {text: b.name}), el('td', {text: b.address || '—'}))))),
                rowsEditor('branches', ['code', 'name', 'address'], ['Código', 'Nombre', 'Dirección'], 'Agregar sucursal'),
                el('h3', {class: 'h6 mt-3', text: 'Depósitos'}),
                data.warehouses.length ? el('table', {class: 'wizard-table'}, el('tbody', {}, data.warehouses.map(w => el('tr', {}, el('td', {text: w.code}), el('td', {text: w.name}), el('td', {text: branches[w.branch_id] || '—'})))))
                    : el('p', {class: 'wizard-help', text: 'Se creará "Depósito Central" en Casa Matriz.'}),
                rowsEditor('warehouses', ['code', 'name', 'branch_id'], ['Código', 'Nombre', 'Sucursal'], 'Agregar depósito', {branch_id: branches}));
        },
        5() {
            const rows = data.taxes;
            const body = el('tbody');
            const addRow = row => body.append(el('tr', {'data-row': ''}, el('input', {type: 'hidden', name: 'id', value: row.proposed ? '' : (row.id || '')}),
                el('td', {}, input('code', row.code, {maxlength: 30})), el('td', {}, input('name', row.name, {maxlength: 120})),
                el('td', {}, input('rate', row.rate, {type: 'number', step: '0.01', min: 0, max: 100})), el('td', {}, input('afip_code', row.afip_code, {type: 'number', min: 0})),
                el('td', {}, el('input', {type: 'radio', class: 'form-check-input', name: 'tax_default', checked: Number(row.is_default) === 1})),
                el('td', {}, check('active', Number(row.active ?? 1) === 1))));
            rows.forEach(addRow);
            form.append(el('p', {class: 'wizard-help', text: rows.some(r => r.proposed) ? 'Alícuotas de IVA propuestas con su código AFIP. Revísalas, ajusta las que no uses y marca la predeterminada.' : 'Alícuotas de IVA de la empresa.'}),
                el('table', {class: 'wizard-table'}, el('thead', {}, el('tr', {}, ['Código', 'Nombre', 'Tasa %', 'Código AFIP', 'Predet.', 'Activa'].map(t => el('th', {text: t})))), body),
                el('button', {type: 'button', class: 'btn btn-sm btn-outline-dark', onclick: () => addRow({code: '', name: '', rate: '', afip_code: '', active: 1, proposed: true})}, el('i', {class: 'bi bi-plus-lg me-1'}), 'Agregar alícuota'));
        },
        6() {
            const body = el('tbody');
            const addRow = row => body.append(el('tr', {'data-row': ''}, el('input', {type: 'hidden', name: 'id', value: row.proposed ? '' : (row.id || '')}),
                el('td', {}, input('code', row.code, {maxlength: 30})), el('td', {}, input('name', row.name, {maxlength: 120})),
                el('td', {}, select('type', data.paymentTypes, row.type)), el('td', {}, select('funds_destination', data.paymentDestinations, row.funds_destination)),
                el('td', {}, check('allow_collections', Number(row.allow_collections ?? 1) === 1)), el('td', {}, check('allow_supplier_payments', Number(row.allow_supplier_payments) === 1)),
                el('td', {}, check('requires_confirmation', Number(row.requires_confirmation) === 1)), el('td', {}, check('active', Number(row.active ?? 1) === 1)),
                el('input', {type: 'hidden', name: 'allows_installments', value: Number(row.allows_installments) === 1 ? '1' : '0'}),
                el('input', {type: 'hidden', name: 'percentage', value: row.percentage ?? '0.00'})));
            data.paymentMethods.forEach(addRow);
            form.append(el('p', {class: 'wizard-help', text: 'Medios de cobro y pago. "Pagos a proveedores" habilita el medio en Compras. Las restricciones por sucursal o punto de venta se ajustan luego en Configuración → Medios de pago.'}),
                el('table', {class: 'wizard-table'}, el('thead', {}, el('tr', {}, ['Código', 'Nombre', 'Tipo', 'Destino de fondos', 'Cobros', 'Pagos a proveedores', 'Requiere confirmación', 'Activo'].map(t => el('th', {text: t})))), body),
                el('button', {type: 'button', class: 'btn btn-sm btn-outline-dark', onclick: () => addRow({code: '', name: '', type: 'cash', funds_destination: 'cash', allow_collections: 1, active: 1, proposed: true})}, el('i', {class: 'bi bi-plus-lg me-1'}), 'Agregar medio'));
        },
        7() {
            const a = data.arca || {};
            const d = a.diagnostics;
            form.append(el('p', {class: 'wizard-help', text: 'Facturación electrónica con ARCA. Si todavía no tienes el certificado, usa "Configurar más tarde"; podrás completarlo en Ventas → Configuración.'}),
                el('div', {class: 'wizard-grid'},
                    field('Facturación electrónica', el('div', {class: 'form-check form-switch mt-1'}, check('arca_enabled', Number(a.arca_enabled) === 1, {role: 'switch'}), el('span', {class: 'ms-2', text: 'Activar ARCA'}))),
                    field('Ambiente', select('arca_environment', {homologacion: 'Homologación (pruebas)', produccion: 'Producción'}, a.arca_environment || 'homologacion')),
                    field('CUIT emisor', input('arca_cuit', a.arca_cuit || data.company?.tax_id, {maxlength: 13, inputmode: 'numeric'})),
                    field('Condición frente al IVA', select('arca_iva_condition', {'': 'Selecciona…', ...data.ivaConditions}, a.arca_iva_condition || data.company?.iva_condition)),
                    field('Ingresos brutos', input('arca_iibb', a.arca_iibb, {maxlength: 40})),
                    field('Inicio de actividades', input('arca_start_activities', a.arca_start_activities, {type: 'date'})),
                    field('Punto de venta AFIP (estándar)', input('point_of_sale_standard', a.point_of_sale_standard || 1, {type: 'number', min: 1, max: 99998}), 'Número dado de alta en ARCA para facturar desde el sistema.'),
                    field('Punto de venta AFIP (kiosco)', input('point_of_sale_kiosk', a.point_of_sale_kiosk || 1, {type: 'number', min: 1, max: 99998})),
                    field('Certificado (.crt / .pem)', el('input', {type: 'file', class: 'form-control', name: 'certificate', accept: '.crt,.pem,.cer'}), d?.has_certificate ? 'Ya hay un certificado cargado; sube uno nuevo solo para reemplazarlo.' : null),
                    field('Clave privada (.key)', el('input', {type: 'file', class: 'form-control', name: 'private_key', accept: '.key,.pem'}))));
            if (d) {
                const items = [...(d.readiness?.checks || []), ...(d.certificate?.checks || [])];
                if (items.length) form.append(el('div', {class: 'wizard-subheading'}, el('h3', {text: 'Diagnóstico'})),
                    el('ul', {class: 'wizard-checks'}, items.map(c => el('li', {}, el('i', {class: 'bi ' + (c.ok ? 'bi-check-circle-fill' : 'bi-x-circle-fill'), 'aria-hidden': 'true'}), c.label))),
                    d.certificate?.summary ? el('p', {class: 'wizard-help', text: d.certificate.summary}) : null);
            }
        },
        8() {
            const acc = data.accounting;
            const accounts = {'': 'Sin asignar', ...Object.fromEntries(acc.accounts.map(a => [a.id, a.code + ' · ' + a.name]))};
            form.append(el('p', {class: 'wizard-help', text: 'Cuenta contable que usa cada proceso del sistema para generar sus asientos. Solo se listan cuentas activas que aceptan movimientos.'}),
                el('table', {class: 'wizard-table'}, el('thead', {}, el('tr', {}, el('th', {text: 'Función'}), el('th', {text: 'Cuenta'}))),
                    el('tbody', {}, Object.entries(acc.keys).map(([key, label]) => el('tr', {}, el('td', {text: label}), el('td', {}, select('map_' + key, accounts, acc.mapping[key] || '', {'data-key': key})))))),
                el('div', {class: 'wizard-subheading'}, el('h3', {text: 'Ejercicios contables'})),
                el('ul', {class: 'wizard-checks'}, acc.periods.map(p => el('li', {}, el('i', {class: 'bi bi-check-circle-fill', 'aria-hidden': 'true'}), `${p.name} (${p.start_date} al ${p.end_date}) · ${p.status === 'open' ? 'abierto' : p.status}`))),
                acc.periods.some(p => String(p.start_date).startsWith(String(data.nextYear))) ? null
                    : el('label', {class: 'd-flex gap-2 align-items-center'}, check('open_next_year', false), `Abrir también el Ejercicio ${data.nextYear}`));
        },
        9() {
            const r = data.review;
            if (data.state?.status === 'completed') form.append(el('div', {class: 'wizard-done', text: 'La empresa está configurada. Puedes volver a cualquier paso para ajustar datos.'}));
            form.append(el('p', {class: 'wizard-help', text: `Preparación: ${r.score}%. Los puntos en rojo impiden finalizar; los amarillos son recomendaciones.`}),
                el('ul', {class: 'wizard-checks'},
                    r.blocking.map(t => el('li', {}, el('i', {class: 'bi bi-x-circle-fill', 'aria-hidden': 'true'}), t)),
                    r.warnings.map(t => el('li', {}, el('i', {class: 'bi bi-exclamation-circle-fill', 'aria-hidden': 'true'}), t)),
                    r.ok.map(t => el('li', {}, el('i', {class: 'bi bi-check-circle-fill', 'aria-hidden': 'true'}), t))));
        },
    };
    /** Editable list of new rows (name="{prefix}[i][field]"). */
    function rowsEditor(prefix, fields, labels, addLabel, selects = {}) {
        const body = el('tbody');
        const add = () => body.append(el('tr', {'data-new': prefix}, fields.map(name => el('td', {}, selects[name]
            ? select(name, {'': '—', ...selects[name]}, '') : input(name, '', {maxlength: name === 'code' ? 20 : 150})))));
        return el('div', {}, el('table', {class: 'wizard-table'}, el('thead', {}, el('tr', {}, labels.map(t => el('th', {text: t})))), body),
            el('button', {type: 'button', class: 'btn btn-sm btn-outline-dark', onclick: add}, el('i', {class: 'bi bi-plus-lg me-1'}), addLabel));
    }
    function render() {
        form.replaceChildren();
        if (!data) return;
        if (locked(stage)) { form.append(el('p', {class: 'wizard-help', text: 'Este paso lo completa el superadministrador.'})); return; }
        (renderers[stage] || (() => {}))();
        form.querySelectorAll('input,select,textarea').forEach(node => { node.disabled = node.disabled || (stage === STEP_COUNT && data?.state?.status === 'completed'); });
    }

    // ---------- collect & save ----------
    const fields = names => Object.fromEntries(names.map(name => [name, value(form, name)?.value ?? '']));
    const rows = (selector, build) => [...form.querySelectorAll(selector)].map(build);
    function payload() {
        switch (stage) {
            case 1: {
                const out = fields(['legal_name', 'name', 'tax_id', 'iva_condition', 'iibb', 'start_activities', 'currency_code', 'email', 'phone', 'address']);
                if (!validCuit(out.tax_id)) throw new Error('El CUIT no es válido: revisa los 11 dígitos.');
                return out;
            }
            case 2: return {systems: [...form.querySelectorAll('[name="systems[]"]:checked')].map(node => node.value)};
            case 3: return fields(['name', 'username', 'email', 'password']);
            case 4: return {
                branches: rows('[data-new="branches"]', tr => ({code: value(tr, 'code').value, name: value(tr, 'name').value, address: value(tr, 'address').value})),
                warehouses: rows('[data-new="warehouses"]', tr => ({code: value(tr, 'code').value, name: value(tr, 'name').value, branch_id: value(tr, 'branch_id').value})),
            };
            case 5: return {taxes: rows('#onboarding-form tbody [data-row]', tr => ({id: value(tr, 'id').value, code: value(tr, 'code').value, name: value(tr, 'name').value,
                rate: value(tr, 'rate').value, afip_code: value(tr, 'afip_code').value, is_default: tr.querySelector('[name="tax_default"]').checked ? 1 : 0, active: value(tr, 'active').checked ? 1 : 0}))};
            case 6: return {methods: rows('#onboarding-form tbody [data-row]', tr => ({id: value(tr, 'id').value, code: value(tr, 'code').value, name: value(tr, 'name').value,
                type: value(tr, 'type').value, funds_destination: value(tr, 'funds_destination').value, allow_collections: value(tr, 'allow_collections').checked ? 1 : 0,
                allow_supplier_payments: value(tr, 'allow_supplier_payments').checked ? 1 : 0, requires_confirmation: value(tr, 'requires_confirmation').checked ? 1 : 0,
                active: value(tr, 'active').checked ? 1 : 0, allows_installments: value(tr, 'allows_installments').value, percentage: value(tr, 'percentage').value}))};
            case 7: {
                const body = new FormData();
                for (const name of ['arca_environment', 'arca_cuit', 'arca_iva_condition', 'arca_iibb', 'arca_start_activities', 'point_of_sale_standard', 'point_of_sale_kiosk']) body.append(name, value(form, name).value);
                body.append('arca_enabled', value(form, 'arca_enabled').checked ? '1' : '0');
                for (const name of ['certificate', 'private_key']) { const file = value(form, name).files[0]; if (file) body.append(name, file); }
                return body;
            }
            case 8: return {mapping: Object.fromEntries([...form.querySelectorAll('[data-key]')].map(node => [node.dataset.key, node.value])),
                open_years: value(form, 'open_next_year')?.checked ? [data.nextYear] : []};
            default: return {};
        }
    }
    async function save() {
        if (busy || locked(stage)) return;
        busy = true; byId('onboarding-save').disabled = true;
        try {
            if (stage === STEP_COUNT) {
                const result = await request(`/${encodeURIComponent(companyId)}/finalizar`, 'POST', {});
                await load(); message(result.message);
                return;
            }
            const body = payload();
            if (!companyId) {
                const result = await request('', 'POST', body);
                companyId = result.companyId;
                history.replaceState(null, '', config.companiesUrl + '/' + encodeURIComponent(companyId) + '/asistente');
                await load(); message(result.message);
                return;
            }
            const arcaOn = stage === 7 && Boolean(value(form, 'arca_enabled')?.checked);
            const result = await request(`/${encodeURIComponent(companyId)}/paso/${stage}`, 'POST', body);
            const current = stage;
            await load();
            // With ARCA enabled but not ready, stay on the step so the diagnostics can be reviewed.
            const stay = current === 7 && arcaOn && !result.diagnostics?.readiness?.ready;
            message(result.message + (stay ? ' Revisa el diagnóstico de ARCA: la configuración todavía no está lista para facturar.' : ''), stay);
            if (current < STEP_COUNT) go(stay ? 7 : current + 1);
        } catch (error) { message(error.message, true); }
        finally { busy = false; byId('onboarding-save').disabled = locked(stage); }
    }
    byId('onboarding-save').addEventListener('click', save);
    byId('onboarding-prev').addEventListener('click', () => go(stage - 1));
    byId('onboarding-skip').addEventListener('click', async () => {
        if (busy || !companyId) return;
        try { const result = await request(`/${encodeURIComponent(companyId)}/omitir/${stage}`, 'POST', {}); const current = stage; await load(); message(result.message); go(current + 1); }
        catch (error) { message(error.message, true); }
    });
    form.addEventListener('submit', event => { event.preventDefault(); save(); });
    load().catch(error => message(error.message, true));
}
