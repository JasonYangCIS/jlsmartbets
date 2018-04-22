<?php
/**
 * The template for displaying Search Results pages.
 *
 * @package WordPress
 * @subpackage ReadyMobile
 * @since Ready Mobile 2012 1.0
 */

get_header(); ?>

<section id="content" class="content-left content">
	<?php if ( have_posts() ) : ?>

	<?php get_template_part( 'loop', 'search' ); ?>

<?php else : ?>
	<article>
		<h2><?php _e( 'Nothing Found', 'ReadyArtwork' ); ?></h2>
		<p><?php _e( 'Sorry, but nothing matched your search criteria. Please try again with some different keywords.', 'ReadyArtwork' ); ?></p>
	</article>
<?php endif; ?>
</section>

<?php get_sidebar(); ?>

<?php get_footer(); ?>
