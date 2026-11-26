const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const script=fs.readFileSync(require('node:path').join(__dirname,'../lead-attribution.js'),'utf8');
const required='fbp fbc gclid gbraid wbraid fbclid ga_client_id session_id utm_source utm_medium utm_campaign utm_adgroup utm_term utm_content landing_page referrer'.split(' ');
function form(){return {nodeType:1,fields:[],matches:s=>s==='form',querySelectorAll:()=>[],querySelector(s){return this.fields.find(i=>i.name===s.match(/name="([^"]+)"/)[1]);},append(input){this.fields.push(input);}};}
function boot(url,saved=new Map(),blocked=false){
 const events={},forms=[form()];let mutated;
 const document={referrer:'https://ads.example.test/start?private=yes',cookie:'',body:{},addEventListener:(name,fn)=>events[name]=fn,createElement:()=>({}),querySelectorAll:()=>forms};
 const window={};const sessionStorage={getItem:key=>{if(blocked)throw Error();return saved.get(key);},setItem:(key,value)=>{if(blocked)throw Error();saved.set(key,value);}};
 vm.runInNewContext(script,{window,document,location:new URL(url),URL,URLSearchParams,sessionStorage,MutationObserver:class{constructor(fn){mutated=fn;}observe(){}}});
 events.DOMContentLoaded();
 return {api:window.TLITAttribution,document,forms,events,mutate:node=>mutated([{addedNodes:[node]}])};
}
const saved=new Map(),a=boot('https://lp.example.test/course?utm_source=facebook&utm_campaign=Campaign+A&utm_adgroup=Group+A&utm_content=Video+A&campaign_name=Explicit+Campaign&ad_name=Explicit+Ad&adset_name=Explicit+Set&ad_id=123&fbclid=click',saved);
const data=a.api.collect();for(const key of required)assert.ok(Object.hasOwn(data,key),key);
assert.equal(data.campaign_name,'Explicit Campaign');assert.equal(data.ad_name,'Explicit Ad');assert.equal(data.adset_name,'Explicit Set');assert.equal(data.ad_id,'123');
assert.equal(data.landing_page,'https://lp.example.test/course');assert.equal(data.referrer,'https://ads.example.test/start');
assert.equal(data.ga_client_id,'');assert.equal(data.session_id,'');
assert.equal(a.forms[0].fields.length,a.api.keys.length);assert.ok(a.forms[0].fields.every(i=>i.type==='hidden'));
a.api.decorate(a.forms[0]);assert.equal(a.forms[0].fields.length,a.api.keys.length);
a.document.cookie='_fbp=fb.1.100.200; _fbc=fb.1.100.click; _ga=GA1.1.12345.67890; _ga_TEST=GS2.1.s1764000000$o1$g0';
a.events.submit({target:a.forms[0]});assert.equal(a.forms[0].fields.find(i=>i.name==='ga_client_id').value,'12345.67890');assert.equal(a.api.collect().session_id,'1764000000');
const dynamic=form();a.mutate(dynamic);assert.equal(dynamic.fields.find(i=>i.name==='ad_name').value,'Explicit Ad');
const next=boot('https://lp.example.test/next',saved);assert.equal(next.api.collect().campaign_name,'Explicit Campaign');assert.equal(next.api.collect().landing_page,'https://lp.example.test/course');
const other=boot('https://lp.example.test/other?utm_source=google&utm_campaign=Campaign+B&gclid=google-click',saved);assert.equal(other.api.collect().utm_campaign,'Campaign B');assert.equal(other.api.collect().ad_name,'');assert.equal(other.api.collect().fbclid,'');
const aliases=boot('https://lp.example.test/course?gaclientid=10.20&ad_set_name=Alias+Set');assert.equal(aliases.api.collect().ga_client_id,'10.20');assert.equal(aliases.api.collect().adset_name,'Alias Set');
assert.doesNotThrow(()=>boot('https://lp.example.test/course',new Map(),true));
console.log('PASS: All hidden fields, original acquisition through navigation, new-campaign isolation, real cookies, delayed cookies, dynamic forms, aliases and blocked storage.');
