(function (wp) {
  const { registerBlockType } = wp.blocks;
  const { __ } = wp.i18n;
  const { InspectorControls, useBlockProps } = wp.blockEditor || wp.editor;
  const { PanelBody, SelectControl, RangeControl } = wp.components;

  registerBlockType("thd/rc-destacados", {
    edit({ attributes, setAttributes }) {
      const { termino = "guia-de-venta-destacada", postsPerPage = 8 } = attributes;
      const blockProps = useBlockProps({ className: "rc rc--editor" });

      const tituloPrincipal =
        termino === "tutorial-destacado" ? __("Tutoriales ", "thd") : __("Guías de ", "thd");
      const tituloAccent =
        termino === "tutorial-destacado" ? __("Destacados", "thd") : __("Venta", "thd");

      return wp.element.createElement(
        wp.element.Fragment,
        null,
        wp.element.createElement(
          InspectorControls,
          null,
          wp.element.createElement(
            PanelBody,
            { title: __("Opciones", "thd"), initialOpen: true },
            wp.element.createElement(SelectControl, {
              label: __("Tipo de destacado", "thd"),
              value: termino,
              options: [
                { label: __("Guía de Venta Destacada", "thd"), value: "guia-de-venta-destacada" },
                { label: __("Tutorial Destacado", "thd"), value: "tutorial-destacado" },
              ],
              onChange: (v) => setAttributes({ termino: v }),
            }),
            wp.element.createElement(RangeControl, {
              label: __("Número de posts", "thd"),
              value: postsPerPage,
              onChange: (v) => setAttributes({ postsPerPage: v }),
              min: 1,
              max: 24,
            })
          )
        ),
        wp.element.createElement(
          "div",
          blockProps,
          wp.element.createElement(
            "div",
            { className: "rc__head" },
            wp.element.createElement(
              "h3",
              { className: "rc__title" },
              wp.element.createElement("span", null, tituloPrincipal),
              wp.element.createElement("span", { className: "rc__title--accent" }, tituloAccent)
            ),
            wp.element.createElement(
              "div",
              { className: "rc__controls" },
              wp.element.createElement("button", { className: "rc__btn" }, "‹"),
              wp.element.createElement("button", { className: "rc__btn" }, "›")
            )
          ),
          wp.element.createElement(
            "div",
            { className: "rc__viewport rc__fade" },
            wp.element.createElement(
              "ul",
              { className: "rc__track" },
              Array.from({ length: Math.min(3, postsPerPage) }).map((_, i) =>
                wp.element.createElement(
                  "li",
                  { className: "rc__slide", key: i },
                  wp.element.createElement(
                    "article",
                    { className: "rc-card" },
                    wp.element.createElement("div", {
                      className: "rc-card__img",
                      style: {
                        width: "100%",
                        height: "150px",
                        background: "rgba(0,0,0,.1)",
                      },
                    }),
                    wp.element.createElement("div", { className: "rc-card__body" },
                      wp.element.createElement("div", { className: "rc-card__tag" }, termino === "tutorial-destacado" ? "Tutorial Destacado" : "Guía de Venta Destacada"),
                      wp.element.createElement("h4", { className: "rc-card__title" }, "Título de ejemplo"),
                      wp.element.createElement("p", { className: "rc-card__excerpt" }, "Extracto de ejemplo…")
                    )
                  )
                )
              )
            )
          )
        )
      );
    },
    save() {
      return null;
    },
  });
})(window.wp);

