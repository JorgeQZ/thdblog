<?php

// === Bloque dinámico "Categorías Destacadas" con ACF ===
add_action('acf/init', function () {
    if (!function_exists('acf_register_block_type')) {
        return;
    }

    acf_register_block_type([
        'name'            => 'thd-categorias',
        'title'           => __('Categorías destacadas', 'thd'),
        'description'     => __('Grid de categorías con orden manual y paginación.', 'thd'),
        'category'        => 'widgets',
        'icon'            => 'category',
        'keywords'        => ['categorías', 'taxonomías', 'grid'],
        'mode'            => 'edit',
        'supports'        => ['align' => false, 'jsx' => true],
        'render_callback' => 'thd_render_categorias_block',
        'enqueue_assets'  => function () {
            // CSS mínimo opcional
            wp_add_inline_style('wp-block-library', '
                .thd-cats{display:grid;grid-template-columns:repeat(3,1fr);gap:16px}
                .thd-cats__item{border:1px solid #eee;border-radius:12px;padding:16px;background:#fff}
                .thd-cats__name{font-weight:700;margin:0 0 6px}
                .thd-cats__count{opacity:.7;font-size:.9em}
                .thd-cats__nav{display:flex;gap:8px;justify-content:center;margin-top:16px}
                .thd-cats__nav a,.thd-cats__nav span{padding:6px 10px;border-radius:8px;border:1px solid #ddd;text-decoration:none}
                .thd-cats__nav .is-active{background:#111;color:#fff;border-color:#111}
            ');
        },
    ]);
});

// === Campos del bloque via ACF Local JSON/PHP ===
add_action('acf/init', function () {
    if (!function_exists('acf_add_local_field_group')) {
        return;
    }

    acf_add_local_field_group([
        'key'    => 'group_thd_categorias',
        'title'  => 'Ajustes: Categorías destacadas',
        'fields' => [
            [
                'key'               => 'field_thd_categorias_terms',
                'label'             => 'Categorías',
                'name'              => 'categorias_terms',
                'type'              => 'taxonomy',
                'taxonomy'          => 'category',
                'field_type'        => 'select',     // mantiene el orden de selección
                'add_term'          => 0,
                'save_terms'        => 0,
                'load_terms'        => 0,
                'multiple'          => 1,
                'return_format'     => 'object',     // nos regresa objetos término
                'instructions'      => 'Selecciona y **ordena** las categorías en el orden que quieras mostrar.',
            ],
            [
                'key'           => 'field_thd_categorias_per_page',
                'label'         => 'Categorías por página',
                'name'          => 'categorias_per_page',
                'type'          => 'number',
                'default_value' => 6,
                'min'           => 1,
                'max'           => 24,
            ],
            [
                'key'           => 'field_thd_categorias_show_count',
                'label'         => 'Mostrar conteo de posts',
                'name'          => 'categorias_show_count',
                'type'          => 'true_false',
                'ui'            => 1,
                'default_value' => 1,
            ],
            [
                'key'           => 'field_thd_categorias_show_pagination',
                'label'         => 'Mostrar paginación si excede el máximo',
                'name'          => 'categorias_show_pagination',
                'type'          => 'true_false',
                'ui'            => 1,
                'default_value' => 1,
            ],
        ],
        'location' => [
            [
                [
                    'param'    => 'block',
                    'operator' => '==',
                    'value'    => 'acf/thd-categorias',
                ],
            ],
        ],
    ]);
});

// === Render del bloque ===
function thd_render_categorias_block($block, $content = '', $is_preview = false, $post_id = 0)
{
    // Atributos/valores desde ACF
    $terms           = get_field('categorias_terms') ?: [];
    $per_page        = (int)(get_field('categorias_per_page') ?: 6);
    $show_count      = !!get_field('categorias_show_count');
    $show_pagination = !!get_field('categorias_show_pagination');

    // Nada seleccionado -> no pintar
    if (empty($terms)) {
        echo '<p style="opacity:.7">Selecciona categorías en el panel del bloque.</p>';
        return;
    }

    // ID único del bloque para que la paginación no choque si pones varios bloques
    $instance_id = isset($block['id']) ? preg_replace('/[^a-z0-9]/i', '', $block['id']) : 'cats';
    $param       = 'cats_'.$instance_id.'_page';

    // Página actual (por query arg)
    $current     = isset($_GET[$param]) ? max(1, (int)$_GET[$param]) : 1;

    // Paginación por términos (no posts): cortamos el array de términos
    $total       = count($terms);
    $pages       = max(1, (int)ceil($total / max(1, $per_page)));
    if ($current > $pages) {
        $current = $pages;
    }

    $offset      = ($current - 1) * $per_page;
    $slice       = array_slice($terms, $offset, $per_page);

    // Salida
    echo '<div class="thd-cats" data-thd-cats="'.$instance_id.'">';
    foreach ($slice as $term) {
        if (!is_object($term)) {
            continue;
        }
        $link  = get_term_link($term);
        if (is_wp_error($link)) {
            continue;
        }

        $count = (int)$term->count;
        echo '<article class="thd-cats__item">';
        echo   '<a class="thd-cats__name" href="'.esc_url($link).'">'.esc_html($term->name).'</a>';
        if ($show_count) {
            echo '<div class="thd-cats__count">'.sprintf(_n('%s entrada', '%s entradas', $count, 'thd'), number_format_i18n($count)).'</div>';
        }
        if (!empty($term->description)) {
            echo '<p class="thd-cats__desc">'.esc_html(wp_trim_words($term->description, 22)).'</p>';
        }
        echo '</article>';
    }
    echo '</div>';

    // Navegación (si procede)
    if ($show_pagination && $pages > 1) {
        echo '<nav class="thd-cats__nav" aria-label="'.esc_attr__('Paginación de categorías', 'thd').'">';
        for ($i = 1; $i <= $pages; $i++) {
            $url = add_query_arg($param, $i);
            if ($i === $current) {
                echo '<span class="is-active" aria-current="page">'.esc_html($i).'</span>';
            } else {
                echo '<a href="'.esc_url($url).'">'.esc_html($i).'</a>';
            }
        }
        echo '</nav>';
    }
}
