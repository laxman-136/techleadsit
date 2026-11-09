// Explicit IST offset keeps the same batch start for visitors in every timezone.
function renderBatchCountdown(now = Date.now()) {
 const timer = document.querySelector('#batch-countdown');
 if (!timer) return false;
 const remaining = Math.max(0, Math.floor((Date.parse(timer.dataset.start) - now) / 1000));
 const values = {days:Math.floor(remaining/86400),hours:Math.floor(remaining/3600)%24,minutes:Math.floor(remaining/60)%60,seconds:remaining%60};
 for (const [unit,value] of Object.entries(values)) timer.querySelector(`[data-unit="${unit}"]`).textContent=String(value).padStart(2,'0');
 timer.querySelector('.countdown-digits').hidden = remaining === 0;
 document.querySelector('#batch-countdown-status').hidden = remaining > 0;
 document.querySelector('#batch-countdown-label').textContent = remaining > 0 ? 'YOUR NEXT BATCH STARTS IN' : 'SCHEDULED BATCH START';
 return remaining > 0;
}
if (renderBatchCountdown()) {
 const batchTick = setInterval(()=>{if(!renderBatchCountdown())clearInterval(batchTick)},1000);
}

const curriculum = [
 ['Functional Setup Manager','Build the access foundation: create users, work with predefined and custom roles, assign responsibilities and manage data access.'],
 ['Enterprise Structure & General Ledger','See how an organisation takes shape in Oracle: legal entities, business units and chart-of-accounts mapping. Work through budgets, secondary and reporting ledgers, revaluation, translation, consolidation and period close.'],
 ['Accounts Payable','Follow the supplier-to-payment process: supplier and bank setup, invoices, approvals, prepayments and payments. Explore supplier merges, FBDI imports and the payables close.'],
 ['Accounts Receivable','Follow the customer-to-receipt process: customer setup, transaction types, auto accounting, receipts, remittance and direct debit. Review customer accounts and the receivables close.'],
 ['Fixed Assets','Trace the asset lifecycle: books and categories, additions, ADFDI, capitalization and depreciation. Explore splitting, merging, retiring and reinstating assets.'],
 ['Cash Management','Connect bank activity with your records. Work through manual bank statements, manual and automatic reconciliation, and external cash transactions.'],
 ['Fusion Tax & Expenses','Understand the tax setup behind transactions: regimes, rates, jurisdictions, rules and party tax profiles. Follow expenses from creation through audit, approval and payment.'],
 ['Reporting & Practical Implementation','Turn financial data into reports with Financial Reporting Studio, Smart View and OTBI. Bring the modules together through data migration and a sample implementation project.']
];
document.querySelector('#modules').innerHTML = curriculum.map(([title,body],i)=>`<details${i===0?' open':''}><summary><span>${String(i+1).padStart(2,'0')}</span>${title}</summary><p>${body}</p></details>`).join('');

// Uses the existing same-origin Bridge API. No CRM or Teams secrets enter the browser.
const form=document.querySelector('#lead-form'), status=document.querySelector('#form-status'), submit=form.querySelector('[type=submit]');
// Keep the mobile CTA from competing with the visible registration form.
if ('IntersectionObserver' in window) {
 const formVisibility = new IntersectionObserver(([entry])=>document.body.classList.toggle('demo-form-visible',entry.isIntersecting),{threshold:0.12});
 formVisibility.observe(form);
}
const endpoint='/wp-json/tlit-leads/v1/';
const uuid=()=>crypto.randomUUID();
let requestId=uuid(),challenge='',draftToken='',draftQueue=Promise.resolve(),complete=false,busy=false,cooldownUntil=0;
const keys='utm_source utm_medium utm_campaign utm_adgroup utm_term utm_content utm_id gclid fbclid gbraid wbraid fb_ad fb_campaign fb_adset_name fb_adset_id fb_lead_id adset_id ad_id'.split(' ');
const cleanUrl=value=>{try{const u=new URL(value);return u.origin+u.pathname}catch{return ''}};
const query=new URLSearchParams(location.search);
let tracking={};
try{tracking=JSON.parse(sessionStorage.getItem('financials-attribution')||'{}')}catch{}
// A new campaign starts a new attribution snapshot; subsequent page visits keep it.
if(keys.some(k=>query.has(k))) tracking={};
keys.forEach(k=>{if(query.get(k))tracking[k]=query.get(k).slice(0,500)});
tracking.landing_page=tracking.landing_page||cleanUrl(location.href);
tracking.referrer=tracking.referrer||cleanUrl(document.referrer)||'direct';
const cookie=name=>document.cookie.split('; ').find(v=>v.startsWith(name+'='))?.split('=').slice(1).join('=')||'';
tracking.fbp=cookie('_fbp');tracking.fbc=cookie('_fbc');
tracking.ga_client_id=cookie('_ga').split('.').slice(-2).join('.');
tracking.session_id=tracking.session_id||uuid();
try{sessionStorage.setItem('financials-attribution',JSON.stringify(tracking))}catch{}
function values(){return Object.fromEntries(new FormData(form))}
function payload(){tracking.fbp=cookie('_fbp')||tracking.fbp;tracking.fbc=cookie('_fbc')||tracking.fbc;tracking.ga_client_id=cookie('_ga').split('.').slice(-2).join('.')||tracking.ga_client_id;const v=values();return {request_id:requestId,intent:'general',page_variant:'oracle_financials',submission_url:cleanUrl(location.href),tracking,name:v.name,phone:v.phone,email:v.email,goal:v.goal,status:v.goal,timeline:v.bant_timeline,question:v.bant_need,details:{student_status:v.goal,bant_need:v.bant_need,bant_timeline:v.bant_timeline,bant_budget:v.bant_budget,bant_authority:v.bant_authority},marketingConsent:false,consent_text:'I agree to be contacted by Tech Leads IT about this demo and course.',contact_consent:v.consent==='on'}}
function message(text,error=false){status.textContent=text;status.classList.toggle('error',error)}
async function api(route,data){const response=await fetch(endpoint+route,{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify(data),signal:AbortSignal.timeout(20000)});let result;try{result=await response.json()}catch{throw Error('Registration is temporarily unavailable. Please call +91 8125 32 32 32.')}if(!response.ok||result.success!==true)throw Error(result.message||'Your request could not be saved. Please try again.');return result}
function draft(state='incomplete',field='contact',error=''){
 if(complete||!form.elements.consent.checked)return Promise.resolve();
 const p=payload();if(!p.name||!p.phone)return Promise.resolve();
 const v={name:p.name,phone:p.phone,email:p.email,goal:p.goal};
 draftQueue=draftQueue.catch(()=>{}).then(async()=>{if(complete)return;const r=await api('draft',{...p,draft_token:draftToken,values:v,lifecycle_status:state,current_step:state==='incomplete'?1:2,total_steps:2,last_field:field,otp_error:error});draftToken=r.draft_token;}).catch(()=>{});
 return draftQueue;
}
form.addEventListener('change',event=>{if(['name','phone','email','goal','consent','bant_need','bant_timeline','bant_budget','bant_authority'].includes(event.target.name))draft('incomplete',event.target.name)});
const detailsStep=document.querySelector('#details-step'),otpPanel=document.querySelector('#otp-panel');
const stepItems=[...document.querySelectorAll('.registration-steps li')];
function showStep(step){
 const verifying=step===2;
 detailsStep.hidden=verifying;otpPanel.hidden=!verifying;
 form.elements.otp.required=verifying;
 stepItems.forEach((item,i)=>{item.classList.toggle('is-active',i===step-1);if(i===step-1)item.setAttribute('aria-current','step');else item.removeAttribute('aria-current')});
 submit.innerHTML=verifying?'Verify & Reserve My Seat':'Reserve My Free Seat <span>↗</span>';
 if(verifying){document.querySelector('.otp-destination').textContent='Enter the 4-digit SMS code sent to +91 '+form.elements.phone.value;form.elements.otp.focus()}
}
showStep(1);
document.querySelector('#edit-details').addEventListener('click',()=>{if(busy||complete)return;challenge='';form.elements.otp.value='';showStep(1);message('Update your details, then request a new verification code.');form.elements.phone.focus()});
form.elements.phone.addEventListener('input',()=>{if(challenge){challenge='';form.elements.otp.value='';showStep(1);message('Mobile number changed. Request a new verification code.')}});
async function sendCode(){const p=payload();await draft();const r=await api('otp',{name:p.name,phone:p.phone,intent:'general'});challenge=r.challenge;cooldownUntil=Date.now()+30000;showStep(2);message('We sent a four-digit code to your mobile. Enter it to confirm your request.');form.elements.otp.focus();await draft('otp_pending','otp')}
form.addEventListener('submit',async event=>{event.preventDefault();if(busy||complete)return;if(!challenge&&!form.reportValidity())return;if(challenge&&!form.elements.otp.reportValidity())return;busy=true;submit.disabled=true;message(challenge?'Verifying and saving your request…':'Sending your verification code…');try{if(!challenge){await sendCode()}else{await draftQueue;const result=await api('submit',{...payload(),challenge,otp:form.elements.otp.value});complete=true;message('Your demo request is saved. Our team will contact you with the schedule. Reference: '+result.receipt);submit.textContent='Request received ✓';[...form.elements].forEach(el=>el.disabled=true);document.querySelector('#otp-panel').hidden=true;window.dataLayer=window.dataLayer||[];window.dataLayer.push({event:'form_submitted',course:'oracle_fusion_financials',form_id:'financials_demo'});}}catch(error){message(error.message||'Unable to connect. Please try again.',true);if(challenge)await draft('otp_failed','otp',error.message)}finally{busy=false;if(!complete)submit.disabled=false}});
document.querySelector('#resend').addEventListener('click',async()=>{if(busy)return;if(Date.now()<cooldownUntil){message('Please wait 30 seconds before requesting another code.',true);return}busy=true;submit.disabled=true;try{await sendCode()}catch(error){message(error.message,true)}finally{busy=false;submit.disabled=false}});
document.querySelectorAll('a[href="#reserve"]').forEach(a=>a.addEventListener('click',()=>{setTimeout(()=>{if(!complete)(challenge?form.elements.otp:form.elements.name).focus({preventScroll:true})},400)}));

const logoMotion=document.querySelector('#logo-motion');
// Curriculum uses its own form state and the same verified Bridge endpoints.
(() => {
 const triggers=[...document.querySelectorAll('[data-curriculum-download]')];
 if(!triggers.length)return;
 const pdfUrl=new URL('assets/oracle-fusion-financials-curriculum.pdf',document.querySelector('script[src*="app.js"]').src).href;
 const dialog=document.createElement('dialog');dialog.className='curriculum-dialog';dialog.id='curriculum-download';
 dialog.setAttribute('aria-labelledby','curriculum-form-title');
 dialog.innerHTML='<button type="button" class="curriculum-close" aria-label="Close curriculum form">×</button><h2 id="curriculum-form-title">Get the full curriculum</h2><p>Enter your details and verify your mobile number. Your Oracle Fusion Financials curriculum will download after submission.</p><div class="registration"></div><a class="download-again" hidden>Download the curriculum again ↓</a>';
 const downloadForm=form.cloneNode(true);downloadForm.id='curriculum-form';
 downloadForm.querySelectorAll('[id]').forEach(el=>el.id='curriculum-'+el.id);
 downloadForm.querySelectorAll('[for]').forEach(el=>el.htmlFor='curriculum-'+el.htmlFor);
 const button=downloadForm.querySelector('[type=submit]');button.classList.remove('submit');button.classList.add('curriculum-submit');button.textContent='Get My Curriculum';
 const note=downloadForm.querySelector('.consent span');note.firstChild.textContent='I agree to be contacted by Tech Leads IT about this curriculum and course. ';
 downloadForm.querySelector('p.micro').textContent='Your PDF downloads after mobile verification and successful submission.';
 dialog.querySelector('.registration').append(downloadForm);document.body.append(dialog);
 const output=downloadForm.querySelector('[role=status]'),otpPanel=downloadForm.querySelector('#curriculum-otp-panel'),again=dialog.querySelector('.download-again');
 again.href=pdfUrl;again.download='Oracle-Fusion-Financials-Curriculum.pdf';
 let trigger,locked=false,finished=false,code='',cooldown=0,token='',queue=Promise.resolve(),receipt=null;
 const id=uuid();
 const say=(text,error=false)=>{output.textContent=text;output.classList.toggle('error',error)};
 const data=()=>{const v=Object.fromEntries(new FormData(downloadForm));return {request_id:id,intent:'brochure',page_variant:'oracle_financials',submission_url:cleanUrl(location.href),tracking,name:v.name,email:v.email,phone:v.phone,goal:v.goal,details:{student_status:v.goal},contact_consent:v.consent==='on',marketingConsent:false,consent_text:'I agree to be contacted by Tech Leads IT about this curriculum and course.'}};
 function saveDraft(state='incomplete'){
  if(finished||!downloadForm.elements.consent.checked)return Promise.resolve();
  const p=data();if(!p.name||!p.phone)return Promise.resolve();
  queue=queue.catch(()=>{}).then(async()=>{if(finished)return;const r=await api('draft',{...p,draft_token:token,values:{name:p.name,email:p.email,phone:p.phone,goal:p.goal},lifecycle_status:state,current_step:code?2:1,total_steps:2,last_field:code?'otp':'contact'});token=r.draft_token}).catch(()=>{});return queue;
 }
 function download(){again.hidden=false;again.click();if(receipt?.download_token)api('download',{receipt:receipt.receipt,download_token:receipt.download_token}).catch(()=>{});}
 async function send(){await saveDraft();const p=data();const r=await api('otp',{name:p.name,phone:p.phone,intent:'brochure'});code=r.challenge;cooldown=Date.now()+30000;otpPanel.hidden=false;downloadForm.elements.otp.required=true;button.textContent='Verify & Download Curriculum';say('Enter the four-digit SMS code to download your curriculum.');downloadForm.elements.otp.focus();await saveDraft('otp_pending');}
 triggers.forEach(el=>{el.setAttribute('aria-haspopup','dialog');el.addEventListener('click',event=>{event.preventDefault();trigger=el;dialog.showModal();document.body.classList.add('curriculum-modal-open');if(!finished)downloadForm.elements.name.focus();else again.focus()})});
 dialog.querySelector('.curriculum-close').addEventListener('click',()=>dialog.close());
 dialog.addEventListener('click',event=>{const r=dialog.getBoundingClientRect();if(event.target===dialog&&(event.clientX<r.left||event.clientX>r.right||event.clientY<r.top||event.clientY>r.bottom))dialog.close()});
 dialog.addEventListener('close',()=>{document.body.classList.remove('curriculum-modal-open');trigger?.focus()});
 downloadForm.addEventListener('change',()=>saveDraft());
 downloadForm.elements.phone.addEventListener('input',()=>{if(code){code='';otpPanel.hidden=true;downloadForm.elements.otp.required=false;downloadForm.elements.otp.value='';button.textContent='Get My Curriculum';say('Mobile number changed. Request a new verification code.')}});
 downloadForm.querySelector('#curriculum-resend').addEventListener('click',async()=>{if(locked||finished)return;if(Date.now()<cooldown){say('Please wait 30 seconds before requesting another code.',true);return}locked=true;button.disabled=true;try{await send()}catch(e){say(e.message,true)}finally{locked=false;button.disabled=false}});
 downloadForm.addEventListener('submit',async event=>{
  event.preventDefault();if(locked||finished||!downloadForm.reportValidity())return;
  locked=true;button.disabled=true;say(code?'Verifying and saving your request…':'Sending your verification code…');
  try{if(!code){await send()}else{await queue;receipt=await api('submit',{...data(),challenge:code,otp:downloadForm.elements.otp.value});finished=true;[...downloadForm.elements].forEach(el=>el.disabled=true);otpPanel.hidden=true;button.textContent='Curriculum ready ✓';say('Your request is saved. Your download is starting. If it does not start, use the download link below.');download();window.dataLayer=window.dataLayer||[];window.dataLayer.push({event:'form_submitted',course:'oracle_fusion_financials',form_id:'financials_curriculum'});}}
  catch(e){say(e.message||'Unable to connect. Please try again.',true);if(code)await saveDraft('otp_failed')}
  finally{locked=false;if(!finished)button.disabled=false}
 });
})();
logoMotion?.addEventListener('click',()=>{const paused=document.querySelector('.company-marquees').classList.toggle('is-paused');logoMotion.setAttribute('aria-pressed',String(paused));logoMotion.textContent=paused?'Resume logo movement':'Pause logo movement';});

// Full testimonials stay in the page source; the popup never changes card size.
const reviewGrid=document.querySelector('.review-grid');
if(reviewGrid && typeof HTMLDialogElement!=='undefined'){
 const reviewDialog=document.createElement('dialog');
 reviewDialog.className='review-dialog';
 reviewDialog.setAttribute('aria-labelledby','review-dialog-name');
 reviewDialog.innerHTML='<div class="review-dialog-header"><div><p class="eyebrow">STUDENT REVIEW</p><h2 id="review-dialog-name"></h2><p class="review-dialog-course">Oracle Fusion Financials learner</p></div><button type="button" class="review-dialog-close" aria-label="Close review" autofocus>×</button></div><blockquote class="review-dialog-text"></blockquote>';
 document.body.append(reviewDialog);
 let reviewTrigger=null;
 reviewGrid.querySelectorAll('figure').forEach(card=>{
  const button=document.createElement('button');
  button.type='button';button.className='review-more';button.textContent='Read more ↗';
  const name=card.querySelector('figcaption b').textContent;
  button.setAttribute('aria-label','Read full review by '+name);
  button.setAttribute('aria-haspopup','dialog');
  button.addEventListener('click',()=>{
   reviewTrigger=button;
   reviewDialog.querySelector('#review-dialog-name').textContent=name;
   reviewDialog.querySelector('.review-dialog-text').textContent=card.querySelector('blockquote').textContent;
   reviewDialog.showModal();document.body.classList.add('review-modal-open');
  });
  card.insertBefore(button,card.querySelector('figcaption'));
 });
 reviewGrid.classList.add('review-grid-compact');
 reviewDialog.querySelector('.review-dialog-close').addEventListener('click',()=>reviewDialog.close());
 reviewDialog.addEventListener('click',event=>{
  const box=reviewDialog.getBoundingClientRect();
  if(event.target===reviewDialog&&(event.clientX<box.left||event.clientX>box.right||event.clientY<box.top||event.clientY>box.bottom))reviewDialog.close();
 });
 reviewDialog.addEventListener('close',()=>{document.body.classList.remove('review-modal-open');reviewTrigger?.focus({preventScroll:true});});
}
