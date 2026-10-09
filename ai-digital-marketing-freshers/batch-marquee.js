(()=>{
 const view=document.querySelector('.batch-loop-window');if(!view)return;
 const track=view.querySelector('.batch-loop-track'),group=track.querySelector('.batch-loop-group'),originals=[...group.children];
 const clone=node=>{const copy=node.cloneNode(true);copy.setAttribute('aria-hidden','true');copy.inert=true;copy.querySelectorAll('[id],[data-copy-id]').forEach(e=>{e.removeAttribute('id');e.removeAttribute('data-copy-id');});copy.removeAttribute('id');return copy;};
 let queued=false;
 function sync(){queued=false;track.querySelector('[data-batch-copy]')?.remove();group.querySelectorAll('[data-batch-fill]').forEach(e=>e.remove());
  const unit=group.getBoundingClientRect().width;if(!unit)return;
  const repeats=Math.max(1,Math.ceil(view.clientWidth/unit));
  for(let i=1;i<repeats;i++)originals.forEach(node=>{const copy=clone(node);copy.dataset.batchFill='';group.append(copy);});
  const copy=clone(group);copy.dataset.batchCopy='';track.append(copy);
  track.style.setProperty('--batch-duration',Math.max(12,group.getBoundingClientRect().width/32)+'s');
  view.classList.add('batch-loop-ready');
 }
 function schedule(){if(!queued){queued=true;requestAnimationFrame(sync);}}
 originals.forEach(node=>new MutationObserver(schedule).observe(node,{subtree:true,characterData:true,childList:true}));
 new ResizeObserver(schedule).observe(view);document.fonts?.ready.then(schedule);sync();
 view.addEventListener('keydown',e=>{if(e.code==='Space'){e.preventDefault();view.classList.toggle('is-paused');}});
})();
