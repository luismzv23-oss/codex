/* Isolated browser test, using the actual PHP-rendered form and Alpine CSP. */
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const {pathToFileURL} = require('node:url');
const {execFileSync} = require('node:child_process');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '../..');
const source = fs.readFileSync(path.join(root, 'writable/inventory-product-preview.html'), 'utf8');
const form = source.match(/<form\b[\s\S]*?<\/form>/)[0];
const fixture = fs.mkdtempSync(path.join(os.tmpdir(), 'codex-inventory-form-'));
const scriptURL = name => pathToFileURL(path.join(root, 'public', name)).href;
const html = `<!doctype html><meta charset="utf-8">
<meta http-equiv="Content-Security-Policy" content="default-src 'none'; script-src 'unsafe-inline' file:; style-src 'unsafe-inline'">
<script>window.testErrors=[]; addEventListener('error',e=>testErrors.push(e.message));</script>
<script defer src="${scriptURL('assets/js/inventory-product-form.js')}"></script>
<script defer src="${scriptURL('assets/vendor/alpinejs/csp-3.17.4.min.js')}"></script>
${form}<pre id="result"></pre>
<script>
addEventListener('DOMContentLoaded',()=>setTimeout(async()=>{
  const errors=[]; let checks=0;
  const check=(condition,message)=>{checks++;if(!condition)errors.push(message);};
  const wait=()=>new Promise(resolve=>setTimeout(resolve,0));
  try {
    const f=document.querySelector('form'); const field=name=>f.elements.namedItem(name);
    const type=field('product_type');
    check(field('unit').value==='pack','Kit debe usar pack');
    for(const name of ['min_stock','max_stock','initial_stock'])check(field(name).value==='0'&&field(name).readOnly,'Kit bloquea '+name);
    check(field('initial_warehouse_id').disabled,'Kit deshabilita depósito inicial');
    check(!field('cost_price').readOnly&&!field('sale_price').readOnly,'Precios editables');
    check(field('cost_price').value==='25.50'&&field('sale_price').value==='40.75','Precios conservados');
    type.value='simple';type.dispatchEvent(new Event('change',{bubbles:true}));await wait();
    check(field('unit').value==='unidad','Simple debe usar unidad');
    check(field('unit').options[1].disabled,'Simple no permite pack');
    for(const name of ['min_stock','max_stock','initial_stock']){
      check(!field(name).readOnly&&field(name).checkValidity(),'Simple admite cero: '+name);
      field(name).value='12';
    }
    field('min_stock').value='-1';check(!field('min_stock').checkValidity(),'Simple rechaza negativos');
    check(!field('initial_warehouse_id').disabled,'Simple permite depósito');
    type.value='kit';type.dispatchEvent(new Event('change',{bubbles:true}));await wait();
    for(const name of ['min_stock','max_stock','initial_stock'])check(field(name).value==='0'&&field(name).readOnly,'Cambio a kit restablece '+name);
    check(field('unit').value==='pack'&&field('unit').options[0].disabled,'Kit restringe unidad');
    const payload=new FormData(f);
    check(payload.get('initial_stock')==='0'&&!payload.has('initial_warehouse_id'),'Kit envía cero sin depósito');
    check(payload.get('sale_price')==='40.75','Cambio de tipo conserva precio');
  } catch(error) { errors.push(error.stack); }
  document.getElementById('result').textContent=JSON.stringify({checks,errors:errors.concat(window.testErrors)});
},100));
</script>`;
try {
  const file = path.join(fixture, 'form.html'); fs.writeFileSync(file, html);
  const output = execFileSync(process.env.INVENTORY_BROWSER_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe', [
    '--headless=new', '--disable-gpu', '--disable-background-networking', '--no-first-run',
    '--user-data-dir='+path.join(fixture,'profile'), '--dump-dom', '--virtual-time-budget=4000', pathToFileURL(file).href
  ], {windowsHide:true, timeout:30000, maxBuffer:2*1024*1024, stdio:['ignore','pipe','pipe']}).toString();
  const result=JSON.parse(output.match(/<pre id="result">(.*?)<\/pre>/s)[1].replace(/&quot;/g,'"').replace(/&amp;/g,'&'));
  console.log(JSON.stringify(result)); assert.deepEqual(result.errors,[]); assert.ok(result.checks>=20);
} finally {
  if(path.dirname(fixture)!==os.tmpdir()||!path.basename(fixture).startsWith('codex-inventory-form-'))throw Error('Ruta de limpieza inválida');
  fs.rmSync(fixture,{recursive:true,force:true,maxRetries:10,retryDelay:200});
}
