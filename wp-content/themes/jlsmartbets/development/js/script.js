
jQuery(document).ready(function() { 

    if ( jQuery('.slickslider').length) {
        jQuery('.slickslider ul.slides').slick();
    }

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


