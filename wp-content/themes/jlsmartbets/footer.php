<?php
  /**
  * The template for displaying the footer.
  *
  * @package WordPress
  * @subpackage ReadyMobile
  * @since Ready Mobile 2012 1.0
  */
  ?>


</main> <!-- main -->


<?php if(stripslashes(get_option("ready_logo_img") != "")): ?>
  <?php $logo = stripslashes(get_option("ready_logo_img")); ?>
<?php else: ?>
  <?php $logo = get_template_directory_uri() . "/img/logo.png"; ?>
<?php endif; ?>

<footer id="footer" class="footer">
  <a class="back-to-top" href="javascript:void(0);"><span></span></a>
  <div class="wrapper">
    <div class="footer-logo">
      <a class="logo" href="<?php echo home_url()?>">
        <img alt="<?php bloginfo('name') ?>" src="<?php echo $logo; ?>"/>
      </a>
    </div>
    <?php wp_nav_menu( array( 'container' => 'nav', 'fallback_cb' => 'readymobile_menu', 'theme_location' => 'footer', 'link_before' => '' ) ); ?>   

    <?php get_sidebar('social-media'); ?>
  </div>

  <div class="copyright">
    &copy;<?php echo date('Y') . " " . get_bloginfo('name'); ?> | All Rights Reserved
  </div>
</footer>
</div> <!-- container -->

<?php    wp_footer();   ?>
</body>
</html>



