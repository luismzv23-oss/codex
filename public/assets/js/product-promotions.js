(() => {
    const root = document.getElementById('promotion-manager');
    if (!root) return;
    const $ = id => root.querySelector('#promo-' + id);
    const money = n => Number(n).toLocaleString('es-AR', {minimumFractionDigits:2, maximumFractionDigits:2});
    let rows = [], page = 1, product = null, timer, sequence = 0, saving = false;
    const message = (text, error=false) => { $('status').textContent=text; $('status').className=error?'mt-2 text-danger':'mt-2 text-success'; };
    const button = (icon, title, action) => {
        const b=document.createElement('button'); b.type='button'; b.className='btn btn-outline-dark icon-btn'; b.title=title; b.setAttribute('aria-label',title);
        const i=document.createElement('i'); i.className='bi bi-'+icon; b.append(i); b.addEventListener('click',action); return b;
    };
    async function request(endpoint, payload, query='') {
        const url=new URL(endpoint,location.origin); url.searchParams.set('company_id',root.dataset.company); if(query)url.searchParams.set('q',query);
        const headers={'X-Requested-With':'XMLHttpRequest'};
        if(payload){headers['Content-Type']='application/json';headers[root.dataset.header]=root.dataset.csrf;}
        const response=await fetch(url,{method:payload?'POST':'GET',headers,body:payload?JSON.stringify(payload):undefined,credentials:'same-origin',cache:'no-store'});
        if(response.redirected || !response.headers.get('content-type')?.includes('application/json'))throw Error('La sesion o el permiso no estan disponibles. Vuelve a abrir Promociones.');
        const data=await response.json();if(data.csrf)root.dataset.csrf=data.csrf;
        if(!response.ok)throw Error(data.error||data.message||'No se pudo completar la operacion.');return data;
    }
    function render() {
        const q=$('filter').value.toLocaleLowerCase();const filtered=rows.filter(r=>(r.sku+' '+r.product_name).toLocaleLowerCase().includes(q));
        const count=Math.max(1,Math.ceil(filtered.length/5));page=Math.min(page,count);$('list').replaceChildren();
        filtered.slice((page-1)*5,page*5).forEach(r=>{
            const card=document.createElement('div');card.className='border-bottom py-3';
            const title=document.createElement('strong');title.textContent=r.sku+' · '+r.product_name;
            const detail=document.createElement('div');detail.className='small text-secondary';
            const price=r.promotion_type==='price'?'Precio promocional: '+money(r.value):r.promotion_type==='percent'?'Descuento: '+money(r.value)+' %':r.promotion_type==='fixed'?'Descuento: '+money(r.value):r.name;
            const now=new Date();const start=r.start_date?new Date(r.start_date.replace(' ','T')):null;const end=r.end_date?new Date(r.end_date.replace(' ','T')):null;
            const state=Number(r.active)!==1?'Inactiva':end&&end<now?'Finalizada':start&&start>now?'Programada':'Vigente';
            detail.textContent=`Actual: ${money(r.sale_price)} · ${price} · ${state}`;
            const dates=document.createElement('div');dates.className='small';dates.textContent=`${start?start.toLocaleString('es-AR'):'Sin inicio'} → ${end?end.toLocaleString('es-AR'):'Sin fin'}`;
            card.append(title,detail,dates);$('list').append(card);
        });
        if(!filtered.length)$('list').textContent='No hay productos con promociones para mostrar.';
        const prev=button('chevron-left','Pagina anterior',()=>{page--;render();});prev.disabled=page<=1;
        const next=button('chevron-right','Pagina siguiente',()=>{page++;render();});next.disabled=page>=count;
        const label=document.createElement('span');label.textContent=`${page} / ${count} · ${filtered.length} registros`;$('pages').replaceChildren(prev,label,next);
    }
    function choose(p) {
        p={...p,sale_price:p.regular_price ?? p.sale_price};product=p;$('form').hidden=false;$('results').replaceChildren();
        const stocks=Object.values(p.stocks||{});const stock=stocks.reduce((s,v)=>s+Number(v.stock||0),0);const available=stocks.reduce((s,v)=>s+Number(v.available||0),0);
        $('product').textContent=`${p.sku} · ${p.name} | ${p.brand||'Sin marca'} | ${p.unit} | Stock: ${stock} | Disponible: ${available} | Precio actual: ${money(p.regular_price ?? p.sale_price)}`;
        $('price').value='';$('price').max=(Number(p.sale_price)-.01).toFixed(2);$('saving').textContent='';$('price').focus();
    }
    $('search').addEventListener('input',()=>{
        clearTimeout(timer);const version=++sequence;const q=$('search').value.trim();$('results').replaceChildren();if(q.length<2)return;
        timer=setTimeout(async()=>{try{const data=await request(root.dataset.search,null,q);if(version!==sequence)return;
            data.products.slice(0,5).forEach(p=>{const line=document.createElement('div');line.className='d-flex justify-content-between align-items-center gap-3 border-bottom py-2';const text=document.createElement('span');text.textContent=`${p.sku} · ${p.name} · ${money(p.regular_price ?? p.sale_price)}`;line.append(text,button('plus-lg','Seleccionar '+p.name,()=>choose(p)));$('results').append(line);});
            if(!data.products.length)$('results').textContent='No se encontraron productos.';
        }catch(e){if(version===sequence)message(e.message,true);}},300);
    });
    $('price').addEventListener('input',()=>{$('saving').textContent=product&&Number($('price').value)>0?`Ahorro: ${money(product.sale_price-$('price').value)}`:'';});
    $('cancel').addEventListener('click',()=>{product=null;$('form').hidden=true;});
    $('filter').addEventListener('input',()=>{page=1;render();});
    $('form').addEventListener('submit',async event=>{
        event.preventDefault();if(saving||!product)return;saving=true;root.inert=true;
        try{const data=await request(root.dataset.endpoint,{product_id:product.id,price:$('price').value,start:$('start').value,end:$('end').value});rows=data.rows;page=1;$('filter').value='';render();$('form').hidden=true;product=null;message('Promocion guardada. El listado ya esta actualizado.');}
        catch(e){message(e.message,true);}finally{saving=false;root.inert=false;}
    });
    request(root.dataset.endpoint).then(data=>{rows=data.rows;render();}).catch(e=>message(e.message,true));
})();
