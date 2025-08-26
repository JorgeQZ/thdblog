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
        $get_meta = fn($key) => get_post_meta($id, $key, true);

        $posts[] = [
            'id' => $id,
            'date' => $post->post_date,
            'modified' => $post->post_modified,
            'status' => $post->post_status,
            'link' => get_permalink($post),
            'postType' => $get_meta('_posttype') ?: 'Tutorial',
            'title' => get_the_title($post),
            'author' => get_the_author_meta('display_name', $author_id),
            'authorDescription' => get_the_author_meta('description', $author_id) ?: 'null',
            'steps' => get_post_meta($id, 'how-to__steps', true) ?: 'null',
            'difficulty' => $get_meta('_difficulty') ?: 'null',
            'duration' => estimate_post_duration($id),
            'thumbnail' => get_the_post_thumbnail_url($id, 'medium') ?: 'null',
            'mainImage' => get_the_post_thumbnail_url($id, 'full') ?: 'null',
            'shortDescription' => $get_meta('_short_description') ?: 'null',
            'video' => $get_meta('_video_url') ?: 'null',
            'categories' => wp_get_post_categories($id),
            'tags' => array_map(function ($tag) {return $tag->name;}, wp_get_post_tags($id)),
            'navigator' => generate_navigator_from_steps($id) ?: 'null',
            // 'relatedPosts' => get_related_posts($id) ?: 'null',
            'attributes' => json_decode($get_meta('_attributes')) ?: 'null',
            'content' => wp_kses_post($post->post_content),

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

function generate_navigator_from_steps($post_id){
      // Leer el JSON desde el campo ACF (o desde post_meta directo)
    $json = function_exists('get_field')
        ? get_field('field_navigator', $post_id)
        : get_post_meta($post_id, 'field_navigator', true);

    if (empty($json)) {
        return null;
    }

    $items = json_decode((string)$json, true);
    if (!is_array($items)) {
        return null;
    }

    $navigator = [];
    foreach ($items as $it) {
        if (!empty($it['id']) && !empty($it['label'])) {
            $title = esc_html($it['label']);
            $href  = '#' . $it['id'];
            $navigator[] = '<a href="' . esc_attr($href) . '">' . ($i + 1) . '. ' . $title . '</a>';
        }
    }

    return !empty($navigator) ? $navigator : null;
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
 *
 */

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
{// Function to get clean pages
    // This function retrieves a paginated list of published pages.
    // It accepts 'page' and 'per_page' parameters to control pagination.
    $page = max(1, (int) $request->get_param('page'));
    $per_page = max(1, min(100, (int) $request->get_param('per_page')));

    // Build query args
    $args = [
        'post_type' => 'page', // Changed from 'post' to 'page'
        'post_status' => 'publish',// Only published pages
        'paged' => $page,// Current page number
        'posts_per_page' => $per_page // Number of pages per page
    ];

    $query = new WP_Query($args);
    $pages = [];

    // Loop through the pages and prepare the response data
    foreach ($query->posts as $post) {
        $pages[] = [
            'id' => $post->ID, // Page ID
            'title' => get_the_title($post), // Page title
            'excerpt' => wp_strip_all_tags(get_the_excerpt($post)), // Page excerpt
            'content' => wp_kses_post(apply_filters('the_content', $post->post_content)), // Sanitized page content
            'slug' => $post->post_name, // Page slug
            'link' => get_permalink($post), // Page permalink
            'date' => get_the_date('', $post), // Page publication date
            'author_name' => get_the_author_meta('display_name', $post->post_author) // Author's display name
        ];
    }

    // Prepare the response with pagination information
    return [
        'page' => (int) $page,
        'per_page' => (int) $per_page,
        'total' => (int) $query->found_posts,
        'total_pages' => (int) $query->max_num_pages,
        'pages' => $pages
    ];
}


/**
 *
 */

if (!defined('THD_NAV_ACF_FIELD')) {
    define('THD_NAV_ACF_FIELD', 'field_navigator'); // NAME por defecto
}
add_action('save_post', function( $post_id, $post, $update ){
    if ( wp_is_post_autosave($post_id) || wp_is_post_revision($post_id) ) return;
    if ( ! current_user_can('edit_post', $post_id) ) return;
    if ( empty($post) || empty($post->post_content) ) {
        thd_nav_update_acf($post_id, '');
        return;
    }

    $content = $post->post_content;
    $items   = [];

    // —— INTENTO 1: DOMDocument (ideal)
    if (class_exists('DOMDocument')) {
        libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>'.$content.'</body></html>';
        $dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $xp  = new DOMXPath($dom);
        $nodes = $xp->query('//*[@id and not(self::script or self::style or self::noscript or self::svg or self::path) and not(ancestor::header or ancestor::footer or ancestor::nav)]');

        $seen = [];
        foreach ($nodes as $node) {
            /** @var DOMElement $node */
            $id_raw = trim($node->getAttribute('id'));
            if ($id_raw === '') continue;

            $id = sanitize_title($id_raw);
            if ($id === '' || isset($seen[$id])) continue;
            $seen[$id] = true;

            $tag = strtolower($node->tagName);

            // Label “inteligente”
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

            // Evita contenedores genéricos vacíos
            if (in_array($tag, ['div','section','article','main','aside']) && $label === $id) {
                continue;
            }

            $items[] = ['label'=>$label,'id'=>$id,'tag'=>$tag];
        }
    }

    // —— INTENTO 2: Fallback por REGEX (si no hay DOM o no encontró nada)
    if (empty($items)) {
        // Toma todos los id="..."
        if (preg_match_all('/\sid\s*=\s*([\'"])(.*?)\1/i', $content, $m)) {
            $seen = [];
            foreach ($m[2] as $id_raw) {
                $id = sanitize_title($id_raw);
                if ($id === '' || isset($seen[$id])) continue;
                $seen[$id] = true;
                $items[] = ['label'=>$id, 'id'=>$id, 'tag'=>'unknown'];
            }
        }
    }

    // Guarda JSON (o vacío)
    $json = !empty($items) ? wp_json_encode($items, JSON_UNESCAPED_UNICODE) : '';
    thd_nav_update_acf($post_id, $json);

    // Aviso en admin para validar rápidamente
    if (is_admin() && current_user_can('manage_options')) {
        set_transient('_thd_nav_notice_'.$post_id, (!empty($items) ? count($items) : 0), 60);
        add_action('admin_notices', function() use ($post_id){
            if ($n = get_transient('_thd_nav_notice_'.$post_id)) {
                delete_transient('_thd_nav_notice_'.$post_id);
                echo '<div class="notice notice-success is-dismissible"><p><strong>Navegación:</strong> guardados '.$n.' anchors en ACF (<code>'.esc_html(THD_NAV_ACF_FIELD).'</code>).</p></div>';
            }
        });
    }
}, 20, 3);

/**
 * Actualiza el campo ACF por name o key; si no hay ACF, guarda meta normal.
 */
function thd_nav_update_acf($post_id, $value){
    // ACF presente
    if (function_exists('update_field')) {
        // Si THD_NAV_ACF_FIELD es una KEY (comienza con "field_"), ACF la reconoce siempre.
        // Si es NAME, requiere que el campo exista y tenga ese name.
        @update_field(THD_NAV_ACF_FIELD, $value, $post_id);
    }
    // Espejo en meta plano para cualquier caso
    update_post_meta($post_id, THD_NAV_ACF_FIELD, $value);
}

add_shortcode('post_navigator', function(){
    $post_id = get_the_ID();
    if (!$post_id) return '';

    // Leer de ACF o meta
    $json = function_exists('get_field')
        ? get_field('field_navigator', $post_id)
        : get_post_meta($post_id, 'field_navigator', true);

    $items = json_decode((string)$json, true);
    if (!is_array($items) || empty($items)) return '';

    ob_start(); ?>
<nav aria-label="<?php echo esc_attr__('Navegación del artículo'); ?>">
    <ol>
        <?php foreach ($items as $it):
            $label = isset($it['label']) ? $it['label'] : '';
            $id    = isset($it['id'])    ? $it['id']    : '';
            if (!$label || !$id) continue; ?>
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