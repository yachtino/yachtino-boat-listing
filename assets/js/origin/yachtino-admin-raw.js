/**
 * Yachtino boat listing - WordPress plugin.
 * @author      Yachtino
 * @package     yachtino
 */

function openAddModule()
{
    var cont = jQuery('#yachtino-adding-box').html();

    // remove content from temporary box to avoid double ids
    yachtinoIdContentLightBox[1] = 'yachtino-adding-box';
    jQuery('#yachtino-adding-box').html('');

    yachtinoShowLightBoxWhole(1, cont);
}

jQuery(document).ready(function () {

    function changeLgsForModule()
    {
        // add / or /c1001/ after <input>
        var x = jQuery('#yachtino-moduleid option:selected').attr('data-addpath');
        var itype = jQuery('#yachtino-moduleid option:selected').attr('data-itemtype');
        if (typeof itype == 'undefined') {
            itype = jQuery('#itemtype').val();
        }
        jQuery('.yachtino-js-add-to-path').text(x);
        if (x == '/') {
            jQuery('.yachtino-placeholder-box').addClass('yachtino-hidden');
        } else {

            // stays: model, country
            jQuery('.yachtino-js-one-pholder-model').removeClass('yachtino-hidden');
            jQuery('.yachtino-js-one-pholder-country').removeClass('yachtino-hidden');

            jQuery('.yachtino-js-one-pholder-name').addClass('yachtino-hidden');
            jQuery('.yachtino-js-one-pholder-yearBuilt').addClass('yachtino-hidden'); // not moorings
            jQuery('.yachtino-js-one-pholder-manufacturer').addClass('yachtino-hidden'); // not moorings
            jQuery('.yachtino-js-one-pholder-boatType').addClass('yachtino-hidden');
            jQuery('.yachtino-js-one-pholder-category').addClass('yachtino-hidden');
            jQuery('.yachtino-js-one-pholder-newOrUsed').addClass('yachtino-hidden');
            jQuery('.yachtino-js-one-pholder-power').addClass('yachtino-hidden');
            jQuery('.yachtino-js-one-pholder-length').addClass('yachtino-hidden');
            jQuery('.yachtino-js-one-pholder-beam').addClass('yachtino-hidden');

            if (itype == 'cboat' || itype == 'sboat') {
                jQuery('.yachtino-js-one-pholder-name').removeClass('yachtino-hidden');
                jQuery('.yachtino-js-one-pholder-yearBuilt').removeClass('yachtino-hidden');
                jQuery('.yachtino-js-one-pholder-manufacturer').removeClass('yachtino-hidden');
                jQuery('.yachtino-js-one-pholder-boatType').removeClass('yachtino-hidden');
                jQuery('.yachtino-js-one-pholder-category').removeClass('yachtino-hidden');
                jQuery('.yachtino-js-one-pholder-length').removeClass('yachtino-hidden');
                jQuery('.yachtino-js-one-pholder-beam').removeClass('yachtino-hidden');

            } else if (itype == 'engine') {
                jQuery('.yachtino-js-one-pholder-yearBuilt').removeClass('yachtino-hidden');
                jQuery('.yachtino-js-one-pholder-manufacturer').removeClass('yachtino-hidden');
                jQuery('.yachtino-js-one-pholder-newOrUsed').removeClass('yachtino-hidden');
                jQuery('.yachtino-js-one-pholder-power').removeClass('yachtino-hidden');

            } else if (itype == 'trailer') {
                jQuery('.yachtino-js-one-pholder-yearBuilt').removeClass('yachtino-hidden');
                jQuery('.yachtino-js-one-pholder-manufacturer').removeClass('yachtino-hidden');
                jQuery('.yachtino-js-one-pholder-newOrUsed').removeClass('yachtino-hidden');
                jQuery('.yachtino-js-one-pholder-length').removeClass('yachtino-hidden');
            }

            jQuery('.yachtino-placeholder-box').removeClass('yachtino-hidden');
        }

        // Set placeholder for this module.
        var x = jQuery('#yachtino-moduleid option:selected').attr('data-pholder');
        jQuery('.yachtino-js-placeholder').attr('placeholder', x);
    }

    jQuery('#yachtino-moduleid').on('change', function () {
        changeLgsForModule();
    });
    changeLgsForModule();

    function openPlaceholder(lg, pType)
    {
        jQuery('#yachtino-placeholders-' + lg + '-' + pType).slideDown();
        jQuery('#yachtino-placeholders-' + lg + '-' + pType).removeClass('yachtino-hidden');
    }

    function closePlaceholder(lg, pType)
    {
        if (jQuery('#yachtino-placeholders-' + lg + '-' + pType).hasClass('yachtino-hidden')) {
            return;
        }
        jQuery('#yachtino-placeholders-' + lg + '-' + pType).slideUp();
        setTimeout(function(){
            jQuery('#yachtino-placeholders-' + lg + '-' + pType).addClass('yachtino-hidden');
        }, 300);
    }

    function closeAllPlaceholders()
    {
        jQuery('.yachtino-placeholders').each(function () {
            var id = jQuery(this).attr('id');
            var p = id.split('-');
            closePlaceholder(p[2], p[3]);
        });
    }

    jQuery('.yo-js-show-placeholder').on('click', function () {
        var id = jQuery(this).attr('id');
        var p = id.split('-');
        var lg = p[2];
        var pType = p[3];
        if (jQuery('#yachtino-placeholders-' + lg + '-' + pType).hasClass('yachtino-hidden')) {

            // close all placeholder boxes and THEN open the desired one
            jQuery.when(closeAllPlaceholders()).done(function() {
                openPlaceholder(lg, pType);
            });

        } else {
            closePlaceholder(lg, pType);
        }
    });

    jQuery('.yo-js-close-placeholders').on('click', function () {
        var id = jQuery(this).attr('id');
        var p = id.split('-');
        var lg = p[2];
        var pType = p[3];
        closePlaceholder(lg, pType);
    });

    // Click on placeholder name -> set it into input field.
    jQuery('.yachtino-js-pholder').on('click', function () {
        var id = jQuery(this).attr('data-id');
        var p = id.split('-');
        var lg = p[0];
        var fieldname = p[1];
        var pholder = p[2];

        var txt = jQuery('#langs-' + lg + '-' + fieldname).val();
        if (txt && txt.slice(-1) != ' ') {
            txt += ' ';
        }
        txt += '{' + pholder + '}';
        jQuery('#langs-' + lg + '-' + fieldname).val(txt);
    });

    // Click for deleting a page or module.
    jQuery('.yachtino-js-delete-item').on('click', function () {
        var cont = jQuery('#yachtino-deleting-box').html();

        // Remove content from temporary box to avoid double ids.
        yachtinoIdContentLightBox[1] = 'yachtino-deleting-box';
        jQuery('#yachtino-deleting-box').html('');

        // Set data from THIS item.
        var id = jQuery(this).attr('data-id');
        var p = id.split('-');

        yachtinoShowLightBoxWhole(1, cont);

        // After creating HTML -> set values.
        if (p[0] == 'page') { // delete page
            jQuery('#masterid').val(p[1]);

        } else { // delete module
            jQuery('#moduleid').val(p[1]);
        }
        jQuery('#yachtino-placeholder-name').text(jQuery(this).attr('data-name'));
    });

    // Click for adding module.
    jQuery('#yachtino-add-module').on('click', function () {
        openAddModule();
    });

    // Show / hide search form options for editing module.
    jQuery('.yachtino-js-sform').on('change', function () {
        var vl = jQuery('#sform').val();
        console.log('vl = ' + vl);
        if (vl) {
            jQuery('.yachtino-searchfield-box').slideDown();
            jQuery('.yachtino-searchfield-box').removeClass('yachtino-hidden');
        } else {
            jQuery('.yachtino-searchfield-box').slideUp();
            setTimeout(function(){
                jQuery('.yachtino-searchfield-box').addClass('yachtino-hidden');
            }, 300);
        }
    });

    // Show / hide filter options for editing module.
    jQuery('.yachtino-js-fltr').on('click', function () {
        var isChecked = jQuery('#fltr').prop('checked');
        if (isChecked) {
            jQuery('.yachtino-filter-box').slideDown();
            jQuery('.yachtino-filter-box').removeClass('yachtino-hidden');
        } else {
            jQuery('.yachtino-filter-box').slideUp();
            setTimeout(function(){
                jQuery('.yachtino-filter-box').addClass('yachtino-hidden');
            }, 300);
        }
    });

    // Show / hide number of columns for boat list - layout tiles.
    jQuery('#layout_list, #layout_tiles').on('click', function () {
        var isTiles = jQuery('#layout_tiles').prop('checked');
        if (isTiles) {
            jQuery('#yachtino-number-columns').removeClass('yachtino-hidden');
        } else {
            jQuery('#yachtino-number-columns').addClass('yachtino-hidden');
        }
    });

    // Show / hide linked page for editing module for boat list.
    jQuery('#linked_m, #linked_u').on('click', function () {
        var isPlugin = jQuery('#linked_m').prop('checked');
        if (isPlugin) {
            jQuery('#yachtino-div-linkedm').removeClass('yachtino-hidden');
            jQuery('#yachtino-div-linkedu').addClass('yachtino-hidden');
        } else {
            jQuery('#yachtino-div-linkedm').addClass('yachtino-hidden');
            jQuery('#yachtino-div-linkedu').removeClass('yachtino-hidden');
        }
    });
});
