const assert = require('node:assert/strict');
const crypto = require('node:crypto');
const vm = require('node:vm');
const fs = require('node:fs');
let rows = [], permissions = [{type:'user'}], privateReads = 0;
let properties={TURNSTILE_SECRET_KEY:'private-real-key',RATE_SALT:'private-rate-salt',REGISTRATION_ENABLED:'true'};
let captcha={success:true,hostname:'lp.techleadsit.com',action:'scm_registration'}, writeFail=false;
const usedTokens=new Set();
const props={getProperty:k=>properties[k]??null,setProperty:(k,v)=>properties[k]=v,getProperties:()=>({...properties}),deleteProperty:k=>delete properties[k],setProperties:v=>Object.assign(properties,v)};
const ctx = {
  console:{log:()=>{}},
  ContentService:{MimeType:{JSON:'json'},createTextOutput:s=>({setMimeType:()=>JSON.parse(s)})},
  PropertiesService:{getScriptProperties:()=>props},
  Utilities:{Charset:{UTF_8:'utf8'},DigestAlgorithm:{SHA_256:'sha256'},getUuid:()=>crypto.randomUUID(),computeDigest:(a,v)=>[...crypto.createHash('sha256').update(v).digest()],computeHmacSha256Signature:(v,k)=>[...crypto.createHmac('sha256',k).update(v).digest()]},
  Drive:{Files:{get:()=>{privateReads++;return {permissions};}}},
  DriveApp:{Access:{PRIVATE:'private'},Permission:{NONE:'none'},getFileById:()=>({setSharing:()=>{permissions=[{type:'user'}];}})},
  UrlFetchApp:{fetch:(url,options)=>{
    assert.equal(url,'https://challenges.cloudflare.com/turnstile/v0/siteverify');assert.equal(options.payload.secret,properties.TURNSTILE_SECRET_KEY);
    const duplicate=usedTokens.has(options.payload.response);usedTokens.add(options.payload.response);
    return {getResponseCode:()=>200,getContentText:()=>JSON.stringify(duplicate?{success:false}:captcha)};
  }},
  Sheets:{Spreadsheets:{batchUpdate:(body,id)=>{assert.equal(id,'1wSL-AyX-nsetyp3SzLnJ8JxQnF7k7qyUN1njU1ENR-o');if(writeFail)throw Error('unavailable');const request=body.requests[0];assert.ok(request.appendCells);assert.equal(request.appendCells.sheetId,0);rows.push(request.appendCells.rows[0].values);return {};}}},
  LockService:{getScriptLock:()=>({tryLock:()=>true,releaseLock:()=>{}})},
  SpreadsheetApp:{openById:()=>({getSheetById:()=>({getLastRow:()=>rows.length+1,getRange:r=>r===1?{getValues:()=>[['Name','Email','Mobile','Career objective']]}:{getNotes:()=>rows.map(r=>[r[0].note])}})})}
};
vm.createContext(ctx);vm.runInContext(fs.readFileSync(require('node:path').join(__dirname,'../apps-script/Code.gs'),'utf8'),ctx);
const base={name:'=IMPORTXML("test")',email:'security-test@example.invalid',countryCode:'+1',mobile:'2025550123',careerObjective:'I want to increase my salary.',source:'inline',registrationId:crypto.randomUUID(),website:''};
function post(data,patch={}){const body={captchaToken:crypto.randomUUID(),...data};return ctx.doPost({postData:{contents:new URLSearchParams(body).toString()},parameters:Object.fromEntries(Object.entries(body).map(([k,v])=>[k,[v]])),...patch});}
assert.equal(ctx.doGet().success,false);
properties.REGISTRATION_ENABLED='false';assert.equal(post(base).code,'paused');assert.equal(privateReads,0);
ctx.setupPrivateSheet();assert.equal(properties.REGISTRATION_ENABLED,'true');
assert.equal(post({...base,captchaToken:''}).code,'invalid_fields');
for(const [k,v] of Object.entries({success:false,hostname:'attacker.invalid',action:'other'})){const old=captcha[k];captcha[k]=v;assert.equal(post(base).code,'security_check');captcha[k]=old;}
assert.equal(rows.length,0);
const token=crypto.randomUUID();assert.equal(post({...base,captchaToken:token}).success,true);assert.equal(rows.length,1);
assert.equal(post({...base,captchaToken:token}).code,'security_check');
assert.equal(rows[0][0].userEnteredValue.stringValue,base.name);assert.equal(rows[0][2].userEnteredValue.stringValue,'+12025550123');
assert.equal(post(base).success,true);assert.equal(rows.length,1);
assert.equal(post({...base,name:'Different'}).code,'request_conflict');assert.equal(rows.length,1);
assert.equal(post({...base,name:'Another registration',registrationId:crypto.randomUUID()}).success,true);assert.equal(rows.length,2);assert.equal(rows[0][0].userEnteredValue.stringValue,base.name);
for(const p of [{type:'anyone'},{type:'domain'},{type:'user',view:'published'}]){permissions=[p];assert.equal(post({...base,registrationId:crypto.randomUUID()}).success,false);assert.equal(rows.length,2);}
permissions=[{type:'user'}];assert.equal(post({...base,mobile:'bad'}).code,'invalid_fields');assert.equal(post({...base,website:'spam'}).code,'invalid_fields');
assert.equal(post(base,{parameters:{name:['One','Two']}}).code,'invalid_fields');
writeFail=true;assert.equal(post({...base,email:'failure@example.invalid',registrationId:crypto.randomUUID()}).success,false);writeFail=false;
assert.equal(post({...base,registrationId:crypto.randomUUID()}).success,true);assert.equal(post({...base,registrationId:crypto.randomUUID()}).code,'rate_limited');
assert.equal(JSON.stringify(properties).includes(base.email),false);
for(const k of Object.keys(properties))if(k.startsWith('scm_rate_'))delete properties[k];
for(let i=0;i<30;i++)assert.equal(post({...base,email:'test'+i+'@example.invalid',registrationId:crypto.randomUUID()}).success,true);
assert.equal(post({...base,email:'over-limit@example.invalid',registrationId:crypto.randomUUID()}).code,'rate_limited');
ctx.pauseRegistration();assert.equal(post(base).code,'paused');
console.log('PASS: direct POST validation, CAPTCHA host/action/replay, private-only storage, no overwrite by email, durable retries, literal cell values, failure handling, rate limits and pause.');
