
(function(){
  function initRC(root){
    const track = root.querySelector('.rc__track');
    const prev  = root.querySelector('[data-rc-prev]');
    const next  = root.querySelector('[data-rc-next]');

    if (!track || !prev || !next) return;

    const step = () => Math.max(track.clientWidth * 0.9, 280);

    function update(){
      prev.disabled = track.scrollLeft <= 4;
      const end = track.scrollWidth - track.clientWidth - 4;
      next.disabled = track.scrollLeft >= end;
    }

    prev.addEventListener('click', () => track.scrollBy({ left: -step(), behavior: 'smooth' }));
    next.addEventListener('click', () => track.scrollBy({ left:  step(), behavior: 'smooth' }));
    track.addEventListener('scroll', update, { passive: true });
    window.addEventListener('resize', update);
    update();
  }

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-rc]').forEach(initRC);
  });
})();



/**
 * fucniones de archive
 */

(function () {
    const controls = document.querySelector('.ba-controls');
    if (!controls) return;
    controls.addEventListener('change', (e) => {
      const t = e.target;
      if (t.matches('select')) {
        controls.submit();
      }
    });
  })();

  (function () {
  const HEADER_OFFSET =
    (document.querySelector('header') && document.querySelector('header').offsetHeight) || 0;

  function scrollToHash(hash) {
    if (!hash) return;
    const id = decodeURIComponent(hash.replace(/^#/, ''));
    let el = document.getElementById(id);

    // Fallback: si Gutenberg creó duplicados (#id-2, #id-3)
    if (!el) {
      try {
        el = document.querySelector('[id^="' + CSS.escape(id) + '"]');
      } catch (e) {
        el = document.querySelector('[id^="' + id.replace(/"/g, '\\"') + '"]');
      }
    }
    if (!el) return;

    const y = el.getBoundingClientRect().top + window.pageYOffset - HEADER_OFFSET - 8;
    window.scrollTo({ top: y, behavior: 'smooth' });
  }

  document.addEventListener('click', function (e) {
    const a = e.target.closest('a[href*="#"]');
    if (!a) return;

    const url = new URL(a.getAttribute('href'), location.href);
    // Solo maneja anchors dentro de la misma ruta
    if (url.pathname !== location.pathname || !url.hash) return;

    const targetId = decodeURIComponent(url.hash.slice(1));
    if (!targetId) return;

    const el = document.getElementById(targetId)
      || document.querySelector('[id^="' + CSS.escape(targetId) + '"]');
    if (!el) return; // no hay destino; deja que navegue normal

    e.preventDefault();
    history.replaceState(null, '', '#' + encodeURIComponent(targetId));
    scrollToHash('#' + targetId);
  });

  // Si la página carga con hash, aplica offset
  if (location.hash) {
    setTimeout(function () { scrollToHash(location.hash); }, 0);
  }
})();