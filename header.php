<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
    <meta charset="<?php echo esc_attr(get_bloginfo('charset')); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html(get_bloginfo('name')); ?></title>
    <?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
    <header>
        <div class="container">
            <?php the_custom_logo();?>
            <div class="blog_name"><?php bloginfo();?></div>
            <div class="thd-search" data-thd-search>
                <div id="thd-search-panel" class="thd-search__panel" hidden>
                    <?php get_search_form(); ?>
                    <div class="thd-search__suggestions" role="listbox"
                        aria-label="<?php esc_attr_e('Sugerencias', 'thd'); ?>"></div>
                </div>
            </div>
        </div>
    </header>