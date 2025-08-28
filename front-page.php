<?php
get_header(); ?>
<div class="container">
    <?php
        while (have_posts()) : the_post();
            the_content(); // <-- SIN esto, los bloques nunca se renderizan
        endwhile;
?>
</div>
<?php get_footer(); ?>