<?php
/**
 * The Sidebar containing the primary and secondary widget areas.
 *
 * @package WordPress
 * @subpackage ReadyMobile
 * @since Ready Mobile 2012 1.0
 */
?>

<div class="sidebar sidebar-social-media">
	<?php if ( is_active_sidebar( 'sidebar-social-media' ) ) : dynamic_sidebar( ' sidebar-social-media' ); ?>

	<?php endif; ?>
</div>
