'use strict';

(() => {
  const section = document.querySelector('#reviews');
  const track = section?.querySelector('.review-track');
  if (!track || !track.children.length) return;

  const cards = [...track.children];
  // One visual copy makes the loop seamless; assistive technology reads each review once.
  cards.forEach(card => {
    const copy = card.cloneNode(true);
    copy.dataset.reviewCopy = '';
    copy.setAttribute('aria-hidden', 'true');
    copy.inert = true;
    track.append(copy);
  });

  const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)');
  const speed = 28; // Pixels per second, independent of display refresh rate.
  let inView = false;
  let hovering = track.matches(':hover');
  let touching = false;
  let interactionTimer = 0;
  let frame = 0;
  let lastTime = 0;
  let position = track.scrollLeft;
  let loopWidth = 0;

  function stop() {
    cancelAnimationFrame(frame);
    frame = 0;
    lastTime = 0;
    track.dataset.motion = 'paused';
  }

  function advance(time) {
    if (lastTime) position += speed * Math.min(time - lastTime, 64) / 1000;
    lastTime = time;
    if (loopWidth) position = ((position % loopWidth) + loopWidth) % loopWidth;
    track.scrollLeft = position;
    frame = requestAnimationFrame(advance);
  }

  function sync() {
    const paused = !inView || document.hidden || reducedMotion.matches || hovering ||
      touching || interactionTimer || section.contains(document.activeElement) || !loopWidth;
    if (paused) return stop();
    if (!frame) {
      position = track.scrollLeft;
      track.dataset.motion = 'playing';
      frame = requestAnimationFrame(advance);
    }
  }

  function measure() {
    loopWidth = track.children[cards.length].offsetLeft - cards[0].offsetLeft;
    if (!reducedMotion.matches && loopWidth > 0 && track.scrollLeft >= loopWidth) {
      track.scrollLeft %= loopWidth;
    }
    position = track.scrollLeft;
    sync();
  }

  function restAfterInteraction() {
    clearTimeout(interactionTimer);
    interactionTimer = setTimeout(() => {
      interactionTimer = 0;
      sync();
    }, 2500);
    sync();
  }

  track.addEventListener('pointerenter', event => {
    if (event.pointerType === 'touch') return;
    hovering = true;
    sync();
  });
  track.addEventListener('pointerleave', event => {
    if (event.pointerType === 'touch') return;
    hovering = false;
    sync();
  });
  section.addEventListener('focusin', sync);
  section.addEventListener('focusout', () => requestAnimationFrame(sync));
  track.addEventListener('pointerdown', event => {
    if (event.pointerType !== 'touch') return;
    touching = true;
    sync();
  }, {passive: true});
  const releaseTouch = () => {
    if (!touching) return;
    touching = false;
    restAfterInteraction();
  };
  window.addEventListener('pointerup', releaseTouch, {passive: true});
  window.addEventListener('pointercancel', releaseTouch, {passive: true});
  track.addEventListener('wheel', restAfterInteraction, {passive: true});
  document.addEventListener('visibilitychange', sync);
  reducedMotion.addEventListener('change', measure);

  const observer = new IntersectionObserver(entries => {
    inView = entries[0].isIntersecting;
    sync();
  });
  observer.observe(track);
  new ResizeObserver(measure).observe(track);

  for (const [selector, direction] of [['[data-review-prev]', -1], ['[data-review-next]', 1]]) {
    section.querySelector(selector)?.addEventListener('click', () => {
      restAfterInteraction();
      const step = cards[1].offsetLeft - cards[0].offsetLeft;
      if (!reducedMotion.matches && direction < 0 && track.scrollLeft < 1) {
        track.scrollLeft += loopWidth;
      }
      const current = track.scrollLeft;
      const nextIndex = direction > 0 ? Math.floor((current + .5) / step) + 1 : Math.ceil((current - .5) / step) - 1;
      track.scrollBy({left: nextIndex * step - current, behavior: reducedMotion.matches ? 'auto' : 'smooth'});
    });
  }
  measure();
})();
