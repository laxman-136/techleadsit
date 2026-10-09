(()=>{
 const section=document.querySelector('#reviews');if(!section)return;
 const view=section.querySelector('.reviews-window'),list=section.querySelector('.reviews-list'),track=section.querySelector('.reviews-track'),button=section.querySelector('.reviews-toggle'),media=matchMedia('(prefers-reduced-motion: reduce)');
 let paused=media.matches,hover=false,focused=false,dragging=false,frame,last=0,visible=false;
 const copy=list.cloneNode(true);copy.setAttribute('aria-hidden','true');copy.inert=true;copy.querySelectorAll('[data-copy-id]').forEach(el=>el.removeAttribute('data-copy-id'));track.append(copy);
 function label(){button.textContent=paused?'Play →':'Pause Ⅱ';button.setAttribute('aria-label',paused?'Play review scrolling':'Pause review scrolling');button.setAttribute('aria-pressed',String(paused));}
 function tick(time){if(last&&visible&&!paused&&!hover&&!focused&&!dragging&&!media.matches){view.scrollLeft+=Math.min(time-last,50)*.032;const width=list.getBoundingClientRect().width;if(view.scrollLeft>=width)view.scrollLeft-=width;}last=time;frame=requestAnimationFrame(tick);}
 button.addEventListener('click',()=>{paused=!paused;label();});view.addEventListener('mouseenter',()=>hover=true);view.addEventListener('mouseleave',()=>hover=false);view.addEventListener('focusin',()=>focused=true);view.addEventListener('focusout',()=>focused=false);view.addEventListener('pointerdown',()=>{dragging=true;paused=true;label();});window.addEventListener('pointerup',()=>dragging=false);
 media.addEventListener('change',()=>{paused=media.matches;copy.hidden=media.matches;view.scrollLeft=0;label();});new IntersectionObserver(entries=>{visible=entries[0].isIntersecting;},{threshold:0}).observe(section);
 document.addEventListener('visibilitychange',()=>{if(document.hidden){cancelAnimationFrame(frame);last=0;}else frame=requestAnimationFrame(tick);});copy.hidden=media.matches;label();frame=requestAnimationFrame(tick);
})();
