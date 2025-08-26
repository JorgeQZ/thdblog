
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

