<?php
  /**
   * Template Name: Membership Template
  */

  get_header(); ?>

  <?php wp_reset_query(); ?>

  <section class="content content-full membership">
    <article>
      <div class="wrapper">
        <div class="membership-content-container">
          <h1><?php the_title(); ?></h1>
          <?php the_content(); ?>
        </div>
      </div>
    </article>

    <div class="product-feed-container">
      <div class="wrapper">
        <?php 
        $posts = get_field('product_feed');
        if( $posts ): ?>
        <ul>
          <?php foreach( $posts as $post): // variable must be called $post (IMPORTANT) ?>
            <?php setup_postdata($post); ?>
            <li>
              <a href="<?php the_permalink(); ?>" class="product-feed-block">
                <?php if ( has_post_thumbnail() ): ?>
                  <div class="product-feed-image-container">
                    <?php the_post_thumbnail();  ?>
                  </div>
                <?php endif;?>
                <span class="product-feed-title"><?php the_title(); ?></span>
              </a>
              <a href="<?php the_permalink(); ?>" class="button">
                <?php echo "Learn More"; ?>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
        <?php wp_reset_postdata(); // IMPORTANT - reset the $post object so the rest of the page works correctly ?>
      <?php endif; ?>
    </div>
  </div>
</section>


<?php  get_footer(); ?>