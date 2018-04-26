
jQuery(document).ready(function() { 

    if ( jQuery('.slickslider').length) {
        jQuery('.slickslider ul.slides').slick();
    }

     /* Mobile hamburger toggle */
    jQuery('.mobile-hamburger').click(function(){
        jQuery(this).toggleClass('active');
        jQuery('.menu-header-container').toggleClass('active');
    });

	// TO TOP
    // hide #back-top first
    jQuery(".back-to-top").hide();
    jQueryshowToTop = 500;

    // fade in #back-top
    jQuery(function () { 
    	jQuery(window).scroll(function () {
    		var y = jQuery(window).scrollTop();
    		if (y > jQueryshowToTop) {
    			jQuery('.back-to-top').fadeIn();
    		} else {
    			jQuery('.back-to-top').fadeOut();
    		}
    	});
        // scroll body to 0px on click
        jQuery('.back-to-top').click(function () {
        	jQuery('body,html').animate({
        		scrollTop: 0
        	}, 450);
        	return false;
        });
    });
});


