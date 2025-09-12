<?php
add_action('rest_api_init', function () {
    register_rest_route('v1', '/posts', [
        'methods'             => 'GET',
        'callback'            => 'blog_get_posts',
         'permission_callback' => 'blog_rest_permission'

        // 'permission_callback' => '__return_true'
    ]);
});

// /** Permite solo GET (público). Ajusta si quieres endurecer. */
// function blog_rest_permission($request) {
//     return ($request instanceof WP_REST_Request) && $request->get_method() === 'GET';
// }

function blog_get_posts($request)
{
    // Paginación segura: 1..100, default 12
    $page     = max(1, (int) $request->get_param('page'));
    $per_req  = (int) $request->get_param('per_page');
    $per_page = max(1, min(100, $per_req > 0 ? $per_req : 12));

    $args = [
        'post_type'        => 'post',
        'posts_per_page'   => $per_page,
        'paged'            => $page,
        'post_status'      => 'publish',
        'ignore_sticky_posts' => true,
    ];

    // (Opcional) soporta filtros básicos sin romper contratos
    if ($s = (string) $request->get_param('s')) {
        $args['s'] = sanitize_text_field($s);
    }
    if ($cat = (int) $request->get_param('cat')) {
        $args['cat'] = $cat;
    }
    if ($tag = (string) $request->get_param('tag')) {
        $args['tag'] = sanitize_title($tag);
    }

    $query = new WP_Query($args);

    // Helpers internos
    $meta = function ($id, $key) {
        $v = get_post_meta($id, $key, true);
        return is_string($v) && $v !== '' ? $v : (is_array($v) ? $v : null);
    };
    $meta_json = function ($id, $key) use ($meta) {
        $raw = $meta($id, $key);
        if (!$raw) {
            return null;
        }
        if (is_array($raw)) {
            return $raw;
        }
        $d = json_decode($raw, true);
        return $d ?: null;
    };
    $nulls = function ($v) {
        // Mantiene tu contrato: null/false/'' -> 'null' (string)
        return ($v === null || $v === false || $v === '') ? 'null' : $v;
    };
    $gallery_to_array = function ($raw) {
        if (!$raw) {
            return [];
        }
        if (is_array($raw)) {
            return array_values(array_filter($raw));
        }
        $json = json_decode($raw, true);
        if (is_array($json)) {
            return array_values(array_filter($json));
        }
        $parts = preg_split('/[\r\n,]+/', (string)$raw);
        return array_values(array_filter(array_map('trim', $parts)));
    };
    $collect_related = function ($id) {
        $ids = [];
        if (function_exists('get_field')) {
            $gv = get_field('guias_de_venta', $id);
            if (!empty($gv['related_posts_guias'])) {
                $ids = array_merge($ids, array_map('intval', (array)$gv['related_posts_guias']));
            }
            $tt = get_field('related_posts_tutoriales', $id);
            if (!empty($tt['related_posts_tutoriales'])) {
                $ids = array_merge($ids, array_map('intval', (array)$tt['related_posts_tutoriales']));
            }
        }
        return array_values(array_unique(array_filter(array_map('intval', $ids))));
    };

    $default_cover = apply_filters('thd_api_fallback_thumb', get_template_directory_uri() . '/img/cover-default.jpg');

    $posts = [];
    foreach ($query->posts as $post) {
        $id        = $post->ID;
        $author_id = $post->post_author;

        // Imágenes con fallback
        $thumb = get_the_post_thumbnail_url($id, 'medium') ?: $default_cover;
        $main  = get_the_post_thumbnail_url($id, 'full') ?: $thumb;
        $ogimg = $meta($id, '_og_image') ?: $main ?: $thumb;
        $twimg = $meta($id, '_twitter_image') ?: $ogimg ?: $main ?: $thumb;

        // Galería
        $gallery = $gallery_to_array($meta($id, '_gallery_images'));

        // Breadcrumb/Schema
        $breadcrumb = $meta_json($id, '_structured_breadcrumb');
        $schema_ld  = $meta_json($id, '_schema_json');

        // Relacionados (IDs)
        $related = $collect_related($id);

        // Navigator como array de <a> (permalink#id normalizado/truncado)
        $nav_json = function_exists('get_field')
            ? get_field(THD_NAV_ACF_FIELD, $id)
            : get_post_meta($id, THD_NAV_ACF_FIELD, true);

        $items = json_decode((string)$nav_json, true);
        $navigator_array = [];
        if (is_array($items) && !empty($items)) {
            $base = get_permalink($id);
            $seen = [];
            foreach ($items as $it) {
                $label = isset($it['label']) ? trim((string)$it['label']) : '';
                $nid   = isset($it['id']) ? trim((string)$it['id']) : '';
                if ($label && $nid) {
                    if (function_exists('thd_normalize_anchor')) {
                        $nid = thd_normalize_anchor($nid, $seen, defined('THD_ANCHOR_MAX') ? THD_ANCHOR_MAX : 45);
                        $seen[] = $nid;
                    } else {
                        // fallback mínimo
                        if (strpos($nid, '#') !== false) {
                            $nid = substr($nid, strpos($nid, '#') + 1);
                        }
                        $nid = sanitize_title(ltrim($nid, '/'));
                    }
                    $navigator_array[] = '<a href="' . esc_url($base . '#' . $nid) . '">' . esc_html($label) . '</a>';
                }
            }
        }

        // Contenido crudo + renderizado
        $content_raw = get_post_field('post_content', $id);
        $content_rendered = apply_filters('the_content', $content_raw);
        $content_plain = thd_content_plain($content_raw);

        $posts[] = [
            // Core
            'id'                 => $id,
            'date'               => $post->post_date,
            'modified'           => $post->post_modified,
            'status'             => $post->post_status,
            'link'               => get_permalink($post),
            'postType'           => $meta($id, '_posttype') ?: 'Tutorial',
            'title'              => get_the_title($post),
            'author'             => get_the_author_meta('display_name', $author_id),
            'authorDescription'  => $nulls(get_the_author_meta('description', $author_id)),
            'difficulty'         => $nulls($meta($id, '_difficulty')),
            'duration'           =>  $nulls($meta($id, '_duration')),

            // Imágenes (con fallback)
            'thumbnail'          => $nulls($thumb),
            'mainImage'          => $nulls($main),

            // Excerpt / Descripción
            'shortDescription'   => $nulls($meta($id, '_short_description')),

            // Media y tax
            'video'              => $nulls($meta($id, '_video_url')),
            'categories'         => wp_get_post_categories($id),
            'tags'               => array_map(function ($t) { return $t->name; }, wp_get_post_tags($id)),

            // Navigator y relacionados
            'navigator'          => !empty($navigator_array) ? $navigator_array : 'null',
            'relatedPosts'       => !empty($related) ? $related : 'null',

            // Contenidos
            'content'            => wp_kses_post($content_raw),
            'contentRendered'    => wp_kses_post($content_rendered),
            'contentPlain' => $nulls($content_plain),


            // SEO básicos
            'seoTitle'           => $nulls($meta($id, '_seo_title')),
            'metaDescripcion'    => $nulls($meta($id, '_meta_description')),
            'metaKeywords'       => $nulls($meta($id, '_meta_keywords')),
            'canonicalUrl'       => $nulls($meta($id, '_canonical_url')),
            'robotsDirectives'   => $nulls($meta($id, '_robots_directives')),

            // Open Graph
            'ogTitle'            => $nulls($meta($id, '_og_title') ?: get_the_title($id)),
            'ogDescription'      => $nulls($meta($id, '_og_description') ?: $meta($id, '_short_description')),
            'ogImage'            => $nulls($ogimg),
            'ogType'             => $nulls($meta($id, '_og_type') ?: 'article'),
            'ogUrl'              => $nulls($meta($id, '_og_url') ?: get_permalink($id)),

            // Twitter Card
            'twitterCardType'    => $nulls($meta($id, '_twitter_card_type') ?: 'summary_large_image'),
            'twitterTitle'       => $nulls($meta($id, '_twitter_title') ?: get_the_title($id)),
            'twitterDescription' => $nulls($meta($id, '_twitter_description') ?: $meta($id, '_short_description')),
            'twitterImage'       => $nulls($twimg),
            'twitterCreator'     => $nulls($meta($id, '_twitter_creator')),

            // SEO avanzados
            'schemaType'           => $nulls($meta($id, '_schema_type') ?: 'Article'),
            'schemaJson'           => $schema_ld ?: 'null',
            'focusKeyword'         => $nulls($meta($id, '_focus_keyword')),
            'metaRobotsAdvanced'   => $nulls($meta($id, '_meta_robots_advanced')),
            'metaViewport'         => $nulls($meta($id, '_meta_viewport')),
            'canonicalAlternate'   => $nulls($meta($id, '_canonical_alternate')),
            'altTextMainImage'     => $nulls($meta($id, '_alt_text_main_image')),
            'imageCaption'         => $nulls($meta($id, '_image_caption')),
            'videoTranscript'      => $nulls($meta($id, '_video_transcript')),
            'galleryImages'        => !empty($gallery) ? $gallery : [],
            'metaRefresh'          => $nulls($meta($id, '_meta_refresh')),
            'structuredBreadcrumb' => $breadcrumb ?: 'null',
            'pagination'           => [
                'prev' => $nulls($meta($id, '_pagination_prev')),
                'next' => $nulls($meta($id, '_pagination_next')),
            ],
        ];
    }

    return [
        'page'        => (int) $page,
        'per_page'    => (int) $per_page,
        'total'       => (int) $query->found_posts,
        'total_pages' => (int) $query->max_num_pages,
        'posts'       => $posts,
    ];
}

if (!function_exists('thd_content_plain')) {
    function thd_content_plain($raw)
    {
        $text = (string) $raw;

        // Quitar comentarios de bloques Gutenberg <!-- wp:... --> <!-- /wp:... -->
        $text = preg_replace('/<!--\s*\/?wp:.*?-->/', '', $text);

        // Quitar cualquier comentario HTML restante
        $text = preg_replace('/<!--(?!<!)[^\[>].*?-->/', '', $text);

        // Quitar shortcodes [shortcode]...[/shortcode]
        $text = strip_shortcodes($text);

        // Quitar todas las etiquetas HTML, script/style incl.
        $text = wp_strip_all_tags($text, true);

        // Decodificar entidades y normalizar espacios/line breaks
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\x{00A0}/u', ' ', $text); // &nbsp; → espacio normal
        $text = preg_replace("/\r\n|\r/", "\n", $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text); // compactar saltos múltiples
        $text = trim($text);

        return $text;
    }
}
/** Related por tags/categorías (con preferencia a manual) */
function get_related_posts($post_id, $limit = 4)
{
    $manual_raw = get_post_meta($post_id, '_related_posts', true);
    $manual     = json_decode($manual_raw, true);
    if (is_array($manual) && !empty($manual)) {
        return array_values(array_unique(array_filter(array_map('intval', $manual))));
    }

    $tags = wp_get_post_tags($post_id, ['fields' => 'ids']);
    $cats = wp_get_post_categories($post_id);

    $query = new WP_Query([
        'post__not_in'        => [(int)$post_id],
        'posts_per_page'      => (int)$limit,
        'ignore_sticky_posts' => true,
        'orderby'             => 'date',
        'order'               => 'DESC',
        'fields'              => 'ids',
        'tax_query'           => [
            'relation' => 'OR',
            [
                'taxonomy' => 'post_tag',
                'field'    => 'term_id',
                'terms'    => $tags,
            ],
            [
                'taxonomy' => 'category',
                'field'    => 'term_id',
                'terms'    => $cats,
            ]
        ]
    ]);

    return !empty($query->posts) ? array_map('intval', $query->posts) : [];
}

/**
 * Endpoint REST para páginas limpias.
 */
add_action('rest_api_init', function () {
    register_rest_route('v1', '/pages', [
        'methods'             => 'GET',
        'callback'            => 'blog_get_clean_pages',
        'permission_callback' => 'blog_rest_permission'
    ]);
});

function blog_get_clean_pages($request)
{
    $page     = max(1, (int) $request->get_param('page'));
    $per_req  = (int) $request->get_param('per_page');
    $per_page = max(1, min(100, $per_req > 0 ? $per_req : 10));

    $args = [
        'post_type'      => 'page',
        'post_status'    => 'publish',
        'paged'          => $page,
        'posts_per_page' => $per_page,
    ];

    $query = new WP_Query($args);

    $pages = [];
    foreach ($query->posts as $post) {
        $pages[] = [
            'id'          => $post->ID,
            'title'       => get_the_title($post),
            'excerpt'     => wp_strip_all_tags(get_the_excerpt($post)),
            'content'     => wp_kses_post(apply_filters('the_content', $post->post_content)),
            'slug'        => $post->post_name,
            'link'        => get_permalink($post),
            'date'        => get_the_date('c', $post),
            'author_name' => get_the_author_meta('display_name', $post->post_author),
        ];
    }

    return [
        'page'        => (int) $page,
        'per_page'    => (int) $per_page,
        'total'       => (int) $query->found_posts,
        'total_pages' => (int) $query->max_num_pages,
        'pages'       => $pages,
    ];
}

/** ======================
 * Navegación por anchors -> guarda JSON en ACF después de que ACF guarda.
 * ====================== */
if (!defined('THD_NAV_ACF_FIELD')) {
    define('THD_NAV_ACF_FIELD', 'field_navigator');
}
// 1) Helper: normaliza SIN bajar a minúsculas (preserva el case)
if (!function_exists('thd_sanitize_anchor_preserve_case')) {
    function thd_sanitize_anchor_preserve_case(string $raw): string
    {
        $id = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $id = trim($id);
        $id = preg_replace('/\s+/u', '-', $id);                // espacios -> guiones
        $id = preg_replace('/[^A-Za-z0-9\-\_:.]/u', '-', $id); // set permitido
        $id = preg_replace('/-+/', '-', $id);                  // colapsa guiones
        $id = trim($id, '-');
        return $id !== '' ? $id : 'section';
    }
}

// 2) Helper: hace único el ID preservando el case (case-insensitive para colisiones)
if (!function_exists('thd_make_unique_anchor')) {
    function thd_make_unique_anchor(string $base, array &$taken): string
    {
        $base = thd_sanitize_anchor_preserve_case($base);
        $candidate = $base;
        $i = 2;
        while (isset($taken[strtolower($candidate)])) {
            $candidate = $base . '-' . $i++;
        }
        $taken[strtolower($candidate)] = true;
        return $candidate;
    }
}

add_action('acf/save_post', function ($post_id) {
    $post = get_post($post_id);
    if (!$post || $post->post_type === 'revision' || wp_is_post_autosave($post_id)) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    if (empty($post->post_content)) {
        thd_nav_update_acf($post_id, '');
        thd_nav_admin_notice($post_id, 0);
        return;
    }

    $content = (string)$post->post_content;
    $items   = [];

    if (class_exists('DOMDocument')) {
        libxml_use_internal_errors(true);
        $dom  = new DOMDocument('1.0', 'UTF-8');
        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>'.$content.'</body></html>';
        if ($dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD)) {
            $xp   = new DOMXPath($dom);
            $nodes = $xp->query('//*[@id and not(self::script or self::style or self::noscript or self::svg or self::path) and not(ancestor::svg) and not(ancestor::header or ancestor::footer or ancestor::nav)]');

            $seen = [];
            foreach ($nodes as $node) {
                /** @var DOMElement $node */
                $rawId = trim($node->getAttribute('id'));
                if ($rawId === '') {
                    continue;
                }

                // —— reemplazo de sanitize_title(): preserva mayúsculas + unicidad
                $id = thd_sanitize_anchor_preserve_case($rawId);
                if (isset($seen[strtolower($id)])) {
                    $id = thd_make_unique_anchor($id, $seen);
                } else {
                    $seen[strtolower($id)] = true;
                }

                $tag = strtolower($node->tagName);
                $label = $node->getAttribute('aria-label')
                    ?: $node->getAttribute('data-label')
                    ?: $node->getAttribute('data-title')
                    ?: ($node->hasAttribute('alt') ? $node->getAttribute('alt') : '');

                if ($label === '') {
                    $text = trim(preg_replace('/\s+/', ' ', $node->textContent ?? ''));
                    if ($text === '') {
                        $head = $xp->query('.//h1|.//h2|.//h3|.//h4|.//h5|.//h6', $node)->item(0);
                        if ($head instanceof DOMElement) {
                            $text = trim(preg_replace('/\s+/', ' ', $head->textContent ?? ''));
                        }
                    }
                    $label = $text !== '' ? $text : $id;
                }

                $label = wp_strip_all_tags($label);
                if (function_exists('mb_strlen') && function_exists('mb_substr') && mb_strlen($label) > 140) {
                    $label = mb_substr($label, 0, 137) . '…';
                } elseif (strlen($label) > 140) {
                    $label = substr($label, 0, 137) . '…';
                }

                if (in_array($tag, ['div','section','article','main','aside'], true) && $label === $id) {
                    continue;
                }

                $items[] = ['label' => $label, 'id' => $id, 'tag' => $tag];
            }
        }
        libxml_clear_errors();
        libxml_use_internal_errors(false);
    }

    // —— Fallback regex (SIN thd_normalize_anchor)
    if (empty($items)) {
        if (preg_match_all('/\sid\s*=\s*([\'"])(.*?)\1/i', $content, $m)) {
            $seen = [];
            foreach ($m[2] as $rawId) {
                $id = thd_make_unique_anchor($rawId, $seen); // ← preserva mayúsculas y hace único
                $items[] = ['label' => $id, 'id' => $id, 'tag' => 'unknown'];
            }
        }
    }

    $json = !empty($items) ? wp_json_encode($items, JSON_UNESCAPED_UNICODE) : '';
    thd_nav_update_acf($post_id, $json);
    thd_nav_admin_notice($post_id, !empty($items) ? count($items) : 0);
}, 999);
add_action('save_post', function () { /* intencionalmente vacío */ }, 9999);

// ——— Helpers de anchors (preserva mayúsculas)
if (!function_exists('thd_sanitize_anchor_preserve_case')) {
    function thd_sanitize_anchor_preserve_case(string $raw): string
    {
        $id = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $id = trim($id);
        $id = preg_replace('/\s+/u', '-', $id);                // espacios -> guiones
        $id = preg_replace('/[^A-Za-z0-9\-\_:.]/u', '-', $id); // set permitido
        $id = preg_replace('/-+/', '-', $id);                  // colapsa guiones
        $id = trim($id, '-');
        return $id !== '' ? $id : 'section';
    }
}
if (!function_exists('thd_make_unique_anchor')) {
    function thd_make_unique_anchor(string $base, array &$taken): string
    {
        $base = thd_sanitize_anchor_preserve_case($base);
        $candidate = $base;
        $i = 2;
        while (isset($taken[strtolower($candidate)])) {
            $candidate = $base . '-' . $i++;
        }
        $taken[strtolower($candidate)] = true;
        return $candidate;
    }
}

/**
 * Actualiza el valor en ACF si está disponible; si no, usa post_meta.
 * Intenta resolver por key o por name y evita escrituras si no hay cambios.
 */
function thd_nav_update_acf($post_id, $value)
{
    $field_ident = defined('THD_NAV_ACF_FIELD') ? THD_NAV_ACF_FIELD : 'navigator';
    $value = (string)$value;

    // ——— Evita escrituras si no cambió
    $current = get_post_meta($post_id, $field_ident, true);
    if ((string)$current === $value) {
        return;
    }

    // ——— Si no existe ACF: post_meta directo
    if (!function_exists('update_field')) {
        update_post_meta($post_id, $field_ident, $value);
        return;
    }

    // ——— Si el identificador ya es field_key
    if (strpos($field_ident, 'field_') === 0) {
        update_field($field_ident, $value, $post_id);
        return;
    }

    // ——— Intentar resolver el field por name para este post (clonado/local)
    if (function_exists('acf_maybe_get_field')) {
        $field = acf_maybe_get_field($field_ident, $post_id);
        if (is_array($field) && !empty($field['key'])) {
            update_field($field['key'], $value, $post_id);
            return;
        }
    } elseif (function_exists('acf_get_field')) {
        // Fallback: menos fiable sin contexto de $post_id
        $field = acf_get_field($field_ident);
        if (is_array($field) && !empty($field['key'])) {
            update_field($field['key'], $value, $post_id);
            return;
        }
    }

    // ——— Último recurso: guardar como meta “plano”
    // Limpia key ACF antigua si existía para evitar incoherencias
    delete_post_meta($post_id, '_' . $field_ident);
    update_post_meta($post_id, $field_ident, $value);
}

/**
 * Guarda un aviso para mostrarse en el siguiente request (no imprime durante el save).
 * El aviso se asocia al usuario actual para evitar mostrarlo a otros.
 */
function thd_nav_admin_notice($post_id, $count)
{
    if (!is_admin()) {
        return;
    }
    $uid = get_current_user_id();
    if (!$uid) {
        return;
    }

    $payload = array(
        'post_id' => (int)$post_id,
        'count'   => (int)$count,
        'field'   => defined('THD_NAV_ACF_FIELD') ? THD_NAV_ACF_FIELD : 'navigator',
    );
    set_transient('_thd_nav_notice_' . $uid, $payload, 60);
}

// Hook global para renderizar el aviso si existe (en cualquier pantalla admin)
add_action('admin_notices', function () {
    $uid = get_current_user_id();
    if (!$uid) {
        return;
    }

    $payload = get_transient('_thd_nav_notice_' . $uid);
    if (!$payload || !is_array($payload)) {
        return;
    }

    delete_transient('_thd_nav_notice_' . $uid);

    $count = isset($payload['count']) ? (int)$payload['count'] : 0;
    $field = isset($payload['field']) ? $payload['field'] : 'navigator';

    echo '<div class="notice notice-success is-dismissible"><p><strong>Navegación:</strong> guardados '
         . esc_html((string)$count) . ' anchors en ACF (<code>' . esc_html($field) . '</code>).</p></div>';
});

/** Helper legacy (si lo sigues usando en otros lugares) */
if (!function_exists('thd_anchor_slug')) {
    function thd_anchor_slug($text, $max = 45)
    {
        $slug = sanitize_title(remove_accents((string)$text));
        if ($max > 0 && strlen($slug) > $max) {
            $slug = rtrim(substr($slug, 0, $max), '-_');
        }
        return $slug;
    }
}

/**
 * Shortcode del Navigator (permite mayúsculas en IDs y evita colisiones case-insensitive).
 * Uso: [post_navigator]
 */
add_shortcode('post_navigator', function () {
    $post_id = get_the_ID();
    if (!$post_id) {
        return '';
    }

    $selector = defined('THD_NAV_ACF_FIELD') ? THD_NAV_ACF_FIELD : 'navigator';
    $json = function_exists('get_field')
        ? get_field($selector, $post_id) // formateado/igual nos sirve: esperamos JSON string
        : get_post_meta($post_id, $selector, true);

    $items = json_decode((string)$json, true);
    if (!is_array($items) || empty($items)) {
        return '';
    }

    $base = get_permalink($post_id);
    $seen = [];

    ob_start(); ?>
<nav class="post-navigator" aria-label="<?php echo esc_attr__('Navegación del artículo', 'thd'); ?>">
    <ol>
        <?php foreach ($items as $it):
            $label = isset($it['label']) ? trim((string)$it['label']) : '';
            $idraw = isset($it['id']) ? trim((string)$it['id']) : '';
            if ($label === '' || $idraw === '') {
                continue;
            }

            // Acepta valores con '#...' y preserva mayúsculas
            if (strpos($idraw, '#') !== false) {
                $idraw = substr($idraw, strpos($idraw, '#') + 1);
            }
            $id = thd_sanitize_anchor_preserve_case(ltrim($idraw, '/'));

            // Evita colisiones ignorando el case
            if (isset($seen[strtolower($id)])) {
                $id = thd_make_unique_anchor($id, $seen);
            } else {
                $seen[strtolower($id)] = true;
            }

            $href = $base . '#' . $id; ?>
        <li><a href="<?php echo esc_url($href); ?>"><?php echo esc_html($label); ?></a></li>
        <?php endforeach; ?>
    </ol>
</nav>
<?php
    return ob_get_clean();
});
