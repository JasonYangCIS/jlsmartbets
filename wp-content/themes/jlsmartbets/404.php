<?php
/**
 * The template for displaying 404 pages.
 *
 * @package WordPress
 * @subpackage ReadyMobile
 * @since Ready Mobile 2012 1.0
 */

get_header(); ?>
<div class="wrapper">
	<section class="content content-full">
		<article>
			<h1><?php _e( 'Not Found', 'ReadyArtwork' ); ?></h1>
			<p><?php _e( 'Sorry, but the page you were trying to view does not exist.', 'ReadyArtwork' ); ?></p>
		</article>
	</section>
</div>

<?php get_footer(); ?>
