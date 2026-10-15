(()=>{
 const section=document.querySelector('#reviews');if(!section)return;
 const view=section.querySelector('.reviews-window'),list=section.querySelector('.reviews-list'),track=section.querySelector('.reviews-track'),media=matchMedia('(prefers-reduced-motion: reduce)');
 let hover=false,focused=false,dragging=false,frame,last=0,visible=false;
 const copy=list.cloneNode(true);copy.setAttribute('aria-hidden','true');copy.inert=true;copy.querySelectorAll('[data-copy-id]').forEach(el=>el.removeAttribute('data-copy-id'));track.append(copy);
 function tick(time){if(last&&visible&&!hover&&!focused&&!dragging&&!media.matches){view.scrollLeft+=Math.min(time-last,50)*.032;const width=list.getBoundingClientRect().width;if(view.scrollLeft>=width)view.scrollLeft-=width;}last=time;frame=requestAnimationFrame(tick);}
 view.addEventListener('mouseenter',()=>hover=true);view.addEventListener('mouseleave',()=>hover=false);view.addEventListener('focusin',()=>focused=true);view.addEventListener('focusout',()=>focused=false);view.addEventListener('pointerdown',()=>dragging=true);window.addEventListener('pointerup',()=>dragging=false);window.addEventListener('pointercancel',()=>dragging=false);
 media.addEventListener('change',()=>{copy.hidden=media.matches;view.scrollLeft=0;});new IntersectionObserver(entries=>{visible=entries[0].isIntersecting;},{threshold:0}).observe(section);
 document.addEventListener('visibilitychange',()=>{if(document.hidden){cancelAnimationFrame(frame);last=0;}else frame=requestAnimationFrame(tick);});copy.hidden=media.matches;frame=requestAnimationFrame(tick);
})();
