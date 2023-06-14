/**
 * Yachtino boat listing - WordPress plugin.
 * @author      Yachtino
 * @package     yachtino
 */

// ############# light box #####################################################
// NEW lightbox (in the middle of screen)

var yachtinoIdContentLightBox = [];
yachtinoIdContentLightBox[1] = '';
function yachtinoShowLightBoxSimple(boxId, textToShow)
{
    jQuery('#yachtino-lightbox' + boxId).width('');
    jQuery('#yachtino-lightbox-cont' + boxId).html(textToShow);
    jQuery('#yachtino-lightOkButton' + boxId).removeClass('yachtino-hidden');
    yachtinoOpenLightBox(boxId);
}

function yachtinoShowLightBoxWhole(boxId, textToShow)
{
    jQuery('#yachtino-lightbox' + boxId).width('');
    jQuery('#yachtino-lightbox-cont' + boxId).html(textToShow);
    yachtinoOpenLightBox(boxId);
}

function yachtinoOpenLightBox(boxId)
{
    jQuery('#yachtino-overlay' + boxId).removeClass('yachtino-hidden');
    // jQuery('html, body').animate({scrollTop:0}, 'slow');

    // show lightbox - must be before calculating position
    // otherwise the width and hight is 0
    jQuery('#yachtino-lightbox' + boxId).removeClass('yachtino-hidden');

    yachtinoResizeLightBox(boxId);
}

function yachtinoResizeLightBox(boxId)
{
    // left position
    var wX = jQuery(window).scrollLeft();
    var wWdt = jQuery(window).width();
    var bWdt = jQuery('#yachtino-lightbox' + boxId).width();
    if (bWdt >= wWdt || wWdt < 440) {
        var newWdt = wX;
        jQuery('#yachtino-lightbox' + boxId).width(wWdt);
    } else {
        var newWdt = wX + ((wWdt - bWdt) / 2);

        // WordPress correction
        newWdt -= 300;
        if (newWdt < 0) {
            newWdt = 0;
        }
    }
    console.log('lightbox: wX = ' + wX + ', wWdt = ' + wWdt + ', bWdt = ' + bWdt + ', newWdt = ' + newWdt);
    jQuery('#yachtino-lightbox' + boxId).css('left', newWdt); // horizontally in the middle of the viewport

    // top position
    var wY = jQuery(window).scrollTop();
    var wHgt = jQuery(window).height();

    // WordPress correction for not-phone
    if (wWdt > 480 && wHgt >= 400) {
        wHgt -= 200;
    }

    var bHgt = jQuery('#yachtino-lightbox' + boxId).height();
    if (bHgt >= wHgt) {
        var newHgt = wY;
    } else {
        var newHgt = wY + ((wHgt - bHgt) / 2);
    }
    console.log('lightbox: wY = ' + wY + ', wHgt = ' + wHgt + ', bHgt = ' + bHgt + ', newHgt = ' + newHgt);
    // console.log('screenTop = ' + window.screenTop);
    jQuery('#yachtino-lightbox' + boxId).css('top', newHgt); // vertically in the middle of the viewport
}

// close lightBox (overlay)
function yachtinoCloseLightBox(boxId)
{
    // put content back into origin div
    if (yachtinoIdContentLightBox[boxId]) {
        var cont = jQuery('#yachtino-lightbox-cont' + boxId).html();
        jQuery('#' + yachtinoIdContentLightBox[boxId]).html(cont);
        yachtinoIdContentLightBox[boxId] = '';
    }
    jQuery('#yachtino-lightbox' + boxId).width('');
    jQuery('#yachtino-lightbox-cont' + boxId).html('');
    jQuery('#yachtino-overlay' + boxId).addClass('yachtino-hidden');
    jQuery('#yachtino-lightbox' + boxId).addClass('yachtino-hidden');
    jQuery('#yachtino-lightOkButton' + boxId).addClass('yachtino-hidden');
}

// shows overlay and loader gif
function yachtinoShowLoaderGif() {
    jQuery('#yachtino-overlay-wait').removeClass('yachtino-hidden');
    jQuery('#yachtino-waitbox').removeClass('yachtino-hidden');
}

// hides overlay and loader gif
function yachtinoHideLoaderGif() {
    jQuery('#yachtino-overlay-wait').addClass('yachtino-hidden');
    jQuery('#yachtino-waitbox').addClass('yachtino-hidden');
}

jQuery(function() {

    // close overlay
    jQuery('#yachtino-lightbox-closex1, #yachtino-lightOkButton1').on('click', function() {
        yachtinoCloseLightBox(1);
    });

    // button "cancel" in the lightbox
    jQuery('body').on('click', '.yachtino-js-closeLbox1', function() {
        yachtinoCloseLightBox(1);
    });
});

// ############# end light box #################################################
