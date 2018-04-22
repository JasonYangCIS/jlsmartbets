<?php
/**
 * The Template for displaying all single posts.
 *
 * @package WordPress
 * @subpackage ReadyMobile
 * @since Ready Mobile 2012 1.0
 */

get_header(); ?>

<section class="content content-left">

	<?php get_template_part( 'loop', 'single' ); ?>

</section>
<div class="sidebar-right"><?php get_sidebar(); ?></div>
<?php get_footer(); ?>
