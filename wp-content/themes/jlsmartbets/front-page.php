<?php
  /**
  * The template for displaying all pages.
  *
  * @package WordPress
  * @subpackage ReadyMobile
  * @since Ready Mobile 2012 1.0
  */

  get_header(); ?>

  <?php /* Slick Slider using Custom Post Type */ ?>
  <?php query_posts( array('post_type' => 'slider_images', 'orderby' => 'menu_order', 'order' => 'ASC' ,  'post_status'=>'publish'  )); ?>
  <div class="slickslider">
    <ul class="slides">
      <?php if(have_posts()): while (have_posts()) : the_post(); ?>
        <?php if ( has_post_thumbnail() ): ?>
          <?php
          $custom = get_post_custom(get_the_ID()); ?> 
          <?php if ( has_post_thumbnail()) : ?>      
            <li class="slide">
              <img src="<?php $img = wp_get_attachment_image_src( get_post_thumbnail_id($post->ID), 'full'); echo $img[0]; ?>" alt=""/>
              <div class="slider-content-container">
                <div class="wrapper">
                  <div class="slider-inner-container">
                    <div class="slider-content">
                      <?php echo get_field('slider_content'); ?>
                      <div class="slider-button"><?php echo get_field('slider_button'); ?></div>
                    </div>

                  </div>
                </div>
              </div>
            </li>
          <?php endif; ?>
        <?php endif; ?>
      <?php endwhile; endif;?>
    </ul>
  </div>
  <?php wp_reset_query(); ?>

  <section class="content content-full front-page-content">
    <article>

      <?php query_posts( array('post_type' => 'frontpage_info','posts_per_page'=>4, 'orderby' => 'menu_order', 'order' => 'ASC' ,  'post_status'=>'publish'  )); ?>
      <div class="front_page_wrapper">
        <?php if(have_posts()):
        while (have_posts()) :  the_post(); ?>
        <div class="home-block-container">
          <?php echo'<h6>'; the_title(); echo'</h6>';  ?>
          <div class="frontpage_info">
            <?php echo the_content('Read more...'); ?>
          </div>
        </div>
      <?php endwhile; endif;?>
    </div>
    <?php wp_reset_query(); ?>
  </article>
</section>


<?php  get_footer(); ?>
