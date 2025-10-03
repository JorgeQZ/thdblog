<?php
add_action('rest_api_init', function () {
    register_rest_route('v1', '/posts', [
        'methods'             => 'GET',
        'callback'            => 'blog_get_posts',
         'permission_callback' => 'blog_rest_permission'

        // 'permission_callback' => '__return_true'
    ]);
});

// function blog_rest_permission($request) {
//     return ($request instanceof WP_REST_Request) && $request->get_method() === 'GET';
// }

function blog_get_posts($request)
{
    // Paginación segura
    $page     = max(1, (int) $request->get_param('page'));
    $per_req  = (int) $request->get_param('per_page');
    $per_page = max(1, min(100, $per_req > 0 ? $per_req : 12));

    // Query base
    $args = [
        'post_type'           => 'post',
        'posts_per_page'      => $per_page,
        'paged'               => $page,
        'post_status'         => 'publish',
        'ignore_sticky_posts' => true,
    ];
    if ($s = (string) $request->get_param('s')) {
        $args['s']   = sanitize_text_field($s);
    }
    if ($cat = (int) $request->get_param('cat')) {
        $args['cat'] = $cat;
    }
    if ($tag = (string) $request->get_param('tag')) {
        $args['tag'] = sanitize_title($tag);
    }

    $q = new WP_Query($args);

    // — Helpers —
    $get_meta = function (int $post_id, string $key) {
        $v = get_post_meta($post_id, $key, true);
        return ($v === '' || $v === null) ? null : $v;
    };
    $get_meta_json = function (int $post_id, string $key) use ($get_meta) {
        $raw = $get_meta($post_id, $key);
        if (!$raw) {
            return null;
        }
        if (is_array($raw)) {
            return $raw;
        }
        $d = json_decode($raw, true);
        return is_array($d) ? $d : null;
    };
    $nulls = function ($v) {
        // Contrato vigente: null/false/'' => 'null'
        return ($v === null || $v === false || $v === '') ? 'null' : $v;
    };
    $to_array = function ($raw) {
        if (!$raw) {
            return [];
        }
        if (is_array($raw)) {
            return array_values(array_filter($raw));
        }
        $j = json_decode($raw, true);
        if (is_array($j)) {
            return array_values(array_filter($j));
        }
        $parts = preg_split('/[\r\n,]+/u', (string) $raw);
        return array_values(array_filter(array_map('trim', $parts)));
    };
    $slugify = function (string $txt) {
        $txt = trim(mb_strtolower($txt, 'UTF-8'));
        $txt = strtr($txt, ['á' => 'a','é' => 'e','í' => 'i','ó' => 'o','ú' => 'u','ñ' => 'n']);
        return sanitize_title($txt);
    };
    $normalize_label = function ($txt) {
        if ($txt === null) {
            return null;
        }
        $txt = trim((string) $txt);
        $txt = mb_strtolower($txt, 'UTF-8');
        $txt = strtr($txt, ['á' => 'a','é' => 'e','í' => 'i','ó' => 'o','ú' => 'u','Á' => 'a','É' => 'e','Í' => 'i','Ó' => 'o','Ú' => 'u','Ñ' => 'ñ']);
        return preg_replace('/\s+/u', ' ', $txt);
    };
    // Todos los términos como slugs únicos
    $all_term_slugs = function ($maybe_terms) use ($slugify): array {
        $slugs = [];
        $push = function ($labelOrSlug) use (&$slugs, $slugify) {
            if ($labelOrSlug === null || $labelOrSlug === '') {
                return;
            }
            $slug = $slugify((string)$labelOrSlug);
            if ($slug !== '' && !in_array($slug, $slugs, true)) {
                $slugs[] = $slug;
            }
        };
        if (!$maybe_terms) {
            return $slugs;
        }

        if ($maybe_terms instanceof WP_Term) {
            $push($maybe_terms->slug ?: $maybe_terms->name);
            return $slugs;
        }
        if (is_array($maybe_terms)) {
            foreach ($maybe_terms as $item) {
                if ($item instanceof WP_Term) {
                    $push($item->slug ?: $item->name);
                } elseif (is_numeric($item)) {
                    $t = get_term((int)$item);
                    if ($t && !is_wp_error($t)) {
                        $push($t->slug ?: $t->name);
                    }
                } elseif (is_string($item)) {
                    if (strpos($item, ',') !== false) {
                        foreach (array_filter(array_map('trim', explode(',', $item))) as $p) {
                            $push($p);
                        }
                    } else {
                        $push($item);
                    }
                }
            }
            return $slugs;
        }
        if (is_string($maybe_terms)) {
            $json = json_decode($maybe_terms, true);
            if (is_array($json)) {
                foreach ($json as $p) {
                    $push(is_array($p) && isset($p['name']) ? $p['name'] : $p);
                }
            } else {
                foreach (array_filter(array_map('trim', explode(',', $maybe_terms))) as $p) {
                    $push($p);
                }
            }
        }
        return $slugs;
    };
    // Slug normalizado para difficulty/posttype
    $difficulty_slug = function ($raw) use ($slugify) {
        if ($raw === null || $raw === '') {
            return null;
        }
        $s = mb_strtolower(trim((string)$raw), 'UTF-8');
        $s = strtr($s, ['á' => 'a','é' => 'e','í' => 'i','ó' => 'o','ú' => 'u']);
        $map = [
            'facil' => 'facil','fácil' => 'facil','easy' => 'facil','1' => 'facil',
            'media' => 'media','intermedia' => 'media','medio' => 'media','medium' => 'media','2' => 'media',
            'dificil' => 'dificil','difícil' => 'dificil','hard' => 'dificil','3' => 'dificil',
        ];
        return $map[$s] ?? $slugify($s);
    };
    $posttype_slug = function ($raw) use ($slugify) {
        if ($raw === null || $raw === '') {
            return null;
        }
        $s = mb_strtolower(trim((string)$raw), 'UTF-8');
        $s = strtr($s, ['á' => 'a','é' => 'e','í' => 'i','ó' => 'o','ú' => 'u']);
        $map = ['tutorial' => 'tutorial','guia' => 'guia','guía' => 'guia','idea' => 'idea','proyecto' => 'proyecto','nota' => 'nota'];
        return $map[$s] ?? $slugify($s);
    };
    $content_plain = function ($raw) {
        $t = wp_strip_all_tags((string) $raw, true);
        $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return preg_replace('/\s+/u', ' ', trim($t));
    };

    $default_cover = apply_filters('thd_api_fallback_thumb', get_template_directory_uri() . '/img/cover-default.jpg');

    $out = [];
    foreach ($q->posts as $p) {
        $id        = (int) $p->ID;
        $author_id = (int) $p->post_author;

        // Imágenes con fallback
        $thumb = get_the_post_thumbnail_url($id, 'medium') ?: $default_cover;
        $main  = get_the_post_thumbnail_url($id, 'full') ?: $thumb;

        // Navigator (si existe)
        $navigator = [];
        $nav_raw = function_exists('get_field') ? get_field(THD_NAV_ACF_FIELD, $id) : get_post_meta($id, THD_NAV_ACF_FIELD, true);
        $nav_items = json_decode((string) $nav_raw, true);
        if (is_array($nav_items) && $nav_items) {
            $base = get_permalink($id);
            foreach ($nav_items as $it) {
                $label = isset($it['label']) ? trim((string) $it['label']) : '';
                $nid   = isset($it['id']) ? trim((string) $it['id']) : '';
                if ($label && $nid) {
                    if (strpos($nid, '#') !== false) {
                        $nid = substr($nid, strpos($nid, '#') + 1);
                    }
                    $navigator[] = '<a href="' . esc_url($base . '#' . sanitize_title($nid)) . '">' . esc_html($label) . '</a>';
                }
            }
        }

        // Contenidos
        $raw    = get_post_field('post_content', $id);
        $render = apply_filters('the_content', $raw);
        $plain  = $content_plain($raw);

        // —— ATTRIBUTES con claves exactas pedidas ——
        // _espacio_del_hogar: lista de slugs (todos)
        $espacio_raw = function_exists('get_field') ? get_field('espacio_del_hogar', $id) : null;
        if ($espacio_raw === null || $espacio_raw === '') {
            $meta_esp = $get_meta($id, '_espacio_del_hogar');
            if (is_string($meta_esp)) {
                $maybe = json_decode($meta_esp, true);
                $espacio_raw = is_array($maybe) ? $maybe : $meta_esp;
            } else {
                $espacio_raw = $meta_esp;
            }
        }
        $espacios_slugs = $all_term_slugs($espacio_raw); // ej.: ["sala","cocina","terraza"]

        // _duration: texto del campo (no en minutos), normalizado a minúsculas sin acentos
        $duration_raw = function_exists('get_field') ? get_field('duration', $id) : null;
        if ($duration_raw === null || $duration_raw === '') {
            $duration_raw = $get_meta($id, '_duration');
        }
        $duration_txt = $normalize_label($duration_raw);  // ej.: "1 dia o menos"

        // _difficulty y _posttype: slugs normalizados
        $difficulty_raw = $get_meta($id, '_difficulty');
        $posttype_raw   = $get_meta($id, '_posttype');

        $difficulty_norm = $difficulty_slug($difficulty_raw); // "facil" | "media" | "dificil" | otro slug
        $posttype_norm   = $posttype_slug($posttype_raw);     // "tutorial"|"guia"|...

        // SEO/OG
        $ogimg = $get_meta($id, '_og_image') ?: $main ?: $thumb;
        $twimg = $get_meta($id, '_twitter_image') ?: $ogimg ?: $main ?: $thumb;

        $out[] = [
            // Core
            'id'                => $id,
            'date'              => $p->post_date,
            'modified'          => $p->post_modified,
            'status'            => $p->post_status,
            'link'              => get_permalink($id),
            'postType'          => $posttype_raw ?: 'Tutorial', // compat
            'title'             => get_the_title($id),
            'author'            => get_the_author_meta('display_name', $author_id),
            'authorDescription' => $nulls(get_the_author_meta('description', $author_id)),

            // Compatibilidad con esquema previo
            'difficulty'        => $nulls($difficulty_raw),
            'duration'          => $nulls($duration_raw),

            // === Nodo attributes con claves exactas ===
            'attributes'        => [
                'espacio_del_hogar' => !empty($espacios_slugs) ? $espacios_slugs : 'null',
                'duration'          => $nulls($duration_txt),
                'difficulty'        => $nulls($difficulty_norm),
                'posttype'          => $nulls($posttype_norm),
            ],

            // Media
            'thumbnail'         => $nulls($thumb),
            'mainImage'         => $nulls($main),

            // Descripción corta
            'shortDescription'  => $nulls($get_meta($id, '_short_description')),

            // Tax y media
            'video'             => $nulls($get_meta($id, '_video_url')),
            'categories'        => wp_get_post_categories($id),
            'tags'              => array_map(function ($t) { return $t->name; }, wp_get_post_tags($id)),

            // Navegador y relacionados
            'navigator'         => $navigator ?: 'null',
            'relatedPosts'      => (function ($pid) {
                $ids = [];
                if (function_exists('get_field')) {
                    $gv = get_field('guias_de_venta', $pid);
                    if (!empty($gv['related_posts_guias'])) {
                        $ids = array_merge($ids, (array) $gv['related_posts_guias']);
                    }
                    $tt = get_field('related_posts_tutoriales', $pid);
                    if (!empty($tt['related_posts_tutoriales'])) {
                        $ids = array_merge($ids, (array) $tt['related_posts_tutoriales']);
                    }
                }
                $ids = array_values(array_unique(array_map('intval', $ids)));
                return $ids ?: 'null';
            })($id),

            // Contenido
            'content'           => wp_kses_post($raw),
            'contentRendered'   => wp_kses_post($render),
            'contentPlain'      => $nulls($content_plain($raw)),

            // SEO básicos
            'seoTitle'          => $nulls($get_meta($id, '_seo_title')),
            'metaDescripcion'   => $nulls($get_meta($id, '_meta_description')),
            'metaKeywords'      => $nulls($get_meta($id, '_meta_keywords')),
            'canonicalUrl'      => $nulls($get_meta($id, '_canonical_url')),
            'robotsDirectives'  => $nulls($get_meta($id, '_robots_directives')),

            // Open Graph
            'ogTitle'           => $nulls($get_meta($id, '_og_title') ?: get_the_title($id)),
            'ogDescription'     => $nulls($get_meta($id, '_og_description') ?: $get_meta($id, '_short_description')),
            'ogImage'           => $nulls($ogimg),
            'ogType'            => $nulls($get_meta($id, '_og_type') ?: 'article'),
            'ogUrl'             => $nulls($get_meta($id, '_og_url') ?: get_permalink($id)),

            // Twitter Card
            'twitterCardType'   => $nulls($get_meta($id, '_twitter_card_type') ?: 'summary_large_image'),
            'twitterTitle'      => $nulls($get_meta($id, '_twitter_title') ?: get_the_title($id)),
            'twitterDescription' => $nulls($get_meta($id, '_twitter_description') ?: $get_meta($id, '_short_description')),
            'twitterImage'      => $nulls($twimg),
            'twitterCreator'    => $nulls($get_meta($id, '_twitter_creator')),

            // Extras
            'schemaType'           => $nulls($get_meta($id, '_schema_type') ?: 'Article'),
            'schemaJson'           => $get_meta_json($id, '_schema_json') ?: 'null',
            'structuredBreadcrumb' => $get_meta_json($id, '_structured_breadcrumb') ?: 'null',
            'galleryImages'        => $to_array($get_meta($id, '_gallery_images')),
            'pagination'           => [
                'prev' => $nulls($get_meta($id, '_pagination_prev')),
                'next' => $nulls($get_meta($id, '_pagination_next')),
            ],
        ];
    }

    return [
        'page'        => $page,
        'per_page'    => $per_page,
        'total'       => (int) $q->found_posts,
        'total_pages' => (int) $q->max_num_pages,
        'posts'       => $out,
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
