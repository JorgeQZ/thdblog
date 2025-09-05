<?php
/**
 * Render callback para el bloque thd/rc-destacados (solo Contenido Destacado)
 */
if (!function_exists('thd_render_rc_destacados_block')) {
    function thd_render_rc_destacados_block($attributes = [], $content = '', $block = null)
    {
        $postsPerPage = isset($attributes['postsPerPage']) ? (int)$attributes['postsPerPage'] : 8;
        if (!taxonomy_exists('contenido_destacado')) return '';

        $q = new WP_Query([
            'post_type'           => 'post',
            'posts_per_page'      => $postsPerPage,
            'post_status'         => 'publish',
            'ignore_sticky_posts' => true,
            'tax_query'           => [[
                'taxonomy' => 'contenido_destacado',
                'field'    => 'slug',
                'terms'    => ['contenido-destacado'],
            ]],
        ]);
        if (!$q->have_posts()) return '';

        $titleMain   = 'Contenido ';
        $titleAccent = 'Destacado';
        $tagLabel    = 'Contenido Destacado';

        $fallback_img = apply_filters(
            'thd_rc_destacados_fallback_thumb',
            get_template_directory_uri() . '/img/cover-default.jpg'
        );

        ob_start(); ?>
<section class="rc" data-rc>
  <div class="rc__head">
    <h3 class="rc__title"><span><?php echo esc_html($titleMain); ?></span>
      <span class="rc__title--accent"><?php echo esc_html($titleAccent); ?></span></h3>
    <div class="rc__controls">
      <button class="rc__btn" data-rc-prev aria-label="<?php echo esc_attr__('Anterior','thd'); ?>">‹</button>
      <button class="rc__btn" data-rc-next aria-label="<?php echo esc_attr__('Siguiente','thd'); ?>">›</button>
    </div>
  </div>
  <div class="rc__viewport rc__fade">
    <ul class="rc__track">
      <?php while ($q->have_posts()) : $q->the_post();
        $post_id = get_the_ID();
        $link    = get_permalink($post_id);
        $title   = get_the_title($post_id);
        $excerpt = get_the_excerpt($post_id);
        if (!$excerpt) {
          $excerpt = wp_trim_words(wp_strip_all_tags(get_post_field('post_content', $post_id)), 25, '…');
        }
        $img_html = thd_safe_thumb_html($post_id, 'medium_large', 'rc-card__img', $fallback_img);
      ?>
      <li class="rc__slide">
        <article class="rc-card">
          <a class="rc-card__media" href="<?php echo esc_url($link); ?>">
            <?php echo $img_html; ?>
          </a>
          <div class="rc-card__body">
            <div class="rc-card__tag"><?php echo esc_html($tagLabel); ?></div>
            <h4 class="rc-card__title"><a href="<?php echo esc_url($link); ?>"><?php echo esc_html($title); ?></a></h4>
            <p class="rc-card__excerpt"><?php echo esc_html($excerpt); ?></p>
          </div>
        </article>
      </li>
      <?php endwhile; wp_reset_postdata(); ?>
    </ul>
  </div>
</section>
<?php
        return ob_get_clean();
    }
}
