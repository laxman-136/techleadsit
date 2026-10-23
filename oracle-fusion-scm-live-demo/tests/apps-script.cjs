const assert = require('node:assert/strict');
const crypto = require('node:crypto');
const vm = require('node:vm');
const fs = require('node:fs');
const secret = 'a'.repeat(64);
let rows = [], permissions = [{type:'user'}], privateReads = 0;
const ctx = {
  ContentService:{MimeType:{JSON:'json'},createTextOutput:s=>({setMimeType:()=>JSON.parse(s)})},
  PropertiesService:{getScriptProperties:()=>({getProperty:()=>secret})},
  Utilities:{Charset:{UTF_8:'utf8'},DigestAlgorithm:{SHA_256:'sha256'},computeDigest:(a,v)=>[...crypto.createHash('sha256').update(v).digest()],computeHmacSha256Signature:(v,k)=>[...crypto.createHmac('sha256',k).update(v).digest()]},
  ScriptApp:{getOAuthToken:()=> 'test-token'},
  UrlFetchApp:{fetch:(url,options)=>{
    if(url.includes('/drive/')) { privateReads++; return {getResponseCode:()=>200,getContentText:()=>JSON.stringify({permissions})}; }
    const request = JSON.parse(options.payload).requests[0];
    assert.ok(request.appendCells); assert.equal(request.appendCells.sheetId,0);
    rows.push(request.appendCells.rows[0].values);
    return {getResponseCode:()=>200,getContentText:()=> '{}'};
  }},
  LockService:{getScriptLock:()=>({tryLock:()=>true,releaseLock:()=>{}})},
  SpreadsheetApp:{openById:id=>{assert.equal(id,'1wSL-AyX-nsetyp3SzLnJ8JxQnF7k7qyUN1njU1ENR-o');return {getSheetById:()=>({getLastRow:()=>rows.length+1,getRange:(r)=>r===1?{getValues:()=>[['Name','Email','Mobile','Career objective']]}:{getNotes:()=>rows.map(r=>[r[0].note])}})}}}
};
vm.createContext(ctx);vm.runInContext(fs.readFileSync(require('node:path').join(__dirname,'../apps-script/Code.gs'),'utf8'),ctx);
const base={name:'=IMPORTXML("test")',email:'security-test@example.invalid',mobile:'+12025550123',careerObjective:'I want to increase my salary.',registrationId:crypto.randomUUID()};
function post(data,patch={}) {const payload=JSON.stringify(data); const timestamp=String(Math.floor(Date.now()/1000));const signature=crypto.createHmac('sha256',secret).update(timestamp+'.'+payload).digest('hex');return ctx.doPost({postData:{contents:JSON.stringify({payload,timestamp,signature,...patch})}});}
assert.equal(post(base,{signature:'bad'}).success,false);assert.equal(privateReads,0);
assert.equal(post(base,{timestamp:'1000000000'}).success,false);
assert.equal(post(base).success,true);assert.equal(rows.length,1);assert.equal(rows[0][0].userEnteredValue.stringValue,base.name);assert.equal(rows[0][2].userEnteredValue.stringValue,'+12025550123');
assert.equal(post(base).success,true);assert.equal(rows.length,1);
assert.equal(post({...base,name:'Different'}).success,false);assert.equal(rows.length,1);
assert.equal(post({...base,name:'Another registration',registrationId:crypto.randomUUID()}).success,true);assert.equal(rows.length,2);assert.equal(rows[0][0].userEnteredValue.stringValue,base.name);
for(const p of [{type:'anyone'},{type:'domain'},{type:'user',view:'published'}]){permissions=[p];assert.equal(post({...base,registrationId:crypto.randomUUID()}).success,false);assert.equal(rows.length,2);}
permissions=[{type:'user'}];assert.equal(post({...base,mobile:'bad',registrationId:crypto.randomUUID()}).success,false);
console.log('PASS: signatures, expiry, private-only storage, append-only same-email records, durable retries, conflict rejection, literal cell values and validation.');
