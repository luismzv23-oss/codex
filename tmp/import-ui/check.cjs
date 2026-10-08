const { chromium } = require('playwright');
const fs = require('fs');
(async()=>{
 const browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
 const page=await browser.newPage({viewport:{width:1440,height:1000}});const errors=[];
 page.on('pageerror',error=>errors.push(error.message));
 const html=fs.readFileSync('tmp/import-ui/wizard.html','utf8');
 await page.route('**/*',async route=>{
  const request=route.request(),u=new URL(request.url());
  if(u.pathname==='/qa-import')return route.fulfill({contentType:'text/html',body:html});
  if(u.pathname.startsWith('/assets/')){
   const file='public'+u.pathname;if(fs.existsSync(file)) return route.fulfill({body:fs.readFileSync(file),contentType:file.endsWith('.js')?'text/javascript':file.endsWith('.css')?'text/css':undefined});
  }
  if(u.hostname!=='localhost')return route.continue();
  if(u.pathname.includes('/archivo') || u.pathname.includes('/vista/'))return route.fulfill({contentType:'image/png',body:fs.readFileSync('tmp/import-ui/fixture.png')});
  if(u.pathname==='/compras/documentos' && request.method()==='GET')return route.fulfill({json:{documents:[]}});
  if(u.pathname==='/compras/documentos' && request.method()==='POST')return route.fulfill({json:{id:'doc',name:'fixture.png',status:'draft',revision:0,draft:{}}});
  if(u.pathname.endsWith('/analizar'))return route.fulfill({json:{id:'doc',name:'fixture.png',status:'draft',revision:1,draft:{supplier_id:'s',invoice_number:'A 00001-00000007',issue_date:'2026-10-05',items:[{product_id:'p',description:'Producto',quantity:2,unit_cost:100,tax_rate:21,pack:1,discount:0}],source_text:'Factura de prueba'}}});
  if(u.pathname.endsWith('/borrador')){const payload=request.postDataJSON();return route.fulfill({json:{id:'doc',name:'fixture.png',status:'draft',revision:payload.revision+1,draft:payload.draft}});}
  if(u.pathname.endsWith('/confirmar'))return route.fulfill({json:{id:'doc',status:'registered',invoice_id:'invoice'}});
  return route.fulfill({status:404,body:''});
 });
 await page.goto('http://localhost:8080/qa-import');
 await page.locator('#purchase-import').waitFor();
 await page.waitForFunction(()=>!document.getElementById('purchase-import').inert);
 if(await page.locator('#import-status.is-error').count()) throw Error(await page.locator('#import-status').innerText());
 await page.locator('#import-documents').getByText('No hay documentos para mostrar.').waitFor();
 await page.screenshot({path:'tmp/import-ui/upload.png',fullPage:true});
 await page.locator('#import-file').setInputFiles('tmp/import-ui/fixture.png');
 await page.locator('[data-step="1"]').waitFor({state:'visible'});
 await page.waitForFunction(()=>!document.getElementById('purchase-import').inert);
 await page.locator('#import-next').click();
 await page.locator('[data-step="2"]').waitFor({state:'visible'});
 await page.waitForFunction(()=>!document.getElementById('purchase-import').inert);
 await page.screenshot({path:'tmp/import-ui/products.png',fullPage:true});
 await page.locator('#import-next').click();
 await page.waitForFunction(()=>!document.getElementById('purchase-import').inert);
 await page.locator('[data-field=expected_subtotal]').fill('200');await page.locator('[data-field=expected_tax]').fill('42');await page.locator('[data-field=expected_total]').fill('242');
 await page.locator('#import-reviewed').check();
 if(await page.locator('#import-next').isDisabled()) throw Error('Confirmation should be enabled');
 await page.screenshot({path:'tmp/import-ui/review.png',fullPage:true});
 await page.setViewportSize({width:390,height:844});
 await page.screenshot({path:'tmp/import-ui/mobile.png',fullPage:true});
 const overflow=await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth);
 if(overflow)throw Error('Horizontal overflow on mobile');
 await page.locator('#import-next').click();await page.locator('#import-success').waitFor({state:'visible'});
 if(errors.length)throw Error(errors.join('\n'));
 console.log(JSON.stringify({flow:'upload-review-items-confirm OK',mobile:'no horizontal overflow',errors}));
 await browser.close();
})().catch(e=>{console.error(e);process.exit(1)});
