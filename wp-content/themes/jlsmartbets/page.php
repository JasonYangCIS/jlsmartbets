<?php
/**
 * The template for displaying all pages.
 *
 * @package WordPress
 * @subpackage ReadyMobile
 * @since Ready Mobile 2012 1.0
 */

get_header(); ?>

<?php $slug = get_post( $post )->post_name; ?>

<section class="content content-left <?php echo $slug;?>">

    <?php get_template_part( 'loop', 'page' ); ?>

</section>

 <?php get_sidebar('page'); ?>

<?php get_footer(); ?>
