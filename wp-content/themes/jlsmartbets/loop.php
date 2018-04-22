<?php
/**
 * The loop that displays posts.
 *
 * @package WordPress
 * @subpackage ReadyMobile
 * @since Ready Mobile 2012 1.0
 */
?>


<?php /* If there are no posts to display, such as an empty archive page */ ?>
<?php if ( ! have_posts() ) : ?>
  <article>
    <h1><?php _e( 'Not Found', 'ReadyArtwork' ); ?></h1>
    <p><?php _e( 'Apologies, but no results were found for the requested archive.', 'ReadyArtwork' ); ?></p>
  </article>
  <?php //get_search_form(); ?>
<?php endif; ?>

<?php while ( have_posts() ) : the_post(); ?>

  <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>

    <header>
      <h2 class="blog-title"><a href="<?php the_permalink(); ?>" title="<?php printf( esc_attr__( 'Permalink to %s', 'ReadyArtwork' ), the_title_attribute( 'echo=0' ) ); ?>" rel="bookmark"><?php the_title(); ?></a></h2>
      <div class='blog-header-top'>
        <div class="blog-author"><?php echo __('By','ReadyArtwork') . ' '; ?><?php the_author_posts_link(); ?><?php echo ' '; ?></div>
        <div class='blog-date'><?php echo __('on','ReadyArtwork') . ' '; ?><?php the_time( get_option( 'date_format' ) ); ?><?php echo ' '; ?></div>
        <div class='blog-category'>
          <?php echo __('in','ReadyArtwork') . ' ' . get_the_category_list(', '); ?>
        </div>
      </div>
    </header>

    <div class="featured-object">
      <?php if ( has_post_thumbnail() ): ?>
      <?php the_post_thumbnail();  ?>
    <?php else: ?>
    <?php echo catch_that_image('image'); ?>
  <?php endif; ?>
</div>

<?php //if ( is_archive() || is_search() ) : // Only display excerpts for archives and search. ?>
  <?php //the_excerpt('Read More'); ?>
  <?php //the_content('Read More'); ?>
<?php //else : ?>
  <?php //the_content( __( 'Continue reading &rarr;', 'ReadyArtwork' ) ); ?>
  <?php the_excerpt(); ?>
  <div class="read-more"><a href="<?php the_permalink($post->ID);?>"><?php echo stripslashes(get_option("ready_blog_read_more")); ?></a></div>
  <?php wp_link_pages( array( 'before' => '<nav>' . __( 'Pages:', 'ReadyArtwork' ), 'after' => '</nav>' ) ); ?>
<?php // endif; ?>

<footer>
  <?php edit_post_link( __( 'Edit', 'ReadyArtwork' ), '', '' ); ?>
</footer>

</article>

<?php comments_template( '', true ); ?>

<?php endwhile; // End the loop. Whew. ?>

<?php if ( function_exists('base_pagination') ) { base_pagination(); } else if ( is_paged() ) { ?>
<div class="navigation clearfix">
  <div class="alignleft"><?php next_posts_link('&laquo; Previous Entries') ?></div>
  <div class="alignright"><?php previous_posts_link('Next Entries &raquo;') ?></div>
</div>
<?php } ?>

