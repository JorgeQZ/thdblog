<?php
get_header();
?>
<div class="container">
<?php if (have_posts()) : while (have_posts()) : the_post();
    $id = get_the_ID();
?>
    <?php
    // — Featured image con fallback robusto
    $fallback_img = get_template_directory_uri() . '/img/cover-default.jpg';
    $thumb_id     = get_post_thumbnail_id($id);
    $img_html     = '';

    if ($thumb_id && get_post_type($thumb_id) === 'attachment') {
        $file = get_attached_file((int)$thumb_id);
        if ($file && file_exists($file)) {
            $alt_meta = get_post_meta((int)$thumb_id, '_wp_attachment_image_alt', true);
            $alt      = ($alt_meta !== '') ? $alt_meta : get_the_title($id);
            $img_html = wp_get_attachment_image(
                (int)$thumb_id,
                'large',
                false,
                [
                    'class'    => 'post-featured-image__img',
                    'loading'  => 'lazy',
                    'decoding' => 'async',
                    'alt'      => $alt,
                ]
            );
        }
    }
    if ($img_html === '') {
        $img_html = sprintf(
            '<img class="post-featured-image__img" src="%s" alt="%s" loading="lazy" decoding="async">',
            esc_url($fallback_img),
            esc_attr(get_the_title($id))
        );
    }
    ?>
    <div class="post-featured-image">
        <?php echo $img_html; ?>
    </div>

    <?php
    // — Breadcrumbs
    echo '<nav class="breadcrumbs" aria-label="Breadcrumbs">';
    echo '<a href="' . esc_url(home_url('/')) . '">Inicio</a> &raquo; ';

    $cats = get_the_category($id);
    if (!empty($cats)) {
        $deepest   = null;
        $max_depth = -1;
        foreach ($cats as $c) {
            $depth = count(get_ancestors($c->term_id, 'category'));
            if ($depth > $max_depth) {
                $max_depth = $depth;
                $deepest   = $c;
            }
        }
        if ($deepest) {
            if ($deepest->parent) {
                $parent = get_term($deepest->parent, 'category');
                if ($parent && !is_wp_error($parent)) {
                    echo '<a href="' . esc_url(get_category_link($parent->term_id)) . '">'
                       . esc_html($parent->name) . '</a> &raquo; ';
                }
            }
            echo '<a href="' . esc_url(get_category_link($deepest->term_id)) . '">'
               . esc_html($deepest->name) . '</a> &raquo; ';
        }
    }
    echo '<span>' . esc_html(get_the_title($id)) . '</span>';
    echo '</nav>';
    ?>

    <div class="nota-title"><?php the_title(); ?></div>

    <div class="grid">
        <div class="column">
            <div class="autor">Por: <?php the_author_posts_link(); ?></div>
            <div class="fecha">
                Publicado
                <time datetime="<?php echo esc_attr(get_the_date('c')); ?>">
                    <?php echo esc_html(get_the_date('d/m/Y')); ?>
                </time>
            </div>
        </div>

        <?php
        // — Duración / Dificultad (solo si existen)
        $duracion_raw   = function_exists('get_field') ? get_field('_duration', $id)   : get_post_meta($id, '_duration', true);
        $dificultad_raw = function_exists('get_field') ? get_field('_difficulty', $id) : get_post_meta($id, '_difficulty', true);
        $duracion       = is_array($duracion_raw) ? '' : trim((string)$duracion_raw);
        $dificultad     = is_array($dificultad_raw) ? '' : trim((string)$dificultad_raw);

        if ($duracion !== '' || $dificultad !== ''): ?>
            <div class="column">
                <?php if ($duracion !== ''): ?>
                    <div class="duracion">Duración: <b><?php echo esc_html($duracion); ?></b></div>
                <?php endif; ?>
                <?php if ($dificultad !== ''): ?>
                    <div class="dificultad">Dificultad: <b><?php echo esc_html($dificultad); ?></b></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="nota">
        <div class="contenido">
            <?php the_content(); ?>
        </div>

        <div class="aside">
            <?php
            // — Excerpt lateral (solo si hay tags o short description)
            $tags      = get_the_terms($id, 'post_tag');
            $has_tags  = is_array($tags) && !empty($tags);

            $short_raw = function_exists('get_field') ? get_field('_short_description', $id) : get_post_meta($id, '_short_description', true);
            $short     = is_string($short_raw) ? trim($short_raw) : '';
            $has_short = ($short !== '');

            if ($has_tags || $has_short): ?>
                <div class="excerpt">
                    <?php if ($has_tags): ?>
                        <div class="tags">
                            <?php
                            $max_tags = 12; $i = 0;
                            foreach ($tags as $tag) {
                                if ($i++ >= $max_tags) break;
                                $url = get_term_link($tag);
                                if (is_wp_error($url)) continue; ?>
                                <a class="tag" href="<?php echo esc_url($url); ?>">
                                    <?php echo esc_html($tag->name); ?>
                                </a>
                            <?php } ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($has_short): ?>
                        <div class="descipcion descripcion">
                            <?php echo wp_kses_post($short); ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php
            // — Post Navigator (shortcode)
            $output = do_shortcode('[post_navigator]');
            if (trim($output) !== ''): ?>
                <div class="navegacion">
                    <p class="aside-title">Navegación</p>
                    <?php echo $output; ?>
                </div>
            <?php else:
                echo '<!-- [post_navigator] no devolvió contenido -->';
            endif;
            ?>

            <div class="categorias">
                <p class="aside-title">Categorias relacionadas</p>
                <?php
                $taxonomy      = 'category';
                $limit         = 4;
                $posts_to_scan = 200;

                $post_type = get_post_type($id) ?: 'post';
                $terms     = get_the_terms($id, $taxonomy);

                if (!is_wp_error($terms) && !empty($terms)) {

                    $seed_ids = wp_list_pluck($terms, 'term_id');

                    // — Co-ocurrencia
                    $q = new WP_Query([
                        'post_type'           => $post_type,
                        'posts_per_page'      => $posts_to_scan,
                        'post__not_in'        => [$id],
                        'fields'              => 'ids',
                        'tax_query'           => [[
                            'taxonomy' => $taxonomy,
                            'field'    => 'term_id',
                            'terms'    => $seed_ids,
                            'operator' => 'IN',
                        ]],
                        'no_found_rows'       => true,
                        'ignore_sticky_posts' => true,
                    ]);

                    $counts = [];
                    if ($q->have_posts()) {
                        foreach ($q->posts as $pid2) {
                            $t2 = get_the_terms($pid2, $taxonomy);
                            if (is_array($t2)) {
                                foreach ($t2 as $t) {
                                    if (in_array($t->term_id, $seed_ids, true)) continue;
                                    $counts[$t->term_id] = ($counts[$t->term_id] ?? 0) + 1;
                                }
                            }
                        }
                    }
                    wp_reset_postdata();

                    arsort($counts);
                    $related_ids = array_slice(array_keys($counts), 0, $limit);

                    $related_terms = [];
                    if ($related_ids) {
                        $related_terms = get_terms([
                            'taxonomy'   => $taxonomy,
                            'include'    => $related_ids,
                            'hide_empty' => false,
                        ]);
                        if (is_wp_error($related_terms)) {
                            $related_terms = [];
                        }
                    }

                    if (empty($related_terms)) {
                        $pool = [];
                        foreach ($terms as $t) {
                            $siblings = get_terms([
                                'taxonomy'   => $taxonomy,
                                'hide_empty' => true,
                                'parent'     => (int)$t->parent,
                                'exclude'    => $seed_ids,
                                'number'     => 20,
                            ]);
                            if (!is_wp_error($siblings) && $siblings) {
                                foreach ($siblings as $s) {
                                    $pool[$s->term_id] = $s; // dedupe
                                }
                            }
                        }
                        if (!empty($pool)) {
                            $related_terms = array_slice(array_values($pool), 0, $limit);
                        }
                    }

                    if (!empty($related_terms)) : ?>
                        <div class="grid">
                            <?php foreach ($related_terms as $term) :
                                $img_url = '';

                                if (function_exists('get_field')) {
                                    $img_field = get_field('imagen', 'term_' . $term->term_id);
                                    if ($img_field) {
                                        if (is_array($img_field) && !empty($img_field['url'])) {
                                            $img_url = $img_field['url'];
                                        } elseif (is_numeric($img_field)) {
                                            $img_url = wp_get_attachment_image_url((int)$img_field, 'large') ?: '';
                                        } elseif (is_string($img_field)) {
                                            $img_url = $img_field;
                                        }
                                    }
                                    if (!$img_url) {
                                        $img_url = (string)get_field('imagen_como_url', 'term_' . $term->term_id);
                                    }
                                }

                                if (!$img_url) {
                                    $img_url = get_template_directory_uri() . '/img/cover-default.jpg';
                                }

                                $term_link = get_term_link($term);
                                if (is_wp_error($term_link)) {
                                    $term_link = '#';
                                }
                                ?>
                                <a class="item"
                                   href="<?php echo esc_url($term_link); ?>"
                                   style="background-image:url('<?php echo esc_url($img_url); ?>');"
                                   aria-label="<?php echo esc_attr($term->name); ?>">
                                    <span class="item__name"><?php echo esc_html($term->name); ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php } ?>
            </div> <!-- /.categorias -->
        </div> <!-- /.aside -->
    </div> <!-- /.nota -->

    <div class="carruseles">
        <?php
        // — Contenido Relacionado (ACF)
        $contenido_relacionado = function_exists('get_field') ? get_field('contenido_relacionado', $id) : [];
        if (isset($contenido_relacionado['mostrar_cr'])
            && $contenido_relacionado['mostrar_cr'] === 'Si'
            && !empty($contenido_relacionado['related_contenido_relacionado'])):
        ?>
        <section class="rc" data-rc>
            <div class="rc__head">
                <h3 class="rc__title"><span>Contenido</span> <span class="rc__title--accent">RELACIONADO</span></h3>
                <div class="rc__controls">
                    <button class="rc__btn" data-rc-prev aria-label="Anterior">‹</button>
                    <button class="rc__btn" data-rc-next aria-label="Siguiente">›</button>
                </div>
            </div>
            <div class="rc__viewport rc__fade">
                <ul class="rc__track">
                <?php
                $fallback_img_related = apply_filters(
                    'thd_related_fallback_thumb',
                    get_template_directory_uri() . '/img/cover-default.jpg'
                );

                foreach ((array)$contenido_relacionado['related_contenido_relacionado'] as $contenido) :
                    $pid   = (int)$contenido;
                    if (!$pid) continue;

                    $link  = get_permalink($pid);
                    $title = get_the_title($pid) ?: '';

                    // Imagen destacada válida o fallback
                    $img_html = '';
                    $thumb_id = get_post_thumbnail_id($pid);

                    if ($thumb_id && get_post_type($thumb_id) === 'attachment') {
                        $file = get_attached_file((int)$thumb_id);
                        if ($file && file_exists($file)) {
                            $alt_meta = get_post_meta((int)$thumb_id, '_wp_attachment_image_alt', true);
                            $alt      = ($alt_meta !== '') ? $alt_meta : $title;

                            $img_html = wp_get_attachment_image(
                                (int)$thumb_id,
                                'medium_large',
                                false,
                                [
                                    'class'    => 'rc-card__img',
                                    'loading'  => 'lazy',
                                    'decoding' => 'async',
                                    'alt'      => $alt,
                                ]
                            );
                        }
                    }
                    if ($img_html === '') {
                        $img_html = sprintf(
                            '<img class="rc-card__img" src="%s" alt="%s" loading="lazy" decoding="async">',
                            esc_url($fallback_img_related),
                            esc_attr($title)
                        );
                    }

                    // Excerpt del post relacionado
                    $excerpt = get_the_excerpt($pid);
                    if (!$excerpt) {
                        $excerpt = wp_trim_words(wp_strip_all_tags(get_post_field('post_content', $pid)), 10, '…');
                    }

                    $tag = 'Contenido Relacionado';
                    ?>
                    <li class="rc__slide">
                        <article class="rc-card">
                            <a class="rc-card__media" href="<?php echo esc_url($link); ?>">
                                <?php echo $img_html; ?>
                            </a>
                            <div class="rc-card__body">
                                <div class="rc-card__tag"><?php echo esc_html($tag); ?></div>
                                <h4 class="rc-card__title">
                                    <a href="<?php echo esc_url($link); ?>"><?php echo esc_html($title); ?></a>
                                </h4>
                                <p class="rc-card__excerpt"><?php echo esc_html($excerpt); ?></p>
                            </div>
                        </article>
                    </li>
                <?php endforeach; ?>
                </ul>
            </div>
        </section>
        <?php endif; ?>
    </div> <!-- /.carruseles -->

<?php endwhile; endif; ?>
</div>
<?php get_footer(); ?>
