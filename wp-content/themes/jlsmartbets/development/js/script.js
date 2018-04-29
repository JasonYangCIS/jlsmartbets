
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

    var productQtyBox = jQuery('.woocommerce div.product form.cart div.quantity');
    if ( productQtyBox.length > 0 ) {  
        productQtyBox.append('<span class="up-arrow">+</span><span class="down-arrow">-</span>');

        jQuery('.up-arrow').click(function(){
            var currentQty = jQuery(this).closest('.quantity').find('input[type="number"]').val();
            currentQty++;
            jQuery(this).closest('.quantity').find('input[type="number"]').val(currentQty);
        });

        jQuery('.down-arrow').click(function(){
            var currentQty = jQuery(this).closest('.quantity').find('input[type="number"]').val();
            if ( currentQty > 0 ) {
                currentQty--;
                jQuery(this).closest('.quantity').find('input[type="number"]').val(currentQty);
            }
        });
    }
});


