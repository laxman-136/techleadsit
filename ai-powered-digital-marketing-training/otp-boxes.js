(()=>{
window.enhanceOtpInput=function(input){
 if(!input||input.dataset.boxes)return;input.dataset.boxes='1';
 const group=document.createElement('div');group.className='otp-boxes';group.setAttribute('role','group');group.setAttribute('aria-label','Four-digit SMS verification code');
 const boxes=Array.from({length:4},(_,i)=>{const box=document.createElement('input');box.type='text';box.inputMode='numeric';box.autocomplete=i===0?'one-time-code':'off';box.maxLength=i===0?4:1;box.pattern='[0-9]';box.required=true;box.setAttribute('aria-label',`OTP digit ${i+1} of 4`);group.append(box);return box;});
 input.hidden=true;input.required=false;input.after(group);
 function sync(){input.value=boxes.map(b=>b.value).join('');input.dispatchEvent(new Event('input',{bubbles:true}));}
 function distribute(text,start=0){const digits=text.replace(/\D/g,'').slice(0,4-start);if(!digits)return;[...digits].forEach((digit,i)=>boxes[start+i].value=digit);sync();boxes[Math.min(start+digits.length,3)].focus();}
 boxes.forEach((box,i)=>{box.addEventListener('input',()=>{if(box.value.length>1){distribute(box.value,i);return;}box.value=box.value.replace(/\D/g,'');sync();if(box.value&&i<3)boxes[i+1].focus();});box.addEventListener('paste',e=>{e.preventDefault();distribute(e.clipboardData.getData('text'),i);});box.addEventListener('keydown',e=>{if(e.key==='Backspace'&&!box.value&&i>0){e.preventDefault();boxes[i-1].value='';sync();boxes[i-1].focus();}if(e.key==='ArrowLeft'&&i>0)boxes[i-1].focus();if(e.key==='ArrowRight'&&i<3)boxes[i+1].focus();});box.addEventListener('focus',()=>box.select());});
 const clear=()=>{boxes.forEach(b=>b.value='');input.value='';};input.form?.addEventListener('reset',clear);input.closest('fieldset,.brochure-field,.chat-lead')?.querySelector('[data-resend],#resend-code')?.addEventListener('click',clear);
 [...input.value].slice(0,4).forEach((digit,i)=>boxes[i].value=digit);
 input.addEventListener('focus',()=>boxes[0].focus());return group;
};
window.enhanceOtpInput(document.querySelector('#otp-code'));
})();
