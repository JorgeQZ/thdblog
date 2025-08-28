<?php
add_action('rest_api_init', function () {
    register_rest_route('v1', '/posts', [
        'methods' => 'GET',
        'callback' => 'blog_get_posts',
        'permission_callback' => 'blog_rest_permission'
        // 'permission_callback' => '__return_true'
    ]);
});
function blog_get_posts($request)
{
    $page = max(1, (int) $request->get_param('page'));
    $per_page = max(100, min(100, (int) $request->get_param('per_page')));
    $args = [
        'post_type' => 'post',
        'posts_per_page' => $per_page,
        'paged' => $page,
        'post_status' => 'publish'
    ];
    $query = new WP_Query($args);
    $posts = [];
    foreach ($query->posts as $post) {
        $id = $post->ID;
        $author_id = $post->post_author;
        $get_meta = fn ($key) => get_post_meta($id, $key, true);
        // Helpers
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
            // Convierte null/false/'' en string 'null' para mantener tu contrato actual
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
            // fallback: coma o saltos de línea
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
            return array_values(array_unique(array_filter($ids)));
        };
        // ... dentro de tu loop:
        $author_id = $post->post_author;
        $id        = $post->ID;
        // Fallbacks de imágenes
        $thumb = get_the_post_thumbnail_url($id, 'medium');
        $main  = get_the_post_thumbnail_url($id, 'full');
        $ogimg = $meta($id, '_og_image') ?: $main ?: $thumb;
        $twimg = $meta($id, '_twitter_image') ?: $ogimg ?: $main ?: $thumb;
        // Gallery
        $gallery = $gallery_to_array($meta($id, '_gallery_images'));
        // Breadcrumb/pagination JSON
        $breadcrumb = $meta_json($id, '_structured_breadcrumb');
        $schema_ld  = $meta_json($id, '_schema_json');
        // Related (IDs)
        $related = $collect_related($id);
        // Navigator como array de <a>
        $nav_json = function_exists('get_field')
            ? get_field('field_navigator', $id)
            : get_post_meta($id, 'field_navigator', true);
        $items = json_decode((string)$nav_json, true);
        $navigator_array = [];
        if (is_array($items) && !empty($items)) {
            foreach ($items as $it) {
                $label = isset($it['label']) ? trim($it['label']) : '';
                $nid   = isset($it['id']) ? trim($it['id']) : '';
                if ($label && $nid) {
                    $navigator_array[] = '<a href="#' . esc_attr($nid) . '">' . esc_html($label) . '</a>';
                }
            }
        }
        $posts[] = [
            // Core
            'id'               => $id,
            'date'             => $post->post_date,
            'modified'         => $post->post_modified,
            'status'           => $post->post_status,
            'link'             => get_permalink($post),
            'postType'         => $meta($id, '_posttype') ?: 'Tutorial',
            'title'            => get_the_title($post),
            'author'           => get_the_author_meta('display_name', $author_id),
            'authorDescription' => $nulls(get_the_author_meta('description', $author_id)),
            'difficulty'       => $nulls($meta($id, '_difficulty')),
            'duration'         => estimate_post_duration($id),
            'thumbnail'        => $nulls($thumb),
            'mainImage'        => $nulls($main),
            'shortDescription' => $nulls($meta($id, '_short_description')),
            'video'            => $nulls($meta($id, '_video_url')), // si es múltiple, guarda JSON en _video_url
            'categories'       => wp_get_post_categories($id),
            'tags'             => array_map(function ($t) { return $t->name; }, wp_get_post_tags($id)),
            'navigator'        => !empty($navigator_array) ? $navigator_array : 'null',
            'relatedPosts'     => !empty($related) ? $related : 'null',
            'content'          => wp_kses_post($post->post_content),
            // SEO básicos
            'seoTitle'         => $nulls($meta($id, '_seo_title')),
            'metaDescripcion'  => $nulls($meta($id, '_meta_description')),
            'metaKeywords'     => $nulls($meta($id, '_meta_keywords')),
            'canonicalUrl'     => $nulls($meta($id, '_canonical_url')),
            'robotsDirectives' => $nulls($meta($id, '_robots_directives')),
            // Open Graph
            'ogTitle'          => $nulls($meta($id, '_og_title') ?: get_the_title($id)),
            'ogDescription'    => $nulls($meta($id, '_og_description') ?: $meta($id, '_short_description')),
            'ogImage'          => $nulls($ogimg),
            'ogType'           => $nulls($meta($id, '_og_type') ?: 'article'),
            'ogUrl'            => $nulls($meta($id, '_og_url') ?: get_permalink($id)),
            // Twitter Card
            'twitterCardType'  => $nulls($meta($id, '_twitter_card_type') ?: 'summary_large_image'),
            'twitterTitle'     => $nulls($meta($id, '_twitter_title') ?: get_the_title($id)),
            'twitterDescription' => $nulls($meta($id, '_twitter_description') ?: $meta($id, '_short_description')),
            'twitterImage'     => $nulls($twimg),
            'twitterCreator'   => $nulls($meta($id, '_twitter_creator')),
            // SEO Avanzados
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
        'page' => $page,
        'per_page' => $per_page,
        'total' => (int) $query->found_posts,
        'total_pages' => (int) $query->max_num_pages,
        'posts' => $posts
    ];
}
function get_related_posts($post_id, $limit = 4)
{
    $manual_related = json_decode(get_post_meta($post_id, '_related_posts', true));
    if (is_array($manual_related) && !empty($manual_related)) {
        return $manual_related;
    }
    $related_ids = [];
    $tags = wp_get_post_tags($post_id, ['fields' => 'ids']);
    $cats = wp_get_post_categories($post_id);
    $query = new WP_Query([
        'post__not_in' => [$post_id],
        'posts_per_page' => $limit,
        'ignore_sticky_posts' => true,
        'orderby' => 'date',
        'order' => 'DESC',
        'fields' => 'ids',
        'tax_query' => [
            'relation' => 'OR',
            [
                'taxonomy' => 'post_tag',
                'field' => 'term_id',
                'terms' => $tags,
            ],
            [
                'taxonomy' => 'category',
                'field' => 'term_id',
                'terms' => $cats,
            ]
        ]
    ]);
    if (!empty($query->posts)) {
        $related_ids = $query->posts;
    }
    return $related_ids;
}
function estimate_post_duration($post_id)
{
    $content = strip_tags(get_post_field('post_content', $post_id));
    $word_count = str_word_count($content);
    $minutes = ceil($word_count / 200); // promedio de 200 palabras por minuto
    return $minutes . ' minuto' . ($minutes === 1 ? '' : 's');
}
/**
 * Código para habilitar el endpoint REST API que devuelve las páginas del blog.
 */
// Add a REST API endpoint to get clean pages
add_action('rest_api_init', function () {
    register_rest_route('v1', '/pages', [
        'methods' => 'GET',
        'callback' => 'blog_get_clean_pages',
        //'permission_callback' => 'blog_rest_permission'
        'permission_callback' => '__return_true'
    ]);
});
function blog_get_clean_pages($request)
{
    $page = max(1, (int) $request->get_param('page'));
    $per_page = max(1, min(100, (int) $request->get_param('per_page')));
    $args = [
        'post_type' => 'page',
        'post_status' => 'publish',
        'paged' => $page,
        'posts_per_page' => $per_page
    ];
    $query = new WP_Query($args);
    $pages = [];
    foreach ($query->posts as $post) {
        $pages[] = [
            'id' => $post->ID,
            'title' => get_the_title($post),
            'excerpt' => wp_strip_all_tags(get_the_excerpt($post)),
            'content' => wp_kses_post(apply_filters('the_content', $post->post_content)),
            'slug' => $post->post_name,
            'link' => get_permalink($post),
            'date' => get_the_date('', $post),
            'author_name' => get_the_author_meta('display_name', $post->post_author)
        ];
    }
    return [
        'page' => (int) $page,
        'per_page' => (int) $per_page,
        'total' => (int) $query->found_posts,
        'total_pages' => (int) $query->max_num_pages,
        'pages' => $pages
    ];
}
/**
 * Navegación por anchors -> guarda JSON en ACF después de que ACF guarda.
 */
if (!defined('THD_NAV_ACF_FIELD')) {
    define('THD_NAV_ACF_FIELD', 'field_navigator');
}
/* ======================
 * GUARDADO (después de ACF)
 * ====================== */
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
        $dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $xp   = new DOMXPath($dom);
        $nodes = $xp->query('//*[@id and not(self::script or self::style or self::noscript or self::svg or self::path) and not(ancestor::header or ancestor::footer or ancestor::nav)]');
        $seen = [];
        foreach ($nodes as $node) {
            /** @var DOMElement $node */
            $id_raw = trim($node->getAttribute('id'));
            if ($id_raw === '') {
                continue;
            }
            $id = sanitize_title($id_raw);
            if ($id === '' || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
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
            if (in_array($tag, ['div','section','article','main','aside']) && $label === $id) {
                continue;
            }
            $items[] = ['label' => $label, 'id' => $id, 'tag' => $tag];
        }
    }
    if (empty($items)) {
        if (preg_match_all('/\sid\s*=\s*([\'"])(.*?)\1/i', $content, $m)) {
            $seen = [];
            foreach ($m[2] as $id_raw) {
                $id = sanitize_title($id_raw);
                if ($id === '' || isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
                $items[] = ['label' => $id, 'id' => $id, 'tag' => 'unknown'];
            }
        }
    }
    $json = !empty($items) ? wp_json_encode($items, JSON_UNESCAPED_UNICODE) : '';
    thd_nav_update_acf($post_id, $json);
    thd_nav_admin_notice($post_id, !empty($items) ? count($items) : 0);
}, 999);
add_action('save_post', function () { /* intencionalmente vacío */ }, 9999);
function thd_nav_update_acf($post_id, $value)
{
    $field_ident = THD_NAV_ACF_FIELD;
    if (!function_exists('update_field')) {
        update_post_meta($post_id, $field_ident, $value);
        return;
    }
    if (strpos($field_ident, 'field_') === 0) {
        update_field($field_ident, $value, $post_id);
        return;
    }
    if (function_exists('acf_get_field')) {
        $field = acf_get_field($field_ident);
        if (is_array($field) && !empty($field['key'])) {
            update_field($field['key'], $value, $post_id);
            return;
        }
    }
    delete_post_meta($post_id, '_'.$field_ident);
    update_post_meta($post_id, $field_ident, $value);
}
function thd_nav_admin_notice($post_id, $count)
{
    if (!is_admin() || !current_user_can('manage_options')) {
        return;
    }
    set_transient('_thd_nav_notice_'.$post_id, (int)$count, 60);
    add_action('admin_notices', function () use ($post_id) {
        if ($n = get_transient('_thd_nav_notice_'.$post_id)) {
            delete_transient('_thd_nav_notice_'.$post_id);
            echo '<div class="notice notice-success is-dismissible"><p><strong>Navegación:</strong> guardados '
                 .intval($n).' anchors en ACF (<code>'.esc_html(THD_NAV_ACF_FIELD).'</code>).</p></div>';
        }
    });
}
add_shortcode('post_navigator', function () {
    $post_id = get_the_ID();
    if (!$post_id) {
        return '';
    }
    $json = function_exists('get_field')
        ? get_field(THD_NAV_ACF_FIELD, $post_id)
        : get_post_meta($post_id, THD_NAV_ACF_FIELD, true);
    $items = json_decode((string)$json, true);
    if (!is_array($items) || empty($items)) {
        return '';
    }
    ob_start(); ?>
<nav aria-label="<?php echo esc_attr__('Navegación del artículo'); ?>">
    <ol>
        <?php foreach ($items as $it):
            $label = isset($it['label']) ? $it['label'] : '';
            $id    = isset($it['id']) ? $it['id'] : '';
            if (!$label || !$id) {
                continue;
            } ?>
        <li>
            <a href="#<?php echo esc_attr($id); ?>">
                <?php echo esc_html($label); ?>
            </a>
        </li>
        <?php endforeach; ?>
    </ol>
</nav>
<?php
    return ob_get_clean();
});
