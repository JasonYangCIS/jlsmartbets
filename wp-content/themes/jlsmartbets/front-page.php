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
      <div class="main-content">
        <div class="main-content-inner">
          <h1><?php the_title(); ?></h1>
          <?php the_content(); ?>
          <div class="main-content-button"><?php echo get_field('main_content_button'); ?></div>
        </div>
      </div>
      <div class="homepage-image">
        <?php if ( has_post_thumbnail() ): ?>
          <?php the_post_thumbnail();  ?>
        <?php endif;?>
      </div>
    </article>
  </section>


  <?php  get_footer(); ?>
