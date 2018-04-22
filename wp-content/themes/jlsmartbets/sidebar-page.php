<?php
/**
 * The Sidebar containing the primary and secondary widget areas.
 *
 * @package WordPress
 * @subpackage ReadyMobile
 * @since Ready Mobile 2012 1.0
 */
?>

<div class="sidebar sidebar-page">
	<?php if ( is_active_sidebar( 'sidebar-page' ) ) : dynamic_sidebar( 'sidebar-page' ); ?>
				
	<?php endif; ?>
</div>
