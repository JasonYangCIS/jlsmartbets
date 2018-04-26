<?php
  /**
  * The Header for our theme.
  *
  * Displays all of the <head> section
  *
  * @package WordPress
  * @subpackage ReadyArtwork
  * @since ReadyArtwork HTML5 3.0
  */
  ?>
  <!DOCTYPE html>
  <!--[if lt IE 7]>      <html class="no-js lt-ie9 lt-ie8 lt-ie7" <?php language_attributes(); ?>> <![endif]-->
  <!--[if IE 7]>         <html class="no-js lt-ie9 lt-ie8" <?php language_attributes(); ?>> <![endif]-->
  <!--[if IE 8]>         <html class="no-js lt-ie9" <?php language_attributes(); ?>> <![endif]-->
  <!--[if gt IE 8]><!--> <html class="no-js" <?php language_attributes(); ?>> <!--<![endif]-->
  <head>
    <?php if(stripslashes(get_option("ready_logo_img") != "")): ?>
      <?php $logo = stripslashes(get_option("ready_logo_img")); ?>
    <?php else: ?>
      <?php $logo = get_template_directory_uri() . "/img/logo.png"; ?>
    <?php endif; ?>
    <?php $url = wp_get_attachment_url( get_post_thumbnail_id($post->ID) ); ?>
    <?php if ( has_post_thumbnail() ){ ?>
    <meta property="og:image" content="<?php echo $url; ?>"/> 
    <?php }else{ ?>  
    <meta property="og:image" content="<?php echo $logo; ?>"/> 
    <?php }; ?>

    <meta charset="<?php bloginfo( 'charset' ); ?>" />
    <title><?php
    global $page, $paged, $post;
    wp_title( '|', true, 'right' );
    bloginfo( 'name' );
    $site_description = get_bloginfo( 'description', 'display' );
    if ( $site_description && ( is_home() || is_front_page() ) )
      echo " | $site_description";
    if ( $paged >= 2 || $page >= 2 )
      echo ' | ' . sprintf( __( 'Page %s', 'ReadyArtwork' ), max( $paged, $page ) );
    ?></title>
    <meta http-equiv="cleartype" content="on">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">   
    <link rel="shortcut icon" href="<?php echo get_stylesheet_directory_uri(); ?>/favicon.ico" /> 
    <link rel="profile" href="http://gmpg.org/xfn/11" />

    <link rel="stylesheet" type="text/css" media="all" href="<?php  bloginfo('template_directory');  ?>/css/main.css" />
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:400,600,700" rel="stylesheet">

    <link rel="pingback" href="<?php bloginfo( 'pingback_url' ); ?>" />
    <?php wp_head(); ?>
  </head>

  <body <?php body_class(); ?>>
    <div id="container">
      <header id="header" class="header">
        <!--[if lte IE 9]> <div class="IE-warning"> <a href="https://support.microsoft.com/en-us/products/internet-explorer">You are using an outdated browser. For a faster, safer browsing experience, upgrade for free today.</a></div> <![endif]-->
        <div class="wrapper">
          <a class="mobile-hamburger" href="javascript:void(0);"><span></span></a>
          <div class="header_container">
            <div class="header_logo">
              <?php $heading_tag = ( is_home() || is_front_page() ) ? 'h1' : 'div'; ?>
              <<?php echo $heading_tag; ?> id="site-title">
              <a class="logo" href="<?php echo home_url()?>"><img alt="<?php bloginfo('name') ?>" src="<?php echo $logo; ?>"/><span class="visuallyhidden"><?php bloginfo('name') ?></span></a>
              </<?php echo $heading_tag; ?>>
            </div>
          </div>
          <div class="woo-commerce-buttons">
            <div class="my-account">
              <a href="<?php echo get_home_url() . '/my-account'; ?>"></a>
            </div>
            <?php if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

              $count = WC()->cart->cart_contents_count;
              ?><a class="cart-contents" href="<?php echo WC()->cart->get_cart_url(); ?>" title="<?php _e( 'View your shopping cart' ); ?>"><?php 
              if ( $count > 0 ) {
                ?>
                <span class="cart-contents-count"><?php echo esc_html( $count ); ?></span>
                <?php
              }
              ?></a>

              <?php } ?>
          </div>
          <?php wp_nav_menu( array( 'container' => 'nav', 'fallback_cb' => 'readymobile_menu', 'theme_location' => 'primary', 'link_before' => '' ) ); ?>   
        </div>


      </header>

      <main id="main">


