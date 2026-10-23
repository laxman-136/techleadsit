const {chromium}=require('C:/Users/Veera/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const http=require('node:http'),fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const root=path.resolve(__dirname,'..');
const server=http.createServer((req,res)=>{
  const file=path.join(root,new URL(req.url,'http://localhost').pathname.replace(/^\//,'')||'index.html');
  if(!file.startsWith(root+path.sep)){res.writeHead(403).end();return;}
  try{res.setHeader('Content-Type',file.endsWith('.js')?'application/javascript':file.endsWith('.css')?'text/css':file.endsWith('.html')?'text/html':'application/octet-stream');res.end(fs.readFileSync(file));}catch{res.writeHead(404).end();}
});
(async()=>{await new Promise(r=>server.listen(0,'127.0.0.1',r));const browser=await chromium.launch({channel:'chrome',headless:true});try{
  const page=await browser.newPage();const url='http://127.0.0.1:'+server.address().port;
  let enabled=false,save=false,calls=[];
  await page.route('**/wp-json/techleadsit-scm/v1/config',r=>r.fulfill({json:{enabled,sitekey:enabled?'mock-test-widget':''}}));
  await page.route('**/wp-json/techleadsit-scm/v1/register',r=>{calls.push(Object.fromEntries(new URLSearchParams(r.request().postData())));return r.fulfill({status:save?200:502,json:{success:save,message:'Storage unavailable'}});});
  await page.route('https://challenges.cloudflare.com/**',r=>r.fulfill({contentType:'application/javascript',body:'window.turnstile={render:(e,o)=>{window.mockCallbacks ||= []; const id=window.mockCallbacks.push(o)-1;setTimeout(o.callback,0);return id},getResponse:()=>"mock-token",reset:id=>setTimeout(window.mockCallbacks[id].callback,0)};window.scmTurnstileReady();'}));
  await page.goto(url);await page.waitForLoadState('networkidle');
  assert.equal(await page.locator('form [type=submit]:disabled').count(),2);
  assert.equal(await page.locator('.hero + .trust-strip').count(),1);
  await page.locator('[data-register]').first().click();assert.equal(await page.locator('#registration').isVisible(),true);
  await page.locator('#registration .close').click();assert.equal(calls.length,0);
  enabled=true;await page.reload();
  async function fill(selector){const f=page.locator(selector);await f.locator('[name=name]').fill('Security Test');await f.locator('[name=email]').fill('security-test@example.invalid');await f.locator('[name=countryCode]').selectOption('+1');await f.locator('[name=mobile]').fill('2025550123');await f.locator('[name=careerObjective]').nth(1).check();return f;}
  const inline=await fill('#inline-demo-form');await inline.locator('[type=submit]').click();await inline.locator('[role=status]').filter({hasText:'Storage unavailable'}).waitFor();assert.equal(await inline.locator('.zoom-link').count(),0);
  save=true;await inline.locator('[type=submit]:enabled').waitFor();await inline.locator('[type=submit]').click();await inline.locator('.zoom-link').waitFor();assert.equal(calls[0].registrationId,calls[1].registrationId);assert.equal(calls[1].source,'inline');assert.equal(calls[1].countryCode,'+1');
  await page.locator('[data-register]').first().click();const popup=await fill('#demo-form');await popup.locator('[type=submit]:enabled').waitFor();await popup.locator('[type=submit]').click();await popup.locator('.zoom-link').waitFor();assert.equal(calls[2].source,'popup');assert.equal(calls[2].captchaToken,'mock-token');assert.equal(await popup.locator('.zoom-link').getAttribute('href'),'https://zoom.us/j/8125323232');
  await page.locator('#registration .close').click();await page.setViewportSize({width:390,height:844});assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);
  console.log('PASS: both paused forms, ratings below hero, inline failure/retry, popup success, same request ID on retry, international phone, Zoom gating and mobile width. CAPTCHA/network mocked; live Google tests remain required.');
}finally{await browser.close();server.close();}})().catch(e=>{console.error(e);process.exitCode=1;server.close();});
