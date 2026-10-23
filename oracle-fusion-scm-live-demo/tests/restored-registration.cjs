const {chromium}=require('C:/Users/Veera/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const preview=process.argv.includes('--preview');
const submit=process.argv.includes('--submit');
(async()=>{
 const browser=await chromium.launch({channel:'chrome',headless:true});
 try {
  const page=await browser.newPage();
  if(preview) await page.route('**/oracle-fusion-scm-live-demo/app.js*',r=>r.fulfill({contentType:'application/javascript',body:fs.readFileSync(path.resolve(__dirname,'../app.js'),'utf8')}));
  await page.goto('https://lp.techleadsit.com/scm-batch-80',{waitUntil:'networkidle'});
  assert.equal(await page.locator('form [type=submit]:enabled').count(),2);
  assert.equal(await page.locator('.hero + .trust-strip').count(),1);
  const stamp=Date.now();
  for(const [selector,source,index] of [['#inline-demo-form','inline',1],['#demo-form','popup',2]]) {
   if(source==='popup') await page.locator('[data-register]').first().click();
   const form=page.locator(selector);
   await form.locator('[name=name]').fill('SCM RESTORE TEST '+source+' '+stamp);
   await form.locator('[name=email]').fill('scm-restore-'+source+'-'+stamp+'@example.invalid');
   await form.locator('[name=countryCode]').selectOption('+1');
   await form.locator('[name=mobile]').fill('202555010'+index);
   await form.locator('[name=careerObjective]').nth(1).check();
   if(submit) {
    const pending=page.waitForResponse(r=>r.url().includes('/webhook/scm-batch-80-registration')&&r.request().method()==='POST');
    await form.locator('[type=submit]').click();
    const response=await pending;
    const body=await response.text();
    assert.equal(response.status(),200,'Webhook failed: '+body.slice(0,200));
    const result=JSON.parse(body),sent=Object.fromEntries(new URLSearchParams(response.request().postData()));
    assert.equal(result.success,true,body);assert.equal(result.registrationId,sent.registrationId);
    assert.equal(sent.source,source);
    await form.locator('.zoom-link').waitFor({timeout:15000});
    assert.equal(await form.locator('.zoom-link').getAttribute('href'),'https://zoom.us/j/8125323232');
    console.log(JSON.stringify({form:source,confirmed:true,registrationId:sent.registrationId,testEmail:sent.email}));
   }
  }
  await page.locator('#registration .close').click();
  await page.setViewportSize({width:390,height:844});
  assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);
  await page.screenshot({path:path.resolve(__dirname,'../restored-registration-proof.png')});
  console.log('PASS: both forms enabled; '+(submit?'both real Sheet saves confirmed and Zoom links shown; ':'no submissions made; ')+'ratings placement and mobile layout checked.');
 } finally {await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
