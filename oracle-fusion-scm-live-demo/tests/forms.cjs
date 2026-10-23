const {chromium}=require('C:/Users/Veera/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const http=require('node:http'),fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const root=path.resolve(__dirname,'..');
const endpoint='https://n8n.techleadsit.com/webhook/scm-batch-80-registration';
let enabled=false,save=false,calls=[],wrongId=false,nonJson=false;
const server=http.createServer((req,res)=>{
  const file=path.join(root,new URL(req.url,'http://localhost').pathname.replace(/^\//,'')||'index.html');
  if(!file.startsWith(root+path.sep)){res.writeHead(403).end();return;}
  try{
    res.setHeader('Content-Type',file.endsWith('.js')?'application/javascript':file.endsWith('.css')?'text/css':file.endsWith('.html')?'text/html':'application/octet-stream');
    let content=fs.readFileSync(file);
    if(file.endsWith('app.js')&&enabled)content=content.toString().replace("{enabled: false, endpoint: '', sitekey: ''}",JSON.stringify({enabled:true,endpoint,sitekey:'mock-sitekey'}));
    res.end(content);
  }catch{res.writeHead(404).end();}
});
(async()=>{await new Promise(r=>server.listen(0,'127.0.0.1',r));const browser=await chromium.launch({channel:'chrome',headless:true});try{
  const page=await browser.newPage();const url='http://127.0.0.1:'+server.address().port;
  await page.route('https://fonts.**/**',r=>r.abort());
  await page.route('**/wp-json/**',()=>{throw Error('Must not use WordPress API');});
  await page.route(endpoint,r=>{const b=Object.fromEntries(new URLSearchParams(r.request().postData()));calls.push(b);assert.equal(r.request().headers()['content-type'],'application/x-www-form-urlencoded;charset=UTF-8');return nonJson?r.fulfill({body:'not JSON'}):r.fulfill({status:200,json:{success:save,code:'unavailable',registrationId:wrongId?'wrong-id':b.registrationId}});});
  await page.route('https://challenges.cloudflare.com/**',r=>r.fulfill({contentType:'application/javascript',body:'window.turnstile={render:(e,o)=>{window.mockCallbacks ||= []; const id=window.mockCallbacks.push(o)-1;setTimeout(o.callback,0);return id},getResponse:()=>"mock-token",reset:id=>setTimeout(window.mockCallbacks[id].callback,0)};window.scmTurnstileReady();'}));
  await page.goto(url);await page.waitForLoadState('networkidle');
  assert.equal(await page.locator('form [type=submit]:enabled').count(),2);assert.equal(await page.locator('.hero + .trust-strip').count(),1);assert.equal(calls.length,0);
  enabled=true;await page.reload();
  async function fill(selector){const f=page.locator(selector);await f.locator('[name=name]').fill('Security Test');await f.locator('[name=email]').fill('security-test@example.invalid');await f.locator('[name=countryCode]').selectOption('+1');await f.locator('[name=mobile]').fill('2025550123');await f.locator('[name=careerObjective]').nth(1).check();return f;}
  const inline=await fill('#inline-demo-form');await inline.locator('[type=submit]').click();await inline.locator('[role=status]').filter({hasText:'confirm your registration'}).waitFor();assert.equal(await inline.locator('.zoom-link').count(),0);
  save=true;wrongId=true;await inline.locator('[type=submit]:enabled').waitFor();await inline.locator('[type=submit]').click();await inline.locator('[type=submit]:enabled').waitFor();assert.equal(await inline.locator('.zoom-link').count(),0);
  wrongId=false;nonJson=true;await inline.locator('[type=submit]').click();await inline.locator('[type=submit]:enabled').waitFor();assert.equal(await inline.locator('.zoom-link').count(),0);
  nonJson=false;await inline.locator('[type=submit]').click();await inline.locator('.zoom-link').waitFor();assert.ok(calls.every(c=>c.registrationId===calls[0].registrationId));assert.equal(calls[3].source,'inline');assert.equal(calls[3].countryCode,'+1');
  await page.locator('[data-register]').first().click();const popup=await fill('#demo-form');await popup.locator('[type=submit]:enabled').waitFor();await popup.locator('[type=submit]').click();await popup.locator('.zoom-link').waitFor();assert.equal(calls[4].source,'popup');assert.equal(await popup.locator('.zoom-link').getAttribute('href'),'https://zoom.us/j/8125323232');
  await page.locator('#registration .close').click();await page.setViewportSize({width:390,height:844});assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);
  console.log('PASS: enabled forms; URL-encoded n8n POST only; inline/popup success; failure, wrong ID and unreadable response never show Zoom; durable retry ID; mobile layout. Webhook mocked; live Sheet tests run separately.');
}finally{await browser.close();server.close();}})().catch(e=>{console.error(e);process.exitCode=1;server.close();});
