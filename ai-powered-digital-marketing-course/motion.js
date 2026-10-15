/* Local V2 motion orchestration. No animation library or external requests. */
(() => {
  'use strict';
  const reduced = matchMedia('(prefers-reduced-motion: reduce)');
  const root = document.documentElement;
  const sections = [...document.querySelectorAll('main > section')];
  const nodes = new Set();
  const add = (selector, direction = 'rise', stagger = false) => {
    document.querySelectorAll(selector).forEach((element, index) => {
      element.dataset.reveal = direction;
      if (stagger) element.style.setProperty('--reveal-delay', `${(index % 3) * 100}ms`);
      nodes.add(element);
    });
  };
  add('.section-heading, .reviews-top, .fit-block, .career-heading, .certificate-row, .comparison-outcomes > h3, .comparison-outcomes > p, .tools-foot, .roles-row, .faq-list, .final-cta, .section-bottom, .comparison-cta');
  add('.answer-grid article, .project-card, .outcome-grid article, .support-grid article, .tool-tile, .session-agenda > div, .review-card', 'rise', true);
  add('.mentor-visual, .price-card', 'left');
  add('.mentor-copy, .inclusions', 'right');
  add('.comparison-scroll, .session-window', 'frame');
  add('.faq-intro');
  add('.showcase-rail', 'rise');
  const show = element => {
    element.classList.remove('reveal-waiting');
    element.classList.add('reveal-shown');
  };
  const observer = new IntersectionObserver(entries => {
    entries.forEach(({isIntersecting, target}) => {
      if (isIntersecting) { show(target); observer.unobserve(target); }
    });
  }, {threshold: 0.06, rootMargin: '0px 0px -25px 0px'});
  nodes.forEach(element => {
    const box = element.getBoundingClientRect();
    if (!reduced.matches && box.top >= innerHeight && box.height) {
      element.classList.add('reveal-waiting');
      observer.observe(element);
    } else show(element);
  });
  document.querySelectorAll('.calendar-cells i, .rail-calendar i, .rail-chart i, .scene-calendar span').forEach((element,index) => {
    element.style.setProperty('--cell-delay', `${element.closest('.scene-calendar') ? index % 20 * .025 : index % 12 * .22}s`);
  });
  // Background paths are decorative; the curriculum route remains the real stage navigation.
  const ns='http://www.w3.org/2000/svg';
  const projectGrid = document.querySelector('.project-grid');
  const projectSvg = document.createElementNS(ns,'svg');
  projectSvg.classList.add('project-route'); projectSvg.setAttribute('aria-hidden','true');
  projectSvg.innerHTML='<path class="path-bed"/><path class="path-dots"/>';
  projectGrid.prepend(projectSvg);
  const drawProjectPath = () => {
    const box = projectGrid.getBoundingClientRect();
    const cards=[...projectGrid.querySelectorAll('.project-card')].map(el=>{
      const r=el.querySelector('.project-art').getBoundingClientRect();
      return {x:r.left-box.left+r.width/2,y:r.top-box.top+r.height/2};
    });
    projectSvg.setAttribute('viewBox',`0 0 ${box.width} ${box.height}`);
    const mobile=matchMedia('(max-width:760px)').matches;
    let d=`M ${cards[0].x} ${cards[0].y}`;
    cards.slice(1).forEach((point,i)=>{
      const previous=cards[i];
      if(mobile){const bend=box.width*.15;d+=` C ${bend} ${previous.y+150}, ${bend} ${point.y-150}, ${point.x} ${point.y}`;}
      else{const mid=(previous.x+point.x)/2;d+=` C ${mid} ${previous.y-50}, ${mid} ${point.y+50}, ${point.x} ${point.y}`;}
    });
    projectSvg.querySelectorAll('path').forEach(path=>path.setAttribute('d',d));
  };
  const faqSvg=document.createElementNS(ns,'svg');faqSvg.classList.add('faq-current');faqSvg.setAttribute('viewBox','0 0 1400 220');faqSvg.setAttribute('preserveAspectRatio','none');faqSvg.setAttribute('aria-hidden','true');faqSvg.innerHTML='<path d="M-20 120 C200 120 200 30 400 30 S730 210 950 150 S1210 30 1420 80"/>';document.querySelector('#faq').prepend(faqSvg);
  const inView=new IntersectionObserver(entries=>entries.forEach(({target,isIntersecting})=>{
    target.classList.toggle('section-motion-paused',!isIntersecting);
  }),{threshold:0,rootMargin:'80px'});
  sections.forEach(section=>{const r=section.getBoundingClientRect();section.classList.toggle('section-motion-paused',r.top>innerHeight+80||r.bottom < -80);inView.observe(section);});
  document.addEventListener('visibilitychange',()=>root.classList.toggle('page-motion-paused',document.hidden));
  document.addEventListener('focusin',event=>{
    let element=event.target;while(element&&element!==document.body){if(nodes.has(element))show(element);element=element.parentElement;}
  });
  reduced.addEventListener('change',()=>{if(reduced.matches){nodes.forEach(show);observer.disconnect();}});
  document.querySelectorAll('.gold').forEach(button=>button.closest('[data-reveal]')?.classList.add('motion-replay'));
  const resizeObserver=new ResizeObserver(drawProjectPath);resizeObserver.observe(projectGrid);
  document.fonts.ready.then(drawProjectPath);drawProjectPath();
  root.classList.add('motion-ready');
})();
