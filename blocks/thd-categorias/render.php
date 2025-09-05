<?php
// Render dinámico: secciones por categoría con grid "ba-*"

$term_ids     = array_map('intval', $attributes['termIds'] ?? []);
$per_page     = max(1, intval($attributes['perPage'] ?? 6));
$posts_per    = max(1, intval($attributes['postsPerCat'] ?? 3));
$show_excerpt = !empty($attributes['showExcerpt']);

ob_start();

if (empty($term_ids)) {
    echo '<section class="thd-cats-wrap"><p class="thd-cat-msg">Selecciona categorías en los ajustes del bloque.</p></section>';
    echo ob_get_clean();
    return;
}

// ID único por instancia (para el query var de paginación)
$instance = isset($block->id) ? preg_replace('/[^a-z0-9]/i', '', $block->id) : 'thd';
$qvar     = 'thd_cats_' . $instance . '_page';
$current  = isset($_GET[$qvar]) ? max(1, intval($_GET[$qvar])) : 1;

$total = count($term_ids);
$pages = max(1, (int)ceil($total / $per_page));
if ($current > $pages) {
    $current = $pages;
}

$offset   = ($current - 1) * $per_page;
$page_ids = array_slice($term_ids, $offset, $per_page);

// Fallback de imagen si el post no tiene thumbnail
$fallback_img = apply_filters(
    'thd_block_categories_fallback_thumb',
    get_template_directory_uri() . '/img/cover-default.jpg'
);
?>
<section class="thd-cats-wrap" data-instance="<?php echo esc_attr($instance); ?>">
    <?php foreach ($page_ids as $tid):
        $term = get_term($tid, 'category');
        if (!$term || is_wp_error($term)) {
            continue;
        }

        // 3 posts (o lo que definas en postsPerCat) por categoría
        $q = new WP_Query([
            'post_type'           => 'post',
            'posts_per_page'      => $posts_per,
            'ignore_sticky_posts' => true,
            'tax_query'           => [[
                'taxonomy' => 'category',
                'field'    => 'term_id',
                'terms'    => $tid
            ]],
        ]);
        ?>
    <div class="thd-cat-section">
        <h2 class="thd-cat-title"><?php echo esc_html($term->name); ?></h2>

        <!-- Grid de tarjetas (tu estructura) -->
        <div class="ba-grid">
            <?php if ($q->have_posts()): while ($q->have_posts()): $q->the_post(); ?>
            <article class="ba-card">
                <a class="ba-card__media" href="<?php the_permalink(); ?>">
                    <?php
                    $fallback_img = get_template_directory_uri() . '/img/cover-default.jpg';

                    $thumb_id = get_post_thumbnail_id();
                    $valid = false;

                    if ($thumb_id && is_numeric($thumb_id) && 'attachment' === get_post_type($thumb_id)) {
                        // archivo físico
                        $file = get_attached_file((int)$thumb_id);
                        // host local
                        $url  = wp_get_attachment_image_url((int)$thumb_id, 'large');
                        $host_local = wp_parse_url(home_url(), PHP_URL_HOST);
                        $host_img   = $url ? wp_parse_url($url, PHP_URL_HOST) : '';

                        if ($file && file_exists($file) && $url && $host_img === $host_local) {
                            $valid = true;
                        }
                    }?>

                    <?php if ($valid): ?>
                        <?php echo wp_get_attachment_image((int)$thumb_id, 'large', false, [
                            'class'    => 'ba-card__img',
                            'loading'  => 'lazy',
                            'decoding' => 'async',
                        ]); ?>
                    <?php else: ?>
                        <img class="ba-card__img"
                            src="<?php echo esc_url($fallback_img); ?>"
                            alt="<?php echo esc_attr(get_the_title()); ?>"
                            loading="lazy"
                            decoding="async">
                    <?php endif; ?>
                </a>

                <div class="ba-card__body">
                    <h3 class="ba-card__title">
                        <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                    </h3>

                    <time class="ba-card__date" datetime="<?php echo esc_attr(get_the_date('c')); ?>">
                        <?php echo esc_html(get_the_date('d/m/Y')); ?>
                    </time>

                    <?php if ($show_excerpt): ?>
                    <p class="ba-card__excerpt">
                        <?php
                            $raw = get_the_excerpt();
                            if (!$raw) {
                                $raw = wp_strip_all_tags(get_the_content());
                            }
                            echo esc_html(wp_trim_words($raw, 24, '…'));
                        ?>
                    </p>
                    <?php endif; ?>

                    <a class="ba-card__cta" href="<?php the_permalink(); ?>">VER NOTA</a>
                </div>
            </article>
            <?php endwhile; wp_reset_postdata(); else: ?>
                <p class="ba-empty">No se encontraron notas.</p>
            <?php endif; ?>
        </div>

        <div class="thd-cat-cta">
            <a class="thd-btn" href="<?php echo esc_url(get_term_link($term)); ?>">EXPLORAR CATEGORÍA</a>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if ($pages > 1): ?>
    <nav class="thd-cats-nav" aria-label="Paginación de categorías">
        <?php
        for ($i = 1; $i <= $pages; $i++):
            $url = add_query_arg($qvar, $i);
            $active = $i === $current ? ' aria-current="page" class="is-active"' : '';
            echo '<a href="' . esc_url($url) . '"' . $active . '>' . $i . '</a>';
        endfor;
        ?>
    </nav>
    <?php endif; ?>
</section>
<?php
echo ob_get_clean();
