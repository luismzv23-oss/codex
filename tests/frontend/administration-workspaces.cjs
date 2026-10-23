const fs=require('node:fs'),path=require('node:path'),os=require('node:os'),assert=require('node:assert/strict');
const {pathToFileURL}=require('node:url');
const root=path.resolve(__dirname,'../..');
const dir=fs.mkdtempSync(path.join(os.tmpdir(),'codex-admin-ui-'));
const artifact=path.join(root,'writable/private/ui-operational');
const asset=p=>pathToFileURL(path.join(root,'public',p)).href;
const modules={'admin-users':['users-directory.js'],'admin-companies':[],'admin-systems':['systems-workspace.js'],'admin-settings':['settings-pagination.js']};
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
     const snapshot=document.documentElement.outerHTML;let requests=0;
     window.fetch=async()=>{requests++;return {ok:true,redirected:false,text:async()=>snapshot}};
     if(document.getElementById('users-search')) {
      const rows=()=>[...document.querySelectorAll('[data-user-row]')].filter(r=>!r.hidden&&r.style.display!=='none');
      check(rows().length===10,'Users first page');
      document.querySelector('[data-page="next"]').click();await wait();check(rows().length===2,'Users next page');
      const search=document.getElementById('users-search');search.value='Persona 12';search.dispatchEvent(new Event('input',{bubbles:true}));await wait();check(rows().length===1,'User search');
      document.getElementById('users-filters').dispatchEvent(new Event('reset',{cancelable:true}));await wait();check(rows().length===10,'Clear user filters');
     }
     if(document.querySelector('.companies-directory')) {
      const rows=()=>[...document.querySelectorAll('.companies-directory tbody tr')].filter(r=>r.style.display!=='none');
      check(rows().length===10,'Companies page size');
      document.querySelector('.companies-directory [aria-label="Pagina siguiente"]').click();await wait();check(rows().length===2,'Companies next page');
      check(document.querySelectorAll('.companies-directory .codex-pagination').length===1,'Single paginator');
     }
     if(document.querySelector('.systems-workspace')) {
      check(typeof window.reloadSystemsData==='function','Systems controller active');
      await window.reloadSystemsData();await wait();check(requests===1,'Systems refresh once');
      check(document.querySelectorAll('#catalog-systems-table tbody tr').length===12,'System catalog preserved');
     }
     if(document.querySelector('.settings-workspace')) {
      const visible=()=>[...document.querySelectorAll('#settings-taxes>li')].filter(r=>!r.hidden);
      check(visible().length===4,'Settings page size');
      window.dispatchEvent(new CustomEvent('codex:settings-list-saved',{detail:{item:{company_id:'foreign',id:'t12',list:'taxes'}}}));await wait();check(requests===0,'Other company ignored');
      window.dispatchEvent(new CustomEvent('codex:settings-list-saved',{detail:{item:{company_id:'c1',id:'t12',list:'taxes'}}}));await wait();check(requests===1,'Settings refresh once');
      check(visible().some(row=>row.dataset.recordId==='t12'),'Saved record page visible');
     }
     const opener=document.querySelector('a[data-popup="true"]');
     if(opener){opener.href='about:blank';opener.click();await wait();check(document.getElementById('codex-popup').classList.contains('is-open'),'Popup opens');document.dispatchEvent(new KeyboardEvent('keydown',{key:'Escape',bubbles:true}));await wait();check(document.activeElement===opener,'Focus restored');}
     check(testErrors.length===0,'No JS errors: '+testErrors.join(';'));
     return {checks,failures,width:innerWidth};
    })()`});
    const result=evaluation.result.value;assert.ok(result,JSON.stringify(evaluation));console.log(JSON.stringify({module:name,...result}));assert.deepEqual(result.failures,[]);
    await call('Runtime.evaluate',{expression:'window.scrollTo(0,0)'});
    const capture=await call('Page.captureScreenshot',{format:'png'});fs.writeFileSync(path.join(artifact,name+'-'+width+'.png'),Buffer.from(capture.data,'base64'));
    await send('Target.closeTarget',{targetId});
   }
  }
  await send('Browser.close');
 }finally{
  if(browser&&browser.exitCode===null&&closeBrowser){try{await closeBrowser();await new Promise(r=>setTimeout(r,500));}catch(_){}}
  if(browser&&browser.exitCode===null){browser.kill();await new Promise(r=>browser.once('exit',r));}
  if(path.dirname(dir)!==os.tmpdir()||!path.basename(dir).startsWith('codex-admin-ui-'))throw Error('Unsafe cleanup');
  fs.rmSync(dir,{recursive:true,force:true,maxRetries:10,retryDelay:200});
 }
})().catch(e=>{console.error(e);process.exitCode=1});
