window.openSaleReceiptWindow = function (onClose) {
    const dialog = document.createElement('dialog');
    dialog.setAttribute('aria-label', 'Comprobante de venta');
    dialog.style.cssText = 'width:min(1000px,96vw);height:92vh;max-width:96vw;max-height:94vh;padding:0;border:1px solid #ddd;border-radius:16px;overflow:hidden;';
    dialog.innerHTML = '<div style="display:flex;align-items:center;gap:12px;padding:12px 18px;border-bottom:1px solid #ddd"><strong style="flex:1">Comprobante de venta</strong><button type="button" data-print disabled>Imprimir</button><button type="button" data-close>Cerrar</button></div><p role="status" style="padding:18px">Registrando la venta y preparando el comprobante…</p><iframe title="Comprobante para imprimir" style="display:none;width:100%;height:calc(100% - 65px);border:0"></iframe>';
    const frame = dialog.querySelector('iframe');
    const status = dialog.querySelector('[role="status"]');
    const print = dialog.querySelector('[data-print]');
    let ready = false;
    let closed = false;
    const close = () => {
        if (closed) return;
        closed = true;
        window.removeEventListener('message', receive);
        dialog.close();
        dialog.remove();
        if (ready && typeof onClose === 'function') onClose();
    };
    const receive = event => {
        if (event.origin === window.location.origin && event.source === frame.contentWindow && event.data === 'close-sale-receipt') close();
    };
    window.addEventListener('message', receive);
    dialog.querySelector('[data-close]').onclick = close;
    dialog.addEventListener('cancel', event => { event.preventDefault(); close(); });
    print.onclick = () => { frame.contentWindow.focus(); frame.contentWindow.print(); };
    frame.onload = () => { if (ready) { status.hidden = true; print.disabled = false; } };
    document.body.appendChild(dialog);
    dialog.showModal();
    return {
        show(url) {
            const target = new URL(url, window.location.href);
            if (target.origin !== window.location.origin) throw new Error('Dirección del comprobante inválida.');
            ready = true;
            frame.src = target.href;
            frame.style.display = 'block';
            if (closed) { closed = false; window.addEventListener('message', receive); document.body.appendChild(dialog); dialog.showModal(); }
            return true;
        },
        close
    };
};
