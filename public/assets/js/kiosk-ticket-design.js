/* Shared by the kiosk and its design preview. All supplied content is plain text. */
(() => {
    const escape = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const money = value => Number(value || 0).toLocaleString('es-AR', {minimumFractionDigits:2, maximumFractionDigits:2});
    const qrImage = value => {
        if (!value || typeof qrcode !== 'function') return '';
        const qr = qrcode(0, 'M');
        qr.addData(value, 'Byte');
        qr.make();
        return qr.createSvgTag({cellSize:3, margin:12, scalable:true});
    };
    window.renderKioskTicket = (settings, data, preview = false) => {
        const get = key => settings['ticket_' + key] ?? settings[key];
        const show = key => (!preview && data.cae && ['show_qr', 'show_authorization'].includes(key)) || Number(get(key) ?? 1) === 1;
        const text = (key, fallback = '') => get(key) || fallback;
        const row = (label, value) => `<div class="row"><span>${escape(label)}</span><span>${escape(value)}</span></div>`;
        const block = (flag, content) => show(flag) && content ? `<section>${content}</section>` : '';
        const line = content => content ? `<div>${escape(content)}</div>` : '';
        const fonts = {'Courier':'"Courier New",monospace','Helvetica':'Arial,sans-serif','Helvetica 75 Bold':'Arial,sans-serif','DejaVu Sans':'"DejaVu Sans",sans-serif','DejaVu Serif':'"DejaVu Serif",serif','Times-Roman':'"Times New Roman",serif'};
        const width = text('paper_width') === '58mm' ? '58mm' : '80mm';
        const size = {small:10,medium:12,large:14}[text('font_size')] || 12;
        const visiblePayments = (data.payments || []).filter(p => Number(p.show_on_receipt ?? 1) === 1);
        const vat = data.containedVat ?? (data.taxes || []).reduce((sum, tax) => sum + Number(tax.amount || 0), 0);
        const qr = show('show_qr') ? qrImage(data.qrUrl || (preview ? 'VISTA PREVIA SIN VALIDEZ FISCAL' : '')) : '';
        const fiscal = data.cae ? `<strong class="fiscal-title">FACTURACIÓN ELECTRÓNICA</strong>${line('CAE: ' + data.cae)}${data.caeDueDate ? line('Fecha Vto.: ' + data.caeDueDate) : ''}${data.processedAt ? line('Fecha proceso: ' + data.processedAt) : ''}${data.testEnvironment ? '<strong>HOMOLOGACIÓN · SIN VALIDEZ FISCAL</strong>' : ''}` : '<div>Comprobante sin autorización fiscal disponible</div>';
        const items = (data.items || []).map(item => {
            const base = Number(item.quantity) * Number(item.unit_price);
            const discount = base * Number(item.discount_rate || 0) / 100;
            return `<div class="item"><strong>${show('show_sku') && item.sku ? escape(item.sku) + ' · ' : ''}${escape(item.name)}</strong>
                ${show('show_brand') ? line(item.brand) : ''}
                ${row(show('show_item_breakdown') ? `${item.quantity} x ${money(item.unit_price)}` : `Cant.: ${item.quantity}`, money(item.line_total ?? (base - discount)))}
                ${show('show_discounts') && discount > 0 ? row(`Descuento ${money(item.discount_rate)} %`, '-' + money(discount)) : ''}</div>`;
        }).join('');
        return `<!doctype html><html lang="es"><head><meta charset="utf-8"><title>Ticket</title><style>
        @page{size:${width} auto;margin:3mm}*{box-sizing:border-box}body{margin:0;background:${preview ? '#fff' : '#f3f1ed'};color:#000;font-family:${fonts[text('font_family')] || fonts.Courier};font-size:${size}px;font-weight:${text('font_family') === 'Helvetica 75 Bold' ? 'bold' : 'normal'}}
        article{width:${width};max-width:100%;margin:0 auto;padding:4mm;background:#fff;overflow-wrap:anywhere}header{text-align:center;white-space:pre-line}header h1{font-size:1.35em;margin:0 0 5px}section{border-top:1px dashed #777;margin-top:8px;padding-top:7px;white-space:pre-line}.row{display:flex;justify-content:space-between;gap:12px}.row>span:last-child{white-space:nowrap}.item{padding:7px 0}.item>strong{display:block}.total{font-size:1.3em;font-weight:bold;border-top:1px solid #000;margin-top:8px;padding-top:8px}.footer{text-align:center}.notice{text-align:center;font-size:.85em;padding:5px}.actions{display:flex;justify-content:center;gap:12px;padding:15px}.actions button{padding:8px 12px;cursor:pointer}.fiscal-title{display:block;text-align:center;border-top:2px solid #000;border-bottom:2px solid #000;margin-bottom:5px}.qr{text-align:center;break-inside:avoid}.qr svg{display:block;width:42mm;max-width:100%;height:auto;margin:8px auto}.contact{text-align:center;padding:8px 0;font-weight:bold}.contact .phone{font-size:1.3em;margin-top:5px}.thanks{border:1px solid #000;text-align:center;padding:7px;font-weight:bold;margin:10px 0;break-inside:avoid}@media print{body{background:#fff}.actions{display:none}article{width:100%;padding:0}}</style></head><body><article>
        ${data.draft ? '<div class="notice">VISTA PREVIA · VENTA SIN REGISTRAR</div>' : ''}
        <header>${show('show_header') ? `<h1>${escape(text('header_title',data.companyName))}</h1>` : ''}
        ${show('show_subtitle') ? line(text('company_subtitle')) : ''}${show('show_tax_id') && data.taxId ? line('CUIT: ' + data.taxId) : ''}
        ${show('show_address') ? line(text('company_address')) : ''}${show('show_phone') ? line(text('company_phone')) : ''}
        ${block('show_custom_header', `<div style="font-weight:${Number(get('bold_top_left'))===1?'bold':'normal'}">${escape(text('custom_text_top_left'))}</div><div style="font-weight:${Number(get('bold_top_right'))===1?'bold':'normal'}">${escape(text('custom_text_top_right'))}</div>`)}
        ${block('show_date', line(data.date))}${show('show_document') ? line(data.document) : ''}${show('show_reference') ? line(data.reference) : ''}${show('show_currency') ? line(data.currency) : ''}</header>
        ${block('show_customer',data.customer ? line('Cliente: ' + data.customer) : '')}
        <section>${items}</section>
        ${block('show_subtotal',row('Subtotal neto',money(data.subtotal)))}
        ${block('show_taxes',(data.taxes || []).map(t=>row(t.label,money(t.amount))).join(''))}
        ${block('show_discounts',data.discount > 0 ? row('Descuento por medio de pago','-' + money(data.discount)) : '')}
        ${block('show_surcharges',visiblePayments.filter(p=>p.surcharge>0).map(p=>row(`Recargo ${p.code} (${money(p.rate)} %)`,money(p.surcharge))).join(''))}
        <div class="total">${row('TOTAL A PAGAR',money(data.total))}</div>
        ${block('show_payments',visiblePayments.map(p=>row(p.code + (p.status === 'pending' || (!p.status && p.type === 'transfer') ? ' (pendiente)' : ''),money(p.total))).join(''))}
        ${block('show_transparency',line('RÉGIMEN DE TRANSPARENCIA FISCAL AL CONSUMIDOR (Ley 27.743)') + row('IVA contenido:', money(vat)) + row('Otros impuestos nacionales indirectos:', data.nationalTaxes == null ? 'No informado' : money(data.nationalTaxes)) + line('Los impuestos informados son solo los que corresponden a nivel nacional.'))}
        ${block('show_authorization',fiscal)}
        ${block('show_qr',qr ? `<div class="qr">${qr}${preview ? '<small>QR DE EJEMPLO · SIN VALIDEZ FISCAL</small>' : ''}</div>` : (data.cae ? line('QR no disponible: faltan datos de autorización.') : ''))}
        ${show('show_item_count') || (show('show_user') && data.user) ? `<section>${row(show('show_item_count') ? 'Art.: ' + (data.items || []).reduce((s,i)=>s+Number(i.quantity),0) : '', show('show_user') && data.user ? 'Cajero: ' + data.user : '')}</section>` : ''}
        ${block('show_footer',text('footer_notes') ? `<div class="footer">${escape(text('footer_notes'))}</div>` : '')}
        ${block('show_contact',`<div class="contact">${line(text('contact_title','Atendemos tus consultas'))}${text('contact_phone') ? `<div class="phone">Tel.: ${escape(text('contact_phone'))}</div>` : ''}${text('contact_whatsapp') ? `<div class="phone">WhatsApp: ${escape(text('contact_whatsapp'))}</div>` : ''}</div>`)}
        ${show('show_thanks') && text('thanks_text','Gracias por tu compra') ? `<div class="thanks">${escape(text('thanks_text','Gracias por tu compra'))}</div>` : ''}
        </article>${preview ? '' : '<div class="actions"><button onclick="window.print()">Imprimir</button><button onclick="if(window.parent!==window){window.parent.postMessage(\'close-sale-receipt\',window.location.origin)}else{window.close()}">Cerrar</button></div>'}</body></html>`;
    };
})();
