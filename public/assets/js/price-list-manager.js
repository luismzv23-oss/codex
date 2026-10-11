(() => {
    const root=document.getElementById('price-manager');if(!root)return;
    const $=id=>root.querySelector('#price-'+id), money=n=>Number(n).toLocaleString('es-AR',{minimumFractionDigits:2,maximumFractionDigits:2});
    let products=[],lists=[],selected='',editing='',page=1,busy=false;
    const drafts=new Map();
    function message(text,error=false){$('status').textContent=text;$('status').className=error?'text-danger':'text-success';}
    function button(icon,label,action){const b=document.createElement('button');b.type='button';b.className='btn btn-outline-dark icon-btn';b.title=label;b.setAttribute('aria-label',label);const i=document.createElement('i');i.className='bi bi-'+icon;b.append(i);b.addEventListener('click',action);return b;}
    async function request(payload){
        const url=new URL(root.dataset.endpoint,location.origin);url.searchParams.set('company_id',root.dataset.company);if(selected)url.searchParams.set('list_id',selected);
        const headers={'X-Requested-With':'XMLHttpRequest'};if(payload){headers['Content-Type']='application/json';headers[root.dataset.header]=root.dataset.csrf;}
        const response=await fetch(url,{method:payload?'POST':'GET',headers,body:payload?JSON.stringify(payload):undefined,credentials:'same-origin',cache:'no-store'});
        if(response.redirected||!response.headers.get('content-type')?.includes('application/json'))throw Error('Vuelve a iniciar sesion para gestionar los precios.');
        const data=await response.json();if(data.csrf)root.dataset.csrf=data.csrf;if(!response.ok)throw Error(data.error||'No se pudo completar la operacion.');
        lists=data.lists;products=data.products;selected=data.list_id;$('list').replaceChildren(...lists.map(l=>new Option(l.name+(Number(l.active)?'':' (inactiva)'),l.id)));$('list').value=selected;render();
    }
    async function work(action){if(busy)return;busy=true;root.inert=true;try{await action();}catch(e){message(e.message,true);}finally{busy=false;root.inert=false;}}
    function render(){
        const query=$('search').value.trim().toLocaleLowerCase();const filtered=products.filter(p=>(p.sku+' '+p.name).toLocaleLowerCase().includes(query));const pages=Math.max(1,Math.ceil(filtered.length/5));page=Math.max(1,Math.min(page,pages));$('products').replaceChildren();
        filtered.slice((page-1)*5,page*5).forEach(p=>{
            const row=document.createElement('form');row.className='price-product-row';
            const title=document.createElement('span');title.className='price-product-name';title.textContent=p.sku+' ? '+p.name+(Number(p.active)?'':' (inactivo)');title.title=`${title.textContent} ? ${p.brand||'Sin marca'} ? ${p.unit||'unidad'} ? Stock: ${p.stock} ? Disponible: ${p.available} ? Inventario: ${money(p.sale_price)}`;
            const stock=document.createElement('span');stock.className='price-stock';stock.textContent=p.stock;
            const available=document.createElement('span');available.className='price-stock';available.textContent=p.available;
            const current=document.createElement('span');current.className='price-current';current.textContent=money(p.sale_price);
            const input=document.createElement('input');input.type='number';input.step='.01';input.min='.01';input.max='9999999999';input.required=true;input.className='form-control price-edit';input.setAttribute('aria-label','Precio de lista de '+p.name);input.title='Precio de lista';input.value=drafts.get(selected+':'+p.id)??Number(p.list_price??p.sale_price).toFixed(2);input.addEventListener('input',()=>drafts.set(selected+':'+p.id,input.value));
            const save=button('check-lg','Guardar precio de '+p.name,()=>{});save.type='submit';save.disabled=!selected;
            row.append(title,stock,available,current,input,save);$('products').append(row);
            row.addEventListener('submit',e=>{e.preventDefault();work(async()=>{const list=selected;await request({action:'price',list_id:list,product_id:p.id,price:input.value});drafts.delete(list+':'+p.id);render();message('Precio actualizado: '+p.name);});});
        });
        if(!filtered.length)$('products').textContent='No hay productos para esta busqueda.';
        const prev=button('chevron-left','Pagina anterior',()=>{page--;render();});prev.disabled=page===1;
        const next=button('chevron-right','Pagina siguiente',()=>{page++;render();});next.disabled=page===pages;
        const count=document.createElement('span');count.className='small';count.textContent=`${page} / ${pages} · ${filtered.length} productos`;$('pages').replaceChildren(prev,count,next);
    }
    function metadata(id){editing=id;const list=lists.find(l=>l.id===id)||{};$('name').value=list.name||'';$('description').value=list.description||'';$('default').value=String(list.is_default||0);$('active').value=String(list.active??1);$('metadata').hidden=false;$('name').focus();}
    $('search').addEventListener('input',()=>{page=1;render();});
    $('list').addEventListener('change',()=>work(async()=>{selected=$('list').value;page=1;await request();message('');}));
    $('new').addEventListener('click',()=>metadata(''));$('settings').addEventListener('click',()=>metadata(selected));$('cancel').addEventListener('click',()=>{$('metadata').hidden=true;});
    $('metadata').addEventListener('submit',e=>{e.preventDefault();work(async()=>{await request({action:'metadata',list_id:editing,name:$('name').value,description:$('description').value,is_default:Number($('default').value),active:Number($('active').value)});$('metadata').hidden=true;message('Lista guardada. Ya puedes actualizar sus productos.');});});
    work(async()=>{await request();if(!lists.length)metadata('');});
})();
