<?php
get_header(); ?>


<?php
while (have_posts()) : the_post();

    // ——— Banner: featured image o fallback
    $default_bg = get_template_directory_uri() . '/img/banner-default.jpg';
    $bg_url = has_post_thumbnail()
        ? wp_get_attachment_image_url(get_post_thumbnail_id(), 'full')
        : $default_bg;
    ?>
<div class="banner" style="background-image:url('<?php echo esc_url($bg_url); ?>')">
    <div class="title"><?php the_title(); ?></div>
</div>
<div class="container">
    <?php the_content(); // <-- SIN esto, los bloques nunca se renderizan?>
</div>
<?php endwhile; ?>

<?php get_footer(); ?>