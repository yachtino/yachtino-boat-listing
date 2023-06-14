/**
 * Yachtino boat listing - WordPress plugin.
 * @author      Yachtino
 * @package     yachtino
 */

/* ######### image gallery - slider ################################ */

var globStartX = null;

function yachtinoHandleNewGallery()
{
    var glryFlag = false;
    var thumbSteps = jQuery('#yachtino-thumbs-per-slide').text() * 1;

    /**
     * Shows a new image or a new image line (subgallery)
     * @param string glryId - div ID of the gallery container
     * @param string/int startImg - either an integer with numeric index or "next" or "prev"
                       the words means the neighbor image on the left or right side
     * @param int step - how much images shoud be moved in one motion (1 in as main/large pic, 5 in thumbnails bar)
     * @param bool ignoreFlag - true = do not check whether the function already runs
     */
    function updateGallery(glryId, startImg, step, ignoreFlag)
    {
        // avoid that both mouse + touch events are fired
        if (glryFlag && !ignoreFlag) {
            return;
        }

        glryFlag = true;
        setTimeout(function(){
            glryFlag = false;
        }, 500);

        // define gallery type (main gallery or thumbs)
        // eg. glry or thumbglry -> necessary for css classes
        var p = glryId.split('-');
        var glryName = p[0];
        var itemId = p[1]; // item ID (eg. boat ID)
        var addId = p[2]; // additional number - if one boat is more times in the page

        if (startImg == 'prev') {
            var posSign = 1;
        } else if (startImg == 'next') {
            var posSign = -1;
        } else {
            var posSign = 0;
        }

        // number of all images in this gallery as integer
        var imgs = jQuery('#yoglry-' + itemId + '-' + addId).attr('data-number') * 1;

        // define max. image id (the last to right side)
        if (step == 1) {
            var maxImg = imgs - 1;
        } else {
            var fact = imgs / step;
            var factR = Math.floor(fact);
            if (fact == factR) {
                factR -= 1;
            }
            var maxImg = factR * step;
        }

        // currently active image / image on first position when more images (thumbs)
        var imgOld = jQuery('#' + glryId).attr('data-start');

        // new position after sliding
        if (posSign) {
            var imgNew = imgOld - (posSign * step);

            // last shown thumbnails
            if (step > 1) {
                if (imgNew >= imgs) {
                    imgNew = imgs - step;
                } else if (imgNew < 0) {
                    imgNew = 0;
                }
            }

        } else {
            var imgNew = startImg * 1;
        }

        // before first image and after last image is not possible
        if (imgNew >= 0 && imgNew <= maxImg) {

            moveSlider(glryName, itemId, addId, step, imgNew, imgs, maxImg);

            // load next image from server (set img-src)
            yoLoadImage(glryName, itemId, addId, imgNew);

            if (glryName == 'yoglry') {
                jQuery('#' + glryId + '-' + imgOld).removeClass(glryName + '-active');
                jQuery('#' + glryId + '-' + imgNew).addClass(glryName + '-active');
            }

            if ((glryName == 'yoglry' || glryName == 'yomaxglry')
            && jQuery('#yothumbglry-' + itemId + '-' + addId).length) {

                if (glryName == 'yoglry') {
                    // handle large gallery
                    moveSlider('yomaxglry', itemId, addId, 1, imgNew, imgs, maxImg);

                } else {
                    // handle main gallery
                    moveSlider('yoglry', itemId, addId, 1, imgNew, imgs, maxImg);
                    yoLoadImage('yoglry', itemId, addId, imgNew);
                }

                // handle thumbnail-gallery
                updateSubgallery(itemId, addId, imgOld, imgNew, imgs);

            } else if (glryName == 'yothumbglry' && startImg != 'prev' && startImg != 'next') {
                yoLoadImage('yoglry', itemId, addId, imgNew);
            }
        }


        // set image as active
        if (glryName == 'yothumbglry' && (startImg === 'prev' || startImg === 'next')) {

        } else {
            var newId = jQuery('#yoglry-' + itemId + '-' + addId).attr('data-start');
            jQuery('.yoglry-box').removeClass('yoglry-active');
            jQuery('#yoglry-' + itemId + '-' + addId + '-' + newId).addClass('yoglry-active');

            jQuery('.yothumbglry-box').removeClass('yothumbglry-active');
            jQuery('#yothumbglry-' + itemId + '-' + addId + '-' + newId).addClass('yothumbglry-active');

            jQuery('.yomaxglry-box').removeClass('yomaxglry-active');
            jQuery('#yomaxglry-' + itemId + '-' + addId + '-' + newId).addClass('yomaxglry-active');
        }
    }

    // general features for updating gallery
    function moveSlider(glryName, itemId, addId, step, imgNew, imgs, maxImg)
    {
        var glryId = glryName + '-' + itemId + '-' + addId;

        // define the slider box as object
        var sliderDiv = jQuery('#' + glryId + ' div').first();

        // move slider
        var newPos = (imgNew / step) * 100 * -1;

        sliderDiv.css({
            'transition' : 'transform 0.4s ease-in'
        });
        sliderDiv.css({
            'transform' : 'translate3d(' + newPos + '%, 0, 0)'
        });

        // change displayed counter (eg. 2 of 5 imgs)
        var imgCount = (imgNew * 1) + 1;
        jQuery('#' + glryId + ' .' + glryName + '-counter').text(imgCount + ' / ' + imgs);

        // deactivate arrow to right for last image
        // gallery in boat detail view has this arrow to right for event sending request
        if (imgNew == maxImg) {
            jQuery('#' + glryId + ' .' + glryName + '-arrow-next').addClass('yachtino-hidden');
        } else {
            jQuery('#' + glryId + ' .' + glryName + '-arrow-next').removeClass('yachtino-hidden');
        }

        // activate arrow to left for images that are not first
        if (imgNew == 0) {
            jQuery('#' + glryId + ' .' + glryName + '-arrow-prev').addClass('yachtino-hidden');
        } else {
            jQuery('#' + glryId + ' .' + glryName + '-arrow-prev').removeClass('yachtino-hidden');
        }

        // save data for next image
        // field for avoiding swiping first pic to left
        if (imgNew == maxImg) {
            jQuery('#' + glryId).attr('data-last', 'true');
        } else {
            jQuery('#' + glryId).attr('data-last', 'false');
        }
        jQuery('#' + glryId).attr('data-start', imgNew);
    }

    // Checks whether the current image in the main gallery is also visible and active in the thumb gallery
    function updateSubgallery(itemId, addId, imgOld, imgNew, imgs) {
        var thumbglryId = 'yothumbglry-' + itemId + '-' + addId;
        var subimgOldId = thumbglryId + '-' + imgOld;
        var subimgNewId = thumbglryId + '-' + imgNew;

        // get first image of the thumb gallery
        var firstThumb = 1 * jQuery('#' + thumbglryId).attr('data-start');

        setTimeout(function(){
            var newStart = Math.floor(imgNew / thumbSteps) * thumbSteps;

            if (firstThumb != newStart) {
                updateGallery(thumbglryId, newStart, thumbSteps, true);
            }
        }, 150);
    }

    function yoLoadImage(glryName, itemId, addId, imgNew)
    {
        if (glryName == 'yothumbglry') {
            var firstInBlock = Math.floor(imgNew / thumbSteps) * thumbSteps;
            var firstI = firstInBlock - thumbSteps;
            var lastI  = firstInBlock + (2 * thumbSteps) - 1;
        } else {
            var firstI = imgNew - 1;
            var lastI  = imgNew + 1;
        }

        var nextId;
        for (i = firstI; i <= lastI; i++) {
            nextId = glryName + '-' + itemId + '-' + addId + '-' + i;
            if (!jQuery('#' + nextId).length) {
                continue;
            }

            sr = jQuery('#' + nextId + ' img').attr('data-src');
            if (sr) {
                jQuery('#' + nextId + ' img').attr('src', sr);
                jQuery('#' + nextId + ' img').attr('data-src', '');
            }
        }
    }

    /* clicks on the left or right arrow for sliding pictures (on desktop) */
    jQuery('.yoglry-arrow, .yothumbglry-arrow, .yomaxglry-arrow').on('click', function(ev) {

        // define which gallery it is (more gallery containers in one page are possible)
        var glryId = jQuery(this).parent().attr('id');
        var p = glryId.split('-');

        if (p[0] == 'yothumbglry') {
            var step = thumbSteps;
        } else {
            var step = 1;
        }

        // x-position changes - to left or to right
        if (jQuery(this).hasClass(p[0] + '-arrow-prev')) {
            var prevNext = 'prev';
        } else {
            var prevNext = 'next';
        }
        updateGallery(glryId, prevNext, step, false);
    });

    /* main gallery */
    jQuery('body').on('click', '.yoglry-box', function() {
        var glryId = jQuery(this).attr('id');
        var p = glryId.split('-');
        var maxglryId = 'yomaxglry-' + p[1] + '-' + p[2];

        // max. gallery exists (is not the boat list)
        if (jQuery('#' + maxglryId).length) {
            jQuery('#yachtino-overlay1').removeClass('yachtino-hidden');
            jQuery('#' + maxglryId).parent().removeClass('yachtino-hidden');

            // load pictures if not loaded yet
            yoLoadImage('yomaxglry', p[1], p[2], (1 * p[3]));
        }
    });

    /* thumbnail gallery */
    jQuery('body').on('click', '.yothumbglry-box', function() {
        var thumbglryId = jQuery(this).attr('id');
        var p = thumbglryId.split('-');
        var glryId = 'yoglry-' + p[1] + '-' + p[2];
        updateGallery(glryId, p[3], 1, true);
    });

    /* large gallery */
    jQuery('.yomaxglry-close').on('click', function() {
        var id = jQuery(this).parent().attr('id');
        jQuery('#' + id).parent().addClass('yachtino-hidden');
        jQuery('#yachtino-overlay1').addClass('yachtino-hidden');
    });

    /* ############## trigger moves on touch screens ######################## */

    jQuery('.yoglry-container, .yothumbglry-container, .yomaxglry-container').on('touchstart', function(ev) {

        globStartX = ev.originalEvent.touches[0].pageX;

        var glryId = jQuery(this).attr('id');

        var p = glryId.split('-');

        var trans = jQuery('#' + glryId + ' div.' + p[0] + '-slider').css('transform');
        var values = trans.match(/-?[\d\.]+/g);
        if (values == null) {
            globSliderOrig = 0;
        } else {
            globSliderOrig = values[4] * 1;
        }
        jQuery('div.' + p[0] + '-slider').css({
            'transition' : ''
        });
    });

    jQuery('.yoglry-container, .yothumbglry-container, .yomaxglry-container').on('touchmove', function(ev) {
        var thisPos = ev.originalEvent.touches[0].pageX;
        if (globStartX !== null) {
            var dist = thisPos - globStartX;
            var divWidth = jQuery(this).css('width');
            divWidth = divWidth.replace('px', '');

            // not shifting left if it is the first picture (vice versa last picture)
            // this var says which element is currently active - first, last or empty string
            var thisAct = jQuery(this).attr('data-start');
            var isLast  = jQuery(this).attr('data-last');

            if ((dist > 20 && thisAct != '0' && dist <= divWidth)
            || (dist < -20 && isLast != 'true' && dist >= divWidth * -1)) {
                var newPos = dist + globSliderOrig;

                var p = jQuery(this).attr('id').split('-');
                jQuery(this).children('div.' + p[0] + '-slider').css({
                    'transform' : 'translate3D(' + newPos + 'px, 0, 0)'
                });
            }
        }
    });

    jQuery('.yoglry-container, .yothumbglry-container, .yomaxglry-container').on('touchend', function(ev) {
        var thisPos = ev.changedTouches[0].pageX;

        var dist = thisPos - globStartX;

        var targetDiv = jQuery(ev.target).attr('class');

        // the arrow to left or right has been clicked
        // touchend fires faster than click on arrow-next, so avoid firing of touchend in this case
        if (Math.abs(dist) < 20 || (typeof targetDiv != 'undefined'
        && (targetDiv.indexOf('yoglry-arrow') != -1 || targetDiv.indexOf('yomaxglry-arrow') != -1
        || targetDiv.indexOf('yothumbglry-arrow') != -1))) {

        // "normal" swipe
        } else {

            var thisId = jQuery(this).attr('id');

            // not shifting left if it is the first picture (vice versa last picture)
            var thisAct = jQuery(this).attr('data-start');
            var isLast = jQuery(this).attr('data-last');

            var p = jQuery(this).attr('id').split('-');
            var glryName = p[0];

            // move to right or to left, or move back the starting image
            var moveTo = '';
            if (dist > 20 && thisAct != '0') {
                moveTo = 'prev';

            } else if (dist < -20 && isLast != 'true') {
                moveTo = 'next';
            } else {
                moveTo = thisAct;
            }
            if (glryName == 'yothumbglry') {
                var steps = thumbSteps;
            } else {
                var steps = 1;
            }

            // if (steps == 1 && isLast == 'true')
            updateGallery(thisId, moveTo, steps, false);
        }

        globStartX = null;
    });

}

// add these functions also for elements added after initial DOM loaded
jQuery(document).ready(function () {
    yachtinoHandleNewGallery();
});