<?php
/**
 * The loop that displays a single post.
 *
 * @package WordPress
 * @subpackage ReadyArtwork
 * @since Ready Mobile 2012 1.0
 */
?>


<?php if ( have_posts() ) while ( have_posts() ) : the_post(); ?>
  <?php /*
	  <nav>
	  <?php previous_post_link( '%link', '' . _x( '&larr;', 'Previous post link', 'starkers' ) . ' %title' ); ?>
	  <?php next_post_link( '%link', '%title ' . _x( '&rarr;', 'Next post link', 'starkers' ) . '' ); ?>
    </nav> */ ?>

  <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>

    <header>
      <h1 class="blog-title"><?php the_title(); ?></h1>
    </header>
       
    <?php  the_content(); ?>

    <?php wp_link_pages( array( 'before' => '<nav>' . __( 'Pages:', 'ReadyArtwork' ), 'after' => '</nav>' ) ); ?>

    <?php if ( get_the_author_meta( 'description' ) ) : // If a user has filled out their description, show a bio on their entries  ?>
      <?php echo get_avatar( get_the_author_meta( 'user_email' ), apply_filters( 'starkers_author_bio_avatar_size', 60 ) ); ?>
      <h2><?php printf( esc_attr__( 'About %s', 'starkers' ), get_the_author() ); ?></h2>
      <?php the_author_meta( 'description' ); ?>
      <a href="<?php echo get_author_posts_url( get_the_author_meta( 'ID' ) ); ?>">
        <?php printf( __( 'View all posts by %s &rarr;', 'starkers' ), get_the_author() ); ?>
      </a>
    <?php endif; ?>

    <footer>
      <?php//starkers_posted_in(); ?>
      <?php edit_post_link( __( 'Edit', 'ReadyArtwork' ), '', '' ); ?>
    </footer>

  </article>


  <?php // comments_template( '', true ); ?>

<?php endwhile; // end of the loop. ?>
<?php
$prev_post = get_adjacent_post( true, '', true );
$next_post = get_adjacent_post( true, '', false );
if($next_post == '')
{
  $next_post = get_boundary_post();
}
if($prev_post == '')
{
  $prev_post = get_boundary_post(false, '', false); 
}
$prev_post_id = $prev_post->ID;
$next_post_id = $next_post->ID;   ?>


<div id="nav-below" class="navigation">
  <div class="nav-previous"><a href="<?php echo get_permalink($prev_post); ?>"><?php echo __('View Older','ReadyArtwork'); ?></a></div>
  <div class="nav-next"><a href="<?php echo get_permalink($next_post); ?>"><?php echo __('View Newer','ReadyArtwork'); ?></a></div>
</div><!-- #nav-below -->



