<?php
/**
 * The main template file.
 *
 * @package WordPress
 * @subpackage ReadyMobile
 * @since Ready Mobile 2012 1.0
 */
 
get_header(); ?>
<div class="wrapper">
	<section class="content content-full">

		<?php get_template_part( 'loop', 'index' ); ?>

	</section> 
</div>
<?php get_footer(); ?>
