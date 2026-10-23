const fs=require('node:fs'),path=require('node:path'),os=require('node:os');
const {pathToFileURL}=require('node:url');
const {execFileSync}=require('node:child_process');
const assert=require('node:assert/strict');
const root=path.resolve(__dirname,'../..');
const dir=fs.mkdtempSync(path.join(os.tmpdir(),'codex-workspace-ui-'));
const asset=p=>pathToFileURL(path.join(root,'public',p)).href;
let html=fs.readFileSync(path.join(root,'writable/private/ui-pilot/inventory-workspace-preview.html'),'utf8');
// Isolated PHP-rendered fixture: no login, live data or mutation endpoints.
html=html.replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi,'');
html=html.replace(/https?:\/\/[^"']*\/assets\/([^"']+)/g,(_,p)=>asset('assets/'+p));
const scripts=['codex-controls.js','codex-ui.js','inventory-workspace.js'].map(n=>`<script defer src="${asset('assets/js/'+n)}"></script>`).join('')+`<script defer src="${asset('assets/vendor/alpinejs/csp-3.17.4.min.js')}"></script>`;
html=html.replace('</head>',`<script>window.testErrors=[];addEventListener('error',e=>testErrors.push(e.message));</script>${scripts}</head>`);
html=html.replace('</body>',`<pre id="test-result" style="white-space:pre-wrap"></pre><script>
addEventListener('DOMContentLoaded',()=>setTimeout(async()=>{
 const failures=[];let checks=0;
 const check=(ok,msg)=>{checks++;if(!ok)failures.push(msg)};
 const wait=()=>new Promise(r=>setTimeout(r,30));
 const visible=()=>[...document.querySelectorAll('.product-row')].filter(r=>r.style.display!=='none');
 const search=document.getElementById('productSearchInput');
 try {
  check(visible().length===5,'Initial pagination');
  search.value='SKU12';search.dispatchEvent(new Event('input',{bubbles:true}));await wait();
  check(visible().length===1&&visible()[0].dataset.sku==='SKU12','SKU search');
  document.getElementById('clearSearchBtn').click();await wait();check(visible().length===5,'Clear search');
  document.querySelector('[data-filter="critical"]').click();await wait();check(visible().length===2,'Critical filter');
  check(document.querySelector('[data-filter="critical"]').getAttribute('aria-pressed')==='true','Accessible selected filter');
  document.querySelector('[data-filter="healthy"]').click();await wait();check(visible().length===5,'Healthy pagination');
  search.value='ZZZ';search.dispatchEvent(new Event('input',{bubbles:true}));await wait();
  check(visible().length===0&&document.getElementById('no-results-row').style.display!=='none','Empty search');
  document.getElementById('clearSearchBtn').click();document.querySelector('[data-filter="all"]').click();await wait();
  const snapshot=document.documentElement.outerHTML;
  window.fetch=async()=>({ok:true,text:async()=>snapshot});
  document.getElementById('inventory-refresh').click();await wait();await wait();
  check(document.getElementById('inventory-refresh-status').textContent==='Datos actualizados','Refresh success');
  search.value='ZZZ';search.dispatchEvent(new Event('input',{bubbles:true}));await wait();
  check(document.getElementById('no-results-row').style.display!=='none','Empty search after refresh');
  document.getElementById('clearSearchBtn').click();await wait();
  const opener=document.querySelector('#inventory-products-card a[data-popup]');
  opener.href='about:blank';opener.click();await wait();
  check(document.getElementById('codex-popup').classList.contains('is-open'),'Popup opens');
  check(document.activeElement.id==='codex-popup-close','Popup receives focus');
  check(document.querySelector('main').inert,'Background is inert');
  document.dispatchEvent(new KeyboardEvent('keydown',{key:'Escape',bubbles:true}));await wait();
  check(!document.getElementById('codex-popup').classList.contains('is-open'),'Escape closes');
  check(document.activeElement===opener&&!document.querySelector('main').inert,'Focus restored');
  check(getComputedStyle(document.body).backgroundColor==='rgb(244, 247, 251)','Light theme');
  check(document.documentElement.scrollWidth<=innerWidth,'No page horizontal overflow');
 }catch(e){failures.push(e.stack)}
 document.getElementById('test-result').textContent=JSON.stringify({checks,failures:failures.concat(testErrors),width:innerWidth});
},500));
</script></body>`);
// DevTools pipe sets a true mobile viewport (Chrome CLI windows have a minimum width).
(async()=>{
 let browser;
 try {
  const file=path.join(dir,'index.html');fs.writeFileSync(file,html);
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
  for(const width of [1440,390]) {
   const {targetId}=await send('Target.createTarget',{url:'about:blank'});
   const {sessionId}=await send('Target.attachToTarget',{targetId,flatten:true});
   const call=(method,params)=>send(method,params,sessionId);
   await call('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:width<600});
   await call('Page.navigate',{url:pathToFileURL(file).href});
   let result;
   for(let attempt=0;attempt<60;attempt++) {
    await new Promise(r=>setTimeout(r,200));
    const evaluation=await call('Runtime.evaluate',{expression:'document.getElementById("test-result")?.textContent',returnByValue:true});
    if(evaluation.result.value){result=JSON.parse(evaluation.result.value);break;}
   }
   assert.ok(result,'Browser fixture completed');console.log(JSON.stringify(result));assert.deepEqual(result.failures,[]);assert.equal(result.width,width);
   for(const [label,expression] of [['overview','window.scrollTo(0,0)'],['products','document.getElementById("inventory-products-card").scrollIntoView()']]) {
    await call('Runtime.evaluate',{expression});
    const capture=await call('Page.captureScreenshot',{format:'png'});
    fs.writeFileSync(path.join(root,'writable/private/ui-pilot','inventory-pilot-'+width+'-'+label+'.png'),Buffer.from(capture.data,'base64'));
   }
   await send('Target.closeTarget',{targetId});
  }
  let form=fs.readFileSync(path.join(root,'writable/private/ui-pilot/inventory-product-preview.html'),'utf8');
  form=form.replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi,'').replace(/https?:\/\/[^"']*\/assets\/([^"']+)/g,(_,p)=>asset('assets/'+p));
  form=form.replace('</head>',`<script defer src="${asset('assets/js/codex-controls.js')}"></script><script defer src="${asset('assets/js/inventory-product-form.js')}"></script><script defer src="${asset('assets/vendor/alpinejs/csp-3.17.4.min.js')}"></script></head>`);
  const formFile=path.join(dir,'product.html');fs.writeFileSync(formFile,form);
  for(const width of [1280,390]) {
   const {targetId}=await send('Target.createTarget',{url:'about:blank'});
   const {sessionId}=await send('Target.attachToTarget',{targetId,flatten:true});
   const call=(method,params)=>send(method,params,sessionId);
   await call('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:width<600});
   await call('Page.navigate',{url:pathToFileURL(formFile).href});
   await new Promise(r=>setTimeout(r,1000));
   const result=await call('Runtime.evaluate',{expression:`JSON.stringify({overflow:document.documentElement.scrollWidth>innerWidth,theme:document.body.dataset.codexUi,unit:document.querySelector('[name="unit"]').value})`,returnByValue:true});
   const state=JSON.parse(result.result.value);console.log(JSON.stringify({form:width,...state}));assert.equal(state.overflow,false);assert.equal(state.theme,'light');assert.equal(state.unit,'pack');
   const capture=await call('Page.captureScreenshot',{format:'png'});
   fs.writeFileSync(path.join(root,'writable/private/ui-pilot','inventory-pilot-'+width+'-form.png'),Buffer.from(capture.data,'base64'));
   await send('Target.closeTarget',{targetId});
  }
  await send('Browser.close');
 }finally{
  if(browser&&browser.exitCode===null){browser.kill();await new Promise(r=>browser.once('exit',r));}
  if(path.dirname(dir)!==os.tmpdir()||!path.basename(dir).startsWith('codex-workspace-ui-'))throw Error('Unsafe cleanup');
  fs.rmSync(dir,{recursive:true,force:true,maxRetries:10,retryDelay:200});
 }
})().catch(error=>{console.error(error);process.exitCode=1});
