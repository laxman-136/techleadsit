(() => {
 const root=document.querySelector('.tools-marquee');
 const button=document.querySelector('#tools-motion-toggle');
 if(!root||!button)return;
 const media=matchMedia('(prefers-reduced-motion: reduce)');
 root.querySelectorAll('.tools-marquee-track').forEach(track=>{
  const original=track.querySelector('ul');
  function sync(){
   track.querySelector('[data-marquee-copy]')?.remove();
   const clone=original.cloneNode(true);clone.dataset.marqueeCopy='';clone.setAttribute('aria-hidden','true');clone.inert=true;
   clone.querySelectorAll('[data-copy-id]').forEach(el=>{const span=document.createElement('span');span.innerHTML=el.innerHTML;el.replaceWith(span);});
   clone.querySelectorAll('[id],[contenteditable]').forEach(el=>{el.removeAttribute('id');el.removeAttribute('contenteditable');});
   track.append(clone);
  }
  sync();new MutationObserver(sync).observe(original,{subtree:true,childList:true,characterData:true});
 });
 root.classList.add('is-ready');button.hidden=false;
 function setPaused(paused){root.classList.toggle('is-paused',paused);button.setAttribute('aria-pressed',String(paused));button.textContent=paused?'Resume scrolling':'Pause scrolling';}
 button.addEventListener('click',()=>setPaused(!root.classList.contains('is-paused')));
 function preference(){setPaused(media.matches);button.hidden=media.matches;}
 media.addEventListener('change',preference);preference();
 document.querySelector('#copy-edit')?.addEventListener('click',()=>setPaused(true));
})();
