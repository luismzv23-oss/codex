const fs=require('node:fs'),path=require('node:path'),os=require('node:os'),assert=require('node:assert/strict');
const {pathToFileURL}=require('node:url');
const root=path.resolve(__dirname,'../..');
const dir=fs.mkdtempSync(path.join(os.tmpdir(),'codex-controls-ui-'));
const artifact=path.join(root,'writable/private/ui-operational');
const asset=p=>pathToFileURL(path.join(root,'public',p)).href;
const modules={'admin-users':['users-directory.js','logout-cash-check.js']};
(async()=>{let browser,closeBrowser;try{
  browser=require('node:child_process').spawn(process.env.INVENTORY_BROWSER_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe',[
   '--headless=new','--disable-gpu','--disable-background-networking','--no-first-run','--remote-debugging-pipe',
   '--user-data-dir='+path.join(dir,'profile')
  ],{windowsHide:true,stdio:['ignore','ignore','ignore','pipe','pipe']});
  let seq=0,buffer='';const pending=new Map();
  browser.stdio[4].on('data',chunk=>{
   buffer+=chunk.toString();let end;
   while((end=buffer.indexOf('\0'))!==-1){
    const msg=JSON.parse(buffer.slice(0,end));buffer=buffer.slice(end+1);
    if(pending.has(msg.id)){const {resolve,reject,timer}=pending.get(msg.id);clearTimeout(timer);pending.delete(msg.id);msg.error?reject(Error(JSON.stringify(msg.error))):resolve(msg.result);}
   }
  });
  const send=(method,params={},sessionId)=>new Promise((resolve,reject)=>{
   const id=++seq;const timer=setTimeout(()=>{pending.delete(id);reject(Error('Timeout '+method))},15000);
   pending.set(id,{resolve,reject,timer});browser.stdio[3].write(JSON.stringify({id,method,params,...(sessionId?{sessionId}:{})})+'\0');
  });
  closeBrowser=()=>send('Browser.close');
  for(const [name,scripts] of Object.entries(modules)) {
   let html=fs.readFileSync(path.join(artifact,name+'.html'),'utf8');
   html=html.replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi,'').replace(/https?:\/\/[^"']*\/assets\/([^"']+)/g,(_,p)=>asset('assets/'+p));
   html=html.replace('</head>',`<script>window.testErrors=[];addEventListener('error',e=>testErrors.push(e.message));</script>`+['codex-controls.js','codex-ui.js',...scripts].map(s=>`<script defer src="${asset('assets/js/'+s)}"></script>`).join('')+`<script defer src="${asset('assets/vendor/alpinejs/csp-3.17.4.min.js')}"></script></head>`);
   html=html.replace('</main>', `
<nav x-data="codexNavigation" id="test-nav" class="navbar navbar-expand-lg">
<button type="button" class="navbar-toggler" @click="toggleMobile" :aria-expanded="mobileOpen">Menú</button>
<div id="test-collapse" class="collapse navbar-collapse" :class="mobileClass">
<div class="dropdown"><button type="button" id="menu-a" data-codex-dropdown aria-expanded="false">Sistemas</button><div class="dropdown-menu"><a href="#first">Primero</a><a href="#last">Último</a></div></div>
<div class="dropdown"><button type="button" id="menu-b" data-codex-dropdown aria-expanded="false">Configuración</button><div class="dropdown-menu"><a href="#config">Configuración</a></div></div>
</div></nav>
<div role="tablist" class="nav"><button type="button" id="tab-a" role="tab" class="active" data-codex-toggle="tab" data-codex-target="#pane-a">Uno</button><button type="button" id="tab-b" role="tab" data-codex-toggle="tab" data-codex-target="#pane-b">Dos</button></div>
<div class="tab-content"><div id="pane-a" class="tab-pane active show">Uno</div><div id="pane-b" class="tab-pane">Dos</div></div>
<button type="button" id="dialog-open" data-codex-toggle="modal" data-codex-target="#test-modal">Abrir</button>
<form id="modal-form"><div id="test-modal" class="modal fade" aria-hidden="true"><div class="modal-dialog"><div class="modal-content"><h2 class="modal-title">Buscar producto</h2><input name="product" value="SKU123"><button type="button" id="dialog-close" data-codex-dismiss="modal">Cerrar</button></div></div></div></form>
<div id="openCashModal" class="modal fade" aria-hidden="true"><div class="modal-dialog"><div class="modal-content"><h2 class="modal-title">Caja abierta</h2><button type="button" data-codex-dismiss="modal">Cancelar</button></div></div></div>
</main>`);
   const file=path.join(dir,name+'.html');fs.writeFileSync(file,html);
   for(const width of [1440,390]) {
    const {targetId}=await send('Target.createTarget',{url:'about:blank'});
    const {sessionId}=await send('Target.attachToTarget',{targetId,flatten:true});
    const call=(method,params)=>send(method,params,sessionId);
    await call('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:width<600});
    await call('Page.navigate',{url:pathToFileURL(file).href});
    await new Promise(r=>setTimeout(r,1400));
    const evaluation=await call('Runtime.evaluate',{awaitPromise:true,returnByValue:true,expression:`(async()=>{
     let checks=0;const failures=[];const check=(ok,msg)=>{checks++;if(!ok)failures.push(msg)};
     const wait=()=>new Promise(r=>setTimeout(r,100));
     check(document.body.dataset.codexUi==='light','Light theme');
     check(getComputedStyle(document.body).backgroundColor==='rgb(244, 247, 251)','Clear background');
     check(document.documentElement.scrollWidth<=innerWidth,'No page overflow');
     check(!!document.querySelector('[x-data]')._x_dataStack,'Alpine initialized');
     const nav=document.getElementById('test-nav'),a=document.getElementById('menu-a'),b=document.getElementById('menu-b');
     nav.querySelector('.navbar-toggler').click();await wait();
     check(nav.querySelector('#test-collapse').classList.contains('show'),'Mobile toggle');
     a.click();check(a.getAttribute('aria-expanded')==='true','Dropdown opens');
     b.click();check(a.getAttribute('aria-expanded')==='false'&&b.getAttribute('aria-expanded')==='true','Only one menu open');
     b.dispatchEvent(new KeyboardEvent('keydown',{key:'Escape',bubbles:true}));check(document.activeElement===b&&b.getAttribute('aria-expanded')==='false','Menu Escape returns focus');
     a.dispatchEvent(new KeyboardEvent('keydown',{key:'ArrowDown',bubbles:true}));check(document.activeElement.textContent==='Primero','Arrow enters menu');
     document.activeElement.dispatchEvent(new KeyboardEvent('keydown',{key:'End',bubbles:true}));check(document.activeElement.textContent==='Último','End navigates menu');
     document.body.click();check(a.getAttribute('aria-expanded')==='false','Outside closes menu');
     let shown=0,hidden=0,tabShown=0;
     document.getElementById('tab-b').addEventListener('codex:tab-shown',()=>tabShown++);
     document.getElementById('tab-b').click();check(tabShown===1&&document.getElementById('pane-b').classList.contains('active')&&!document.getElementById('pane-a').classList.contains('active'),'Tab activation and preview event');
     document.getElementById('tab-b').dispatchEvent(new KeyboardEvent('keydown',{key:'ArrowLeft',bubbles:true}));check(document.activeElement.id==='tab-a'&&document.getElementById('tab-a').getAttribute('aria-selected')==='true','Tab keyboard and aria');
     const modal=document.getElementById('test-modal'),opener=document.getElementById('dialog-open');
     modal.addEventListener('codex:dialog-shown',()=>shown++);modal.addEventListener('codex:dialog-hidden',()=>hidden++);
     opener.focus();opener.click();await wait();
     check(modal.closest('dialog').open&&modal.classList.contains('show')&&shown===1,'Modal opens and emits event');
     check(modal.contains(document.activeElement),'Modal initial focus');
     check(new FormData(document.getElementById('modal-form')).get('product')==='SKU123','Form association preserved');
     document.getElementById('dialog-close').click();await wait();
     check(!modal.closest('dialog').open&&hidden===1&&document.activeElement===opener,'Close and restore focus');
     opener.click();await wait();modal.closest('dialog').dispatchEvent(new Event('cancel',{cancelable:true}));await wait();
     check(hidden===2&&!modal.classList.contains('show'),'Escape cancellation');
     window.CodexDialogs.getOrCreateInstance(modal).show();await wait();modal.click();await wait();check(hidden===3,'Backdrop closes');
     let requests=0,options,alerts=0;window.alert=()=>alerts++;
     const logout=document.getElementById('logout-cash-check');
     window.fetch=async(url,opts)=>{requests++;options=opts;return {ok:true,status:200,json:async()=>({open:true})}};
     logout.click();await wait();
     check(requests===1&&options.cache==='no-store'&&options.credentials==='same-origin','Logout checks live state');
     check(document.getElementById('openCashModal').closest('dialog').open&&!logout.disabled,'Cash warning shown');
     window.CodexDialogs.getOrCreateInstance(document.getElementById('openCashModal')).hide();await wait();
     window.fetch=async()=>{throw Error('offline')};logout.click();await wait();check(alerts===1&&!logout.disabled,'Logout failure recoverable');
     check(document.documentElement.scrollWidth<=innerWidth,'No overflow after interactions');
     const searchForm=document.createElement('form');
     searchForm.innerHTML='<div x-data="codexSearchSelect" class="codex-search-select"><select class="form-select select-search" name="items_product_id[]" required><option value="">Producto</option><option value="sku1" data-cost="12">001 - Café</option><option value="sku2" data-cost="20">002 - Agua</option></select></div>';
     document.querySelector('main').append(searchForm);await wait();
     const search=searchForm.querySelector('input[type="search"]'),select=searchForm.querySelector('select');
     check(!!search,'Dynamic purchase row initializes');
     let inputs=0,changes=0;select.addEventListener('input',()=>inputs++);select.addEventListener('change',()=>changes++);
     search.value='cafe';search.dispatchEvent(new Event('input'));await wait();
     check([...select.options].filter(o=>!o.hidden).map(o=>o.value).join()==='sku1','Accent-insensitive search');
     check(select.value===''&&!select.validity.valid&&inputs===0&&changes===0,'Search does not change selection or validity');
     select.value='sku1';select.dispatchEvent(new Event('input',{bubbles:true}));select.dispatchEvent(new Event('change',{bubbles:true}));
     check(new FormData(searchForm).get('items_product_id[]')==='sku1'&&select.selectedOptions[0].dataset.cost==='12'&&inputs===1&&changes===1,'Native submission, metadata and events preserved');
     search.value='unknown';search.dispatchEvent(new Event('input'));check(select.value==='sku1','No match preserves value');
     search.dispatchEvent(new KeyboardEvent('keydown',{key:'Escape',bubbles:true}));check([...select.options].every(o=>!o.hidden),'Escape clears filter');
     const enter=new KeyboardEvent('keydown',{key:'Enter',bubbles:true,cancelable:true});search.dispatchEvent(enter);check(enter.defaultPrevented,'Search Enter does not submit');
     select.disabled=true;await wait();check(search.disabled,'Disabled synchronized');select.disabled=false;await wait();
     searchForm.reset();await wait();check(search.value===''&&select.value==='','Native reset');
     check(searchForm.querySelectorAll('input[type="search"]').length===1,'One search control');
     searchForm.remove();await wait();
     check(testErrors.length===0,'No JS errors: '+testErrors.join(';'));
     return {checks,failures,width:innerWidth};
    })()`});
    const result=evaluation.result.value;assert.ok(result,JSON.stringify(evaluation));console.log(JSON.stringify({module:name,...result}));assert.deepEqual(result.failures,[]);
    await call('Runtime.evaluate',{expression:'window.scrollTo(0,0)'});
    const capture=await call('Page.captureScreenshot',{format:'png'});fs.writeFileSync(path.join(artifact,'controls-'+width+'.png'),Buffer.from(capture.data,'base64'));
    await send('Target.closeTarget',{targetId});
   }
  }
  await send('Browser.close');
 }finally{
  if(browser&&browser.exitCode===null&&closeBrowser){try{await closeBrowser();await new Promise(r=>setTimeout(r,500));}catch(_){}}
  if(browser&&browser.exitCode===null){browser.kill();await new Promise(r=>browser.once('exit',r));}
  if(path.dirname(dir)!==os.tmpdir()||!path.basename(dir).startsWith('codex-controls-ui-'))throw Error('Unsafe cleanup');
  fs.rmSync(dir,{recursive:true,force:true,maxRetries:10,retryDelay:200});
 }
})().catch(e=>{console.error(e);process.exitCode=1});
