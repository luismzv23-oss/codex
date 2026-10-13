const {chromium}=require('./import-ui/node_modules/playwright');
const fs=require('fs'); const assert=require('node:assert/strict');
(async()=>{
 const source=fs.readFileSync('app/Views/layouts/app.php','utf8');
 const start=source.lastIndexOf('<script>',source.indexOf('const buildPagination'))+8;
 const script=source.slice(start,source.indexOf('</script>',start));
 const browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
 try {
 const page=await browser.newPage();
 const table=(id,n)=>`<section id="${id}"><div class="table-responsive"><table data-codex-pagination="5" data-pagination-always="true"><tbody>${Array.from({length:n},(_,i)=>`<tr><td>${i+1}</td><td>QA</td></tr>`).join('')}</tbody><tfoot><tr><td>Total constante</td></tr></tfoot></table></div></section>`;
 await page.setContent(table('sales',12)+table('movements',6)+table('single',3));
 await page.addScriptTag({content:script});
 let navigations=0;page.on('framenavigated',()=>navigations++);
 assert.equal(await page.locator('#sales tbody tr:visible').count(),5);
 await page.locator('#sales [aria-label="Pagina siguiente"]').click();
 assert.equal(await page.locator('#sales tbody tr:visible').first().innerText(),'6\tQA');
 assert.equal(await page.locator('#movements tbody tr:visible').first().innerText(),'1\tQA');
 await page.locator('#sales [aria-label="Pagina siguiente"]').click();
 assert.equal(await page.locator('#sales tbody tr:visible').count(),2);
 await page.locator('#movements [aria-label="Pagina siguiente"]').click();
 assert.equal(await page.locator('#movements tbody tr:visible').count(),1);
 assert.equal(await page.locator('#single [aria-label="Pagina siguiente"]').isDisabled(),true);
 assert.equal(await page.locator('#sales tfoot').isVisible(),true);
 assert.equal(navigations,0);
 console.log('Pagination: 8 assertions passed; no navigation');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1)});
