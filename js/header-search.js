(function () {
  const root  = document.querySelector('[data-thd-search]');
  if (!root) return;

  const panel = root.querySelector('.thd-search__panel');        // visible siempre
  const input = root.querySelector('.thd-search__input');
  const list  = root.querySelector('.thd-search__suggestions');

  let lastQuery = '';
  let abortCtrl = null;
  const MAX = (window.THD_SEARCH && THD_SEARCH.maxSuggestions) || 6;

  // Helpers
  const debounce = (fn, delay = 180) => {
    let t; return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), delay); };
  };

  const clearSuggestions = () => {
    if (!list) return;
    list.innerHTML = '';
    list.id = 'thd-search-suggestions';
  };

  const escapeHtml = (s) =>
    String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

  const renderSuggestions = (items, q) => {
    clearSuggestions();
    if (!items || !items.length) {
      const li = document.createElement('div');
      li.className = 'thd-sg__item thd-sg__empty';
      li.textContent = (THD_SEARCH && THD_SEARCH.labels?.noResults) || 'Sin resultados';
      list.appendChild(li);
      return;
    }

    items.slice(0, MAX).forEach(it => {
      const a = document.createElement('a');
      a.className = 'thd-sg__item';
      a.href = it.url || (THD_SEARCH ? THD_SEARCH.home : '/') + '?s=' + encodeURIComponent(q);
      a.setAttribute('role', 'option');
      a.innerHTML = `
        <span class="thd-sg__title">${escapeHtml(it.title || it?.title?.rendered || '')}</span>
      `;
      list.appendChild(a);
    });

    const more = document.createElement('a');
    more.className = 'thd-sg__more';
    more.href = (THD_SEARCH ? THD_SEARCH.home : '/') + '?s=' + encodeURIComponent(q);
    more.textContent = (THD_SEARCH && THD_SEARCH.labels?.seeAll) || 'Ver todos los resultados';
    list.appendChild(more);
  };

  const doSearch = async (q) => {
    if (!THD_SEARCH || !THD_SEARCH.rest) return;
    if (abortCtrl) abortCtrl.abort();
    abortCtrl = new AbortController();

    const u = new URL(THD_SEARCH.rest);
    u.searchParams.set('search', q);
    u.searchParams.set('per_page', String(MAX));
    // Si quieres limitar subtipos, descomenta:
    // if (THD_SEARCH.subtype) u.searchParams.set('subtype', THD_SEARCH.subtype);

    try {
      const res = await fetch(u.toString(), { signal: abortCtrl.signal });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const data = await res.json();
      renderSuggestions(data || [], q);
    } catch (e) {
      if (e.name !== 'AbortError') clearSuggestions();
    }
  };

  // Eventos
  const onInput = debounce((e) => {
    const q = e.target.value.trim();
    if (!q) { clearSuggestions(); return; }
    if (q === lastQuery) return;
    lastQuery = q;
    doSearch(q);
  }, 180);

  // Limpia con ESC (no oculta panel, solo la lista)
  const onKeyDown = (e) => {
    if (e.key === 'Escape') clearSuggestions();
  };

  // Clic fuera → limpia lista (panel permanece visible)
  const onDocClick = (e) => {
    if (!root.contains(e.target)) clearSuggestions();
  };

  // Bind
  if (input) {
    input.addEventListener('input', onInput);
    input.addEventListener('keydown', onKeyDown);
  }
  document.addEventListener('click', onDocClick, { capture: true });

  // Asegura que el panel no esté oculto por atributo hidden
  if (panel && panel.hasAttribute('hidden')) panel.removeAttribute('hidden');
})();
