(function (wp) {
  const { registerBlockType } = wp.blocks;
  const { InspectorControls, useBlockProps } = wp.blockEditor || wp.editor;
  const {
    PanelBody, FormTokenField, RangeControl, ToggleControl,
    Spinner, Notice, Button, TextControl
  } = wp.components;
  const { useSelect } = wp.data;
  const { createElement: el, Fragment, useMemo, useState } = wp.element;

  // --- Lista ordenable por drag & drop ---
  function SortableSelected({ termIds, terms, onChange }) {
    const [dragIndex, setDragIndex] = useState(null);
    const nameOf = (id)=> {
      const t = (terms || []).find(x => x.id === id);
      return t ? t.name : `ID ${id}`;
    };
    const onDragStart = (e, i) => {
      setDragIndex(i);
      e.dataTransfer.effectAllowed = 'move';
      e.dataTransfer.setData('text/plain', String(i));
    };
    const onDragOver = (e) => { e.preventDefault(); e.dataTransfer.dropEffect = 'move'; };
    const onDrop = (e, i) => {
      e.preventDefault();
      const from = dragIndex !== null ? dragIndex : parseInt(e.dataTransfer.getData('text/plain'), 10);
      const to = i;
      if (isNaN(from) || from === to) return;
      const arr = termIds.slice();
      const [moved] = arr.splice(from, 1);
      arr.splice(to, 0, moved);
      onChange(arr);
      setDragIndex(null);
    };

    return el('ul', { className: 'thd-sortable' },
      (termIds || []).map((id, i) =>
        el('li', {
          key: id, className: 'thd-sortable__item', draggable: true,
          onDragStart: (ev)=>onDragStart(ev, i),
          onDragOver: onDragOver,
          onDrop: (ev)=>onDrop(ev, i)
        },
          el('span', { className: 'thd-handle', 'aria-hidden': true }, '≡'),
          el('span', { className: 'thd-label' }, nameOf(id)),
          el(Button, {
            isSmall: true, isDestructive: true, icon: 'no',
            onClick: ()=> onChange(termIds.filter(v => v !== id)),
            className: 'thd-remove'
          })
        )
      )
    );
  }

  function Edit({ attributes, setAttributes }) {
    const { termIds = [], perPage = 6, postsPerCat = 3, showExcerpt = true } = attributes;

    // Cargar términos (para nombres y autocompletado)
    const { terms, isResolving } = useSelect((select) => {
      const query = { per_page: -1, hide_empty: false, _fields: 'id,name,count' };
      const core = select('core');
      return {
        terms: core.getEntityRecords('taxonomy', 'category', query),
        isResolving: core.isResolving('core', 'getEntityRecords', ['taxonomy', 'category', query]),
      };
    }, []);

    const suggestions = useMemo(() => (terms || []).map(t => t.name), [terms]);
    const tokensFromIds = useMemo(
      () => (termIds || []).map(id => {
        const t = (terms || []).find(x => x.id === id);
        return t ? t.name : String(id);
      }),
      [termIds, terms]
    );

    const blockProps = useBlockProps({ className: 'thd-categorias__placeholder' });

    return el(Fragment, {},
      el(InspectorControls, {},
        el(PanelBody, { title: 'Ajustes', initialOpen: true },
          isResolving && el(Spinner, {}),
          (!isResolving && !terms) && el(Notice, { status: 'warning', isDismissible: false },
            'No se pudieron cargar las categorías (REST). Usa el campo de IDs como fallback.'
          ),
          el(FormTokenField, {
            label: 'Categorías (escribe para buscar y Enter)',
            value: tokensFromIds,
            suggestions,
            onChange: (tokens) => {
              const ids = [];
              (tokens || []).forEach(tok => {
                const t = (terms || []).find(x => x.name === tok);
                if (t && !ids.includes(t.id)) ids.push(t.id);
              });
              setAttributes({ termIds: ids });
            },
            placeholder: 'Busca y añade…'
          }),
          // Fallback por si falla REST (opcional)
          el(TextControl, {
            label: 'IDs de categorías (fallback)',
            help: 'Separados por coma. Se usa solo si arriba no carga.',
            value: (termIds || []).join(','),
            onChange: (v) => {
              const arr = (v || '').split(',').map(s => parseInt(s.trim(), 10)).filter(n => !isNaN(n));
              setAttributes({ termIds: arr });
            }
          }),
          el(RangeControl, {
            label: 'Categorías por página', min: 1, max: 24,
            value: perPage, onChange: (v) => setAttributes({ perPage: v })
          }),
          el(RangeControl, {
            label: 'Posts por categoría', min: 1, max: 6,
            value: postsPerCat, onChange: (v) => setAttributes({ postsPerCat: v })
          }),
          el(ToggleControl, {
            label: 'Mostrar extracto',
            checked: !!showExcerpt,
            onChange: (v) => setAttributes({ showExcerpt: !!v })
          })
        ),
        el(PanelBody, { title: 'Orden seleccionado (arrastra para reordenar)', initialOpen: true },
          el(SortableSelected, {
            termIds,
            terms,
            onChange: (arr) => setAttributes({ termIds: arr })
          })
        )
      ),

      el('div', blockProps,
        el('p', null, el('strong', null, 'THD · Categorías en Home')),
        el('p', null, 'Selecciona y ordena categorías. La salida final (cards + paginación) se renderiza en el frontend.'),
        el('ul', null,
          (termIds || []).map(id => {
            const t = (terms || []).find(x => x.id === id);
            return el('li', { key: id }, t ? t.name : `ID ${id}`);
          })
        )
      )
    );
  }

  registerBlockType('thd/categorias-home', {
    attributes: {
      termIds:     { type: 'array',  default: [], items: { type: 'number' } },
      perPage:     { type: 'number', default: 6 },
      postsPerCat: { type: 'number', default: 3 },
      showExcerpt: { type: 'boolean', default: true }
    },
    edit: Edit,
    save: () => null
  });
})(window.wp);
