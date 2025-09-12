<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('THD_ANCHOR_MAX')) {
    define('THD_ANCHOR_MAX', 45); // Límite práctico de anchors estilo Gutenberg
}

/** ——— Includes del tema ——— */
require_once __DIR__ . '/inc/get_posts.php';
require_once __DIR__ . '/inc/jtw_token.php';
require_once __DIR__ . '/inc/get_posttaxonomies.php';
require_once __DIR__ . '/inc/client_api.php';
require_once __DIR__ . '/inc/get_tags.php';

/** ——— Bloques (cada index.php ya registra su bloque) ——— */
require_once __DIR__ . '/blocks/thd-categorias/index.php';
require_once __DIR__ . '/blocks/rc-destacados/index.php';

/** ——— Setup de tema ——— */
function thdblog_theme_setup()
{

    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('custom-logo', array(
        'height'      => 100,
        'width'       => 400,
        'flex-height' => true,
        'flex-width'  => true,
        'header-text' => array('site-title', 'site-description'),
        'variants'    => array(
            'default' => array('label' => __('Logo Principal', 'thdblog')),
            'dark'    => array('label' => __('Logo Modo Oscuro', 'thdblog')),
        ),
    ));
    add_theme_support('menus');
    add_theme_support('html5', array('search-form', 'comment-form', 'comment-list', 'gallery', 'caption'));
}
add_action('after_setup_theme', 'thdblog_theme_setup');

/** Versión de archivo segura (atomiza la consulta y evita TOCTOU) */
function thd_filever($rel_path, $fallback = null)
{
    static $cache = [];

    // Normaliza y contiene dentro del tema
    $base = get_template_directory();
    $file = $base . '/' . ltrim((string) $rel_path, '/');

    if (isset($cache[$file])) {
        return $cache[$file];
    }

    // (Opcional pero recomendado) contención contra traversal si $rel_path viniera de input
    $realBase = realpath($base);
    $realFile = realpath($file); // false si no existe

    // Si no existe o sale del directorio del tema, usa fallback
    if ($realFile === false || strpos($realFile, $realBase) !== 0) {
        $ver = $fallback ?: (wp_get_theme()->get('Version') ?: '1.0.0');
        return $cache[$file] = $ver;
    }

    // Asegura lectura fresca y evita warnings usando una sola llamada
    clearstatcache(false, $realFile);
    $stat = @stat($realFile); // una sola syscall; incluye mtime

    if (is_array($stat) && isset($stat['mtime'])) {
        return $cache[$file] = (string) $stat['mtime'];
    }

    $ver = $fallback ?: (wp_get_theme()->get('Version') ?: '1.0.0');
    return $cache[$file] = $ver;
}
/** ——— Assets ——— */
function thdblog_enqueue_assets()
{
    wp_enqueue_style(
        'thdblog-main',
        get_template_directory_uri() . '/css/main.css',
        array(),
        thd_filever('/css/main.css')
    );

    wp_enqueue_script(
        'thdblog-main-js',
        get_template_directory_uri() . '/js/main.js',
        array(),
        thd_filever('/js/main.js'),
        true
    );

    if (is_archive() || is_search()) {
        wp_enqueue_style(
            'thdblog-archive',
            get_template_directory_uri() . '/css/archive.css',
            array('thdblog-main'),
            thd_filever('/css/archive.css')
        );
    }

    if (is_single()) {
        wp_enqueue_style(
            'thdblog-single',
            get_template_directory_uri() . '/css/single.css',
            array('thdblog-main'),
            thd_filever('/css/single.css')
        );
    }

    if (is_author()) {
        wp_enqueue_style(
            'thdblog-author',
            get_template_directory_uri() . '/css/author.css',
            array('thdblog-main'),
            thd_filever('/css/author.css')
        );
    }

    $ver = wp_get_theme()->get('Version') ?: '1.0.0';

    wp_enqueue_script('thd-header-search', get_template_directory_uri() . '/js/header-search.js', [], $ver, true);
    wp_localize_script('thd-header-search', 'THD_SEARCH', [
        'rest' => esc_url_raw(rest_url('wp/v2/search')),
        'home' => esc_url_raw(home_url('/')),
        'labels' => [
            'placeholder' => __('Buscar…', 'thd'),
            'noResults'   => __('Sin resultados', 'thd'),
            'seeAll'      => __('Ver todos los resultados', 'thd'),
        ],
        'maxSuggestions' => 6,
    ]);
}
add_action('wp_enqueue_scripts', 'thdblog_enqueue_assets');

/** ——— Buscar también en CPTs ——— */
add_action('pre_get_posts', function ($q) {
    if (!is_admin() && $q->is_main_query() && $q->is_search()) {
        $q->set('post_type', ['post', 'page', 'venta_guiada', 'pks']);
    }
});

/** ——— Filtros ACF (relaciones por tipo) ——— */
add_filter('acf/fields/relationship/query/name=related_posts_guias', function ($args) {
    $args['meta_query'] = [[
        'key'     => '_posttype',
        'value'   => 'Buying Guide',
        'compare' => '='
    ]];
    return $args;
}, 10, 3);

add_filter('acf/fields/relationship/query/name=related_posts_tutoriales', function ($args) {
    $args['meta_query'] = [[
        'key'     => '_posttype',
        'value'   => 'Tutorial',
        'compare' => '='
    ]];
    return $args;
}, 10, 3);

/** ——— Debug de plantilla solo para admins ——— */
add_action('wp_footer', function () {
    if (is_user_logged_in() && current_user_can('manage_options')) {
        global $template;
        echo '<!-- Current template: ' . esc_html(basename($template)) . ' -->';
    }
});

/** ——— Orden dinámico en archivos ——— */
add_action('pre_get_posts', function ($q) {
    if (!is_admin() && $q->is_main_query() && $q->is_archive()) {
        $sort_title = isset($_GET['sort_title']) ? sanitize_text_field($_GET['sort_title']) : '';
        if ($sort_title === 'asc' || $sort_title === 'desc') {
            $q->set('orderby', 'title');
            $q->set('order', strtoupper($sort_title));
        }

        $sort_date = isset($_GET['sort_date']) ? sanitize_text_field($_GET['sort_date']) : '';
        if ($sort_date === 'asc' || $sort_date === 'desc') {
            $q->set('orderby', 'date');
            $q->set('order', strtoupper($sort_date));
        }
    }
});

/** ——— CORS (solo REST) ——— */
add_action('send_headers', function () {
    $allowed = array_filter(array_map('thd_norm_origin', [
        get_home_url(null, '', 'https'),
        get_home_url(null, '', 'http'),
        // Agrega orígenes extra aquí si aplica
    ]));

    // Limitar a REST
    $is_rest = (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/wp-json/') === 0);
    if (!$is_rest) {
        return;
    }

    $origin_raw = isset($_SERVER['HTTP_ORIGIN']) ? trim($_SERVER['HTTP_ORIGIN']) : '';
    $origin = thd_norm_origin($origin_raw);

    if ($origin && in_array($origin, $allowed, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
        header('Access-Control-Allow-Methods: GET, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        // header('Access-Control-Allow-Credentials: true'); // si lo necesitas
    }

    if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        if ($origin && in_array($origin, $allowed, true)) {
            status_header(204);
            header('Content-Length: 0');
            exit;
        }
        status_header(403);
        exit;
    }
}, 0);

/** Normaliza origin a scheme://host[:port] */
function thd_norm_origin($url)
{
    if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
        return null;
    }
    $p = wp_parse_url($url);
    if (empty($p['scheme']) || empty($p['host'])) {
        return null;
    }
    $norm = $p['scheme'] . '://' . $p['host'];
    if (!empty($p['port'])) {
        $norm .= ':' . intval($p['port']);
    }
    return $norm;
}

/** ——— NUEVA taxonomía: contenido_destacado ——— */
add_action('init', function () {
    register_taxonomy('contenido_destacado', ['post'], [
        'labels' => [
            'name'              => __('Contenido Destacado', 'thd'),
            'singular_name'     => __('Contenido Destacado', 'thd'),
            'search_items'      => __('Buscar Contenido Destacado', 'thd'),
            'all_items'         => __('Todos', 'thd'),
            'edit_item'         => __('Editar Contenido Destacado', 'thd'),
            'update_item'       => __('Actualizar Contenido Destacado', 'thd'),
            'add_new_item'      => __('Agregar Contenido Destacado', 'thd'),
            'new_item_name'     => __('Nuevo Contenido Destacado', 'thd'),
            'menu_name'         => __('Contenido Destacado', 'thd'),
        ],
        'hierarchical'      => true,   // UI tipo categoría (checkbox)
        'show_ui'           => true,
        'show_in_rest'      => true,   // Gutenberg
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => ['slug' => 'contenido-destacado'],
    ]);

    // Asegura el término "Contenido Destacado"
    if (!term_exists('contenido-destacado', 'contenido_destacado')) {
        wp_insert_term('Contenido Destacado', 'contenido_destacado', [
            'slug' => 'contenido-destacado',
        ]);
    }
}, 9);

/** ——— Migración opcional: 'destacado' -> 'contenido_destacado' ——— */
add_action('admin_init', function () {
    if (get_option('thd_migracion_destacado_a_contenido_done')) {
        return;
    }

    if (!taxonomy_exists('destacado') || !taxonomy_exists('contenido_destacado')) {
        return;
    }

    $old_terms = ['tutorial-destacado', 'guia-de-venta-destacada'];
    $posts = get_posts([
        'post_type'      => 'post',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'tax_query'      => [[
            'taxonomy' => 'destacado',
            'field'    => 'slug',
            'terms'    => $old_terms,
        ]],
        'fields'         => 'ids',
    ]);

    if ($posts) {
        foreach ($posts as $pid) {
            wp_set_object_terms($pid, 'contenido-destacado', 'contenido_destacado', true);
        }
    }

    update_option('thd_migracion_destacado_a_contenido_done', 1, false);
});

/** ——— Desregistrar 'destacado' SOLO cuando ya migraste ——— */
add_action('init', function () {
    if (!get_option('thd_migracion_destacado_a_contenido_done')) {
        return;
    }
    if (taxonomy_exists('destacado') && function_exists('unregister_taxonomy')) {
        unregister_taxonomy('destacado');
    }
}, 100);

/** ——— Ocultar restos de taxonomías antiguas en admin (seguridad extra) ——— */
add_filter('manage_edit-post_columns', function ($cols) {
    $remove_by_key = [
        'taxonomy-guias_de_venta_destacadas',
        'taxonomy-tutoriales_destacados',
        'taxonomy-guia_destacada',
        'taxonomy-tutorial_destacado',
        'taxonomy-destacado',
    ];
    foreach ($remove_by_key as $k) {
        if (isset($cols[$k])) {
            unset($cols[$k]);
        }
    }

    $remove_by_label = [
        'Guías de venta destacadas',
        'Tutoriales destacados',
        'Guía de Venta Destacada',
        'Tutorial Destacado',
        'Destacado',
    ];
    foreach ($cols as $k => $label) {
        if (in_array(wp_strip_all_tags($label), $remove_by_label, true)) {
            unset($cols[$k]);
        }
    }
    return $cols;
}, 999);

add_action('admin_menu', function () {
    $old_tax = [
        'guias_de_venta_destacadas',
        'tutoriales_destacidos', // corrige si tu slug real era otro
        'tutoriales_destacados',
        'guia_destacada',
        'tutorial_destacado',
        'destacado',
    ];
    foreach ($old_tax as $tax) {
        remove_meta_box("{$tax}div", 'post', 'side');
    }
}, 100);

/** ——— Helper: imagen destacada segura con fallback ——— */
if (!function_exists('thd_safe_thumb_html')) {
    function thd_safe_thumb_html($post_id, $size = 'large', $class = 'rc-card__img', $fallback = null)
    {
        $fallback = $fallback ?: apply_filters(
            'thd_fallback_thumb',
            get_template_directory_uri() . '/img/cover-default.jpg'
        );

        $alt = get_the_title($post_id);
        $thumb_id = get_post_thumbnail_id($post_id);

        if ($thumb_id && is_numeric($thumb_id) && get_post_type($thumb_id) === 'attachment') {
            $alt_meta = get_post_meta((int)$thumb_id, '_wp_attachment_image_alt', true);
            if ($alt_meta !== '') {
                $alt = $alt_meta;
            }

            $file = get_attached_file((int)$thumb_id);
            if ($file && file_exists($file)) {
                return wp_get_attachment_image(
                    (int)$thumb_id,
                    $size,
                    false,
                    [
                        'class'    => $class,
                        'loading'  => 'lazy',
                        'decoding' => 'async',
                        'alt'      => $alt,
                    ]
                );
            }
        }

        return sprintf(
            '<img class="%s" src="%s" alt="%s" loading="lazy" decoding="async">',
            esc_attr($class),
            esc_url($fallback),
            esc_attr($alt)
        );
    }
}


// === Registro seguro del bloque rc-destacados (Contenido Destacado/Relacionado) ===
add_action('init', function () {
    if (!class_exists('WP_Block_Type_Registry')) {
        return;
    }

    $block_dir   = get_template_directory() . '/blocks/rc-destacados';
    $block_json  = $block_dir . '/block.json';
    $render_php  = $block_dir . '/render.php';
    $block_name  = 'thd/rc-destacados'; // Asegúrate que coincide con "name" en block.json

    if (!file_exists($block_json)) {
        // block.json ausente = no hay nada que registrar
        return;
    }

    // Si ya está registrado por index.php, no lo registramos de nuevo
    $registry = WP_Block_Type_Registry::get_instance();
    if ($registry->is_registered($block_name)) {
        return;
    }

    // Incluye el render callback si existe
    if (file_exists($render_php)) {
        require_once $render_php;
    }

    // Regístralo desde metadata y engancha el render si está disponible
    register_block_type_from_metadata($block_dir, [
        'render_callback' => function ($attributes = [], $content = '', $block = null) {
            if (function_exists('thd_render_rc_destacados_block')) {
                return thd_render_rc_destacados_block($attributes, $content, $block);
            }
            return ''; // sin render callback -> no imprime nada
        },
    ]);
}, 20); // prioridad > 10 para asegurar que ya corrió 'init' de taxonomías



// functions.php
add_action('after_setup_theme', function () {
    add_image_size('thd_1024x529', 1024, 529, true); // hard crop centrado
});
