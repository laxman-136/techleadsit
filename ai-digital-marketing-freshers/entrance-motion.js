(() => {
 'use strict';
 if (!window.IntersectionObserver || !Element.prototype.animate) return;
 const reduced = matchMedia('(prefers-reduced-motion: reduce)');
 const mobile = matchMedia('(max-width: 760px)');
 const seen = new WeakSet(), active = new Set(), cleanups = new Set();
 const disabled = () => reduced.matches || document.body.classList.contains('copy-editing') || document.body.classList.contains('feedback-mode');
 function play(el, frames, options={}) {
  if(disabled())return;
  const animation=el.animate(frames,{duration:1000,easing:'cubic-bezier(.16,1,.3,1)',fill:'backwards',...options});
  active.add(animation);animation.finished.catch(()=>{}).finally(()=>active.delete(animation));return animation;
 }
 function headline(el){
  // Keep the full heading in place and reveal it once, without cloned text or movement.
  play(el,[{opacity:0},{opacity:1}],{duration:1000,easing:'ease-out'});
 }

 function enter(el,kind,index){
  if(seen.has(el)||disabled())return;seen.add(el);
  const small=mobile.matches;
  if(kind==='headline'){headline(el);return;}
  if(kind==='enquiry')play(el,[{opacity:0,transform:`translateX(${small?24:90}px) rotate(${small?1:4}deg) scale(.96)`},{opacity:1,transform:'translateX(-9px) rotate(-1deg) scale(1.015)',offset:.72},{opacity:1,transform:'none'}],{duration:1000,delay:160});
  if(kind==='card'){
   play(el,[{opacity:0,transform:`perspective(900px) translateY(${small?35:85}px) rotateX(${small?3:12}deg) rotate(${index%2?-1:1}deg) scale(.94)`},{opacity:1,transform:'perspective(900px) translateY(-10px) rotateX(0deg) rotate(0deg) scale(1.02)',offset:.72},{opacity:1,transform:'none'}],{delay:small?0:index*110,duration:1000});
   const sheen=document.createElement('span');sheen.className='entrance-glass-sheen';el.append(sheen);const clean=()=>{sheen.remove();cleanups.delete(clean);};cleanups.add(clean);
   const a=play(sheen,[{transform:'translateX(-110%)',opacity:0},{opacity:.65,offset:.35},{transform:'translateX(110%)',opacity:0}],{duration:1000,delay:(small?0:index*110)+360});a?.finished.catch(()=>{}).finally(clean);setTimeout(clean,2500);
   el.querySelectorAll('.capstone-logos li').forEach((logo,j)=>pop(logo,(small?0:index*110)+300+j*75));
  }
  if(kind==='logos')el.querySelectorAll('ul:not([data-marquee-copy]) .tool-logo').forEach((logo,j)=>pop(logo,j*45));
  if(kind==='photo')play(el,[{clipPath:'polygon(0 0, 0 0, 0 100%, 0 100%)',transform:`translateX(${small?-12:-35}px)`},{clipPath:'polygon(0 0, 100% 0, 100% 100%, 0 100%)',transform:'none'}],{duration:1000,easing:'cubic-bezier(.7,0,.15,1)'});
  if(kind==='badge')play(el,[{opacity:0,transform:'scale(1.5) rotate(-15deg)'},{opacity:1,transform:'scale(.92) rotate(3deg)',offset:.68},{opacity:1,transform:'none'}],{duration:1000,delay:160});
  if(kind==='curriculum')el.querySelectorAll('details').forEach((row,j)=>play(row,[{opacity:0,transform:`translateX(${small?15:42}px)`},{opacity:1,transform:'translateX(-4px)',offset:.78},{opacity:1,transform:'none'}],{duration:1000,delay:j*65}));
 }
 function pop(el,delay){play(el,[{opacity:0,transform:'scale(.35) rotate(-10deg)'},{opacity:1,transform:'scale(1.16) rotate(3deg)',offset:.65},{opacity:1,transform:'none'}],{duration:1000,delay});}
 const targets=new Map();[['.hero h1','headline'],['.conversation-invite','enquiry'],['.capstone-glass-card','card'],['.tools-marquee-row','logos'],['.mentor-photo','photo'],['.trainer-credential-badge','badge'],['.curriculum-list','curriculum']].forEach(([selector,kind])=>document.querySelectorAll(selector).forEach((el,index)=>targets.set(el,{kind,index})));
 const observer=new IntersectionObserver(entries=>entries.forEach(({target,isIntersecting})=>{if(!isIntersecting)return;const {kind,index}=targets.get(target);enter(target,kind,index);if(seen.has(target))observer.unobserve(target);}),{threshold:.12});
 targets.forEach((_,el)=>observer.observe(el));
 function stop(){if(!disabled())return;active.forEach(a=>a.cancel());active.clear();cleanups.forEach(fn=>fn());}
 reduced.addEventListener('change',stop);new MutationObserver(stop).observe(document.body,{attributes:true,attributeFilter:['class']});
 document.addEventListener('focusin',event=>{active.forEach(a=>{if(a.effect?.target?.contains(event.target))a.cancel();});});
})();
