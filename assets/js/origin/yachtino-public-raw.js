/**
 * Yachtino boat listing - WordPress plugin.
 * @author      Yachtino
 * @package     yachtino
 */

jQuery(document).ready(function () {

    /* ###### show/hide description text ############# */

    jQuery('#yachtino-moretext-head1').on('click', function (e) {
        jQuery('#yachtino-moretext-box').slideDown();
        jQuery('#yachtino-moretext-box').removeClass('yachtino-hidden');
        jQuery('#yachtino-moretext-head1').addClass('yachtino-hidden');
        setTimeout(function(){
            jQuery('#yachtino-moretext-head2').removeClass('yachtino-hidden');
        }, 400);
    });
    jQuery('#yachtino-moretext-head2').on('click', function (e) {
        jQuery('#yachtino-moretext-box').slideUp();
        setTimeout(function(){
            jQuery('#yachtino-moretext-box').addClass('yachtino-hidden');
        }, 400);
        jQuery('#yachtino-moretext-head2').addClass('yachtino-hidden');
        jQuery('#yachtino-moretext-head1').removeClass('yachtino-hidden');
    });

    /* ###### show/hide equipment ############# */

    function openEquipmentItem(mainDiv)
    {
        if (!mainDiv.hasClass('is-open')) {
            mainDiv.addClass('is-open');
            mainDiv.find('.yachtino-equipment-item-text').slideDown();
        }
    }

    function closeEquipmentItem(mainDiv)
    {
        if (mainDiv.hasClass('is-open')) {
            mainDiv.removeClass('is-open');
            mainDiv.find('.yachtino-equipment-item-text').slideUp();
        }
    }

    // open single equipent node
    jQuery('.yachtino-equipment-item-wrapper h3').on('click', function (e) {
        e.preventDefault();
        var el = jQuery(this).parent();
        if (el.hasClass('is-open')) {
            closeEquipmentItem(el);
        } else {
            openEquipmentItem(el);
        }
    });

    // open / hide the whole equipment block
    jQuery('.yachtino-equipment div.eqhead').on('click', function (e) {
        var op = jQuery('.yachtino-equipment div.eqhead span').attr('data-open');
        var cl = jQuery('.yachtino-equipment div.eqhead span').attr('data-close');

        if (jQuery('.yachtino-equipment div.eqhead span').text() == op) {
            var toOpen = true;
        } else {
            var toOpen = false;
        }
        if (toOpen) {
            jQuery('.yachtino-equipment div.eqhead span').text(cl);
        } else {
            jQuery('.yachtino-equipment div.eqhead span').text(op);
        }
        var el, isOpen;
        jQuery('.yachtino-equipment-item-wrapper').each(function() {
            if (toOpen) {
                openEquipmentItem(jQuery(this));
            } else {
                closeEquipmentItem(jQuery(this));
            }
        });
    });

    /* ###### search form in boat list ############# */

    function post_filter_options()
    {
        /* IMPORTANT!!! copy in the same order like /includes/api/class-yachtino-api.php */
        var itemType = jQuery('#yachtino-itemtype').text();
        if (itemType == 'cboat' || itemType == 'sboat') {
            orderingVars = ['btid', 'bcid', 'btcid', 'ct', 'provid', 'fuel', 'hmid',
                'lngf', 'lngt', 'ybf', 'ybt', 'powf', 'powt', 'cabf', 'cabt', 'fly',
                'newused', 'sprcf', 'sprct',
                'rgww', 'tn', 'wkprcf', 'wkprct', 'daprcf', 'daprct', 'hrprcf', 'hrprct', 'beprcf', 'beprct',
                'pfidurl', 'q', 'orderby'];

        } else if (itemType == 'engine') {
            orderingVars = ['oclass', 'ct', 'provid', 'manfe', 'etype', 'fuel',
                'powf', 'powt', 'ybf', 'ybt', 'sprcf', 'sprct',
                'pfidurl', 'q', 'orderby'];

        } else if (itemType == 'trailer') {
            orderingVars = ['oclass', 'ct', 'provid', 'manft', 'trmid',
                'lngf', 'lngt', 'ybf', 'ybt', 'sprcf', 'sprct',
                'pfidurl', 'q', 'orderby'];
        }
        var data = {};
        data['lg'] = jQuery('#yachtino-srchform-lg').val();
        data['itemtype'] = jQuery('#yachtino-srchform-itemtype').val();

        var postUrl = '';
        jQuery.each(orderingVars, function (key, val) {
            if (typeof jQuery('#yachtino-srchform-' + val).val() != 'undefined') {

                if (jQuery('#yachtino-srchform-' + val).is(':checkbox')) {
                    if (jQuery('#yachtino-srchform-' + val).prop('checked')) {
                        postUrl += '&' + val + '=' + jQuery('#yachtino-srchform-' + val).val();
                        data[val] = jQuery('#yachtino-srchform-' + val).val();
                    } else {
                        data[val] = '0';
                    }

                } else {
                    postUrl += '&' + val + '=' + jQuery('#yachtino-srchform-' + val).val();
                    data[val] = jQuery('#yachtino-srchform-' + val).val();
                }
            }
        });
        if (postUrl) {
            postUrl = postUrl.substr(1);
        }

        var redirBasic = window.location.href.split('?')[0];
        if (postUrl) {
            redirBasic += '?' + postUrl;
        }

        // send to server, not for shortcode
        if (jQuery('#yachtino-srchform-scode').val() == '0') {
            jQuery.ajax({
                type: 'POST',
                url: yachtino_public.ajax_url,
                data: {
                    action: 'yachtino_get_searchurl',
                    frm: JSON.stringify(data)
                },
                success: function (data) {
                    if (!data.redir) {
                        data.redir = redirBasic;
                    }
                    window.location.href = data.redir;
                },
                error: function (XMLHttpRequest) {
                    console.log(XMLHttpRequest);
                }
            });

        } else {
            window.location.href = redirBasic;
        }
    }

    jQuery('form#yachtino-searchform').on('change', function () {
        post_filter_options();
    });

    jQuery('form#yachtino-searchform').on('submit', function (event) {
        event.preventDefault();
        post_filter_options();
    });

    jQuery('form#yachtino-searchform a.yachtino-searchform-reset-link').on('click', function (e) {
        jQuery('form#yachtino-searchform select').each(function () {
            jQuery(this).find('option:eq(0)').prop('selected', true);
        });
        jQuery('#yachtino-srchform-q').val('');
        jQuery('form#yachtino-searchform').trigger('change');
    });

    // toggle search form  - show / hide (hidden on phone)
    jQuery('.yachtino-filters-headbox h2').on('click', function(ev) {
        if (jQuery('.yachtino-searchbox').css('display').toLowerCase() == 'none') {
            jQuery('.yachtino-searchbox').slideDown();
        } else {
            jQuery('.yachtino-searchbox').slideUp();
        }
    });

    function resizeSearchform()
    {
        var filterWidth = jQuery('#yachtino-searchform-left').width();
        jQuery('div.yachtino-articles.adv-filtering #yachtino-searchform-left div.yachtino-filters').css({'width': filterWidth});
    }

    // scroll search form on large devices
    if (jQuery('div.yachtino-articles.adv-filtering #yachtino-searchform-left div.yachtino-filters').length > 0) {

        let portrait = window.matchMedia('(orientation: portrait)');

        portrait.addEventListener('change', function(e) {
            // if(e.matches) { = is portrait
            resizeSearchform();
        });

        window.addEventListener('resize', function(e) {
            resizeSearchform();
        });


        var filter = jQuery('div.yachtino-articles.adv-filtering #yachtino-searchform-left div.yachtino-filters');
        if (filter === undefined) {
            return;
        }

        var topPadding = 15;
        jQuery(window).bind('scroll', function () {

            // after rotating device - look whether the form should scroll

            var filterWidth = jQuery('#yachtino-searchform-left').width();
            var filterHeight = filter.height() + 24;

            if (window.matchMedia('(max-width: 720px)').matches || (window.innerHeight - 200) < filterHeight) {
                if (filter.css('position') == 'fixed') {
                    filter.css({'position': 'relative', 'width': filterWidth, 'top': '0'});
                }
                console.log('yes return');
                return;
            }

            // define distance to the top
            var navigationFixedTop = jQuery('div.navigation-top.site-navigation-fixed').height();
            if (navigationFixedTop !== undefined) {
                var distanceTop = (navigationFixedTop + 45) + 'px';
            } else {
                var distanceTop = '115px';
            }

            var currentScrollTop = jQuery(window).scrollTop();
            var maxHeight = jQuery('div.yachtino-articles-list').height();
            if (typeof maxHeight == 'undefined') {
                var maxHeight = jQuery('div.yachtino-articles-tiles').height();
            }
            var filterPosBottom = maxHeight - currentScrollTop - filterHeight;

            if (currentScrollTop > 0 && filterPosBottom > 200) {
                // width can change after rotating device
                var filterWidth = jQuery('#yachtino-searchform-left').width();
                filter.css({'position': 'fixed', 'width': filterWidth, 'top': distanceTop});

            // change from fixed to absolute - element position stays relative to boat list and is being scrolled
            } else {
                if (filter.css('position') == 'fixed') {
                    filter.css({'position': 'absolute', 'width': filterWidth, 'top': currentScrollTop});
                }
            }
        });
    }

    /* ###### show special request form (insurance, financing, transport) ############# */
    jQuery('.js-yachtino-specrequest').on('click', function (e) {
        yachtinoShowLoaderGif();
        var data = {
            'rtype': (jQuery(this).attr('data-type')) ? jQuery(this).attr('data-type') : '',
            'itid': (jQuery(this).attr('data-id')) ? jQuery(this).attr('data-id') : '',
            'lg': (jQuery(this).attr('data-lg')) ? jQuery(this).attr('data-lg') : '',
        };

        // send to server and then to API
        jQuery.ajax({
            type: 'POST',
            url: yachtino_public.ajax_url,
            data: {
                action: 'yachtino_get_specrequest',
                req: JSON.stringify(data)
            },
            success: function (data) {
                yachtinoHideLoaderGif();

                // show form in light box
                yachtinoShowLightBoxWhole(1, data.html);
            },
            error: function (XMLHttpRequest) {
                console.log(XMLHttpRequest);
                yachtinoHideLoaderGif();
            }
        });
    });

    jQuery('body').on('submit', 'form#yachtino-specrequest-form', function(e) {
    // jQuery('form#yachtino-specrequest-form').on('submit', function (e) {
        e.preventDefault();

        // open overlay with waiting wheel
        yachtinoShowLoaderGif();

        var data = {
            /* user data */
            'cname': (jQuery('#yachtino-specreqfield-cname').val()) ? jQuery('#yachtino-specreqfield-cname').val() : '',
            'cemail': jQuery('#yachtino-specreqfield-cemail').val(),
            'cphone': (jQuery('#yachtino-specreqfield-cphone').val()) ? jQuery('#yachtino-specreqfield-cphone').val() : '',
            'caddress': (jQuery('#yachtino-specreqfield-caddress').val()) ? jQuery('#yachtino-specreqfield-caddress').val() : '',
            'cpostcode': (jQuery('#yachtino-specreqfield-cpostcode').val()) ? jQuery('#yachtino-specreqfield-cpostcode').val() : '',
            'ccity': (jQuery('#yachtino-specreqfield-ccity').val()) ? jQuery('#yachtino-specreqfield-ccity').val() : '',
            'ccountry': jQuery('#yachtino-specreqfield-ccountry').val(),
            'ctext': jQuery('#yachtino-specreqfield-ctext').val(),

            /* boat data */
            'btid': jQuery('#yachtino-specreqfield-btid').val(),
            'model': jQuery('#yachtino-specreqfield-model').val(),
            'yb': jQuery('#yachtino-specreqfield-yb').val(),
            'loa': jQuery('#yachtino-specreqfield-loa').val(),
            'price': jQuery('#yachtino-specreqfield-price').val(),

            'manuf': (jQuery('#yachtino-specreqfield-manuf') !== undefined) ? jQuery('#yachtino-specreqfield-manuf').val() : '',
            'material': (jQuery('#yachtino-specreqfield-material') !== undefined) ? jQuery('#yachtino-specreqfield-material').val() : '',
            'power': (jQuery('#yachtino-specreqfield-power') !== undefined) ? jQuery('#yachtino-specreqfield-power').val() : '',
            'mooring': (jQuery('#yachtino-specreqfield-mooring') !== undefined) ? jQuery('#yachtino-specreqfield-mooring').val() : '',
            'area': (jQuery('#yachtino-specreqfield-area') !== undefined) ? jQuery('#yachtino-specreqfield-area').val() : '',
            'use': (jQuery('#yachtino-specreqfield-use') !== undefined) ? jQuery('#yachtino-specreqfield-use').val() : '',
            'financ': (jQuery('#yachtino-specreqfield-financ') !== undefined) ? jQuery('#yachtino-specreqfield-financ').val() : '',
            'dur': (jQuery('#yachtino-specreqfield-dur') !== undefined) ? jQuery('#yachtino-specreqfield-dur').val() : '',
            'advPay1': (jQuery('#yachtino-specreqfield-advPay1') !== undefined) ? jQuery('#yachtino-specreqfield-advPay1').val() : '',
            'advPay2': (jQuery('#yachtino-specreqfield-advPay2') !== undefined) ? jQuery('#yachtino-specreqfield-advPay2').val() : '',
            'beam': (jQuery('#yachtino-specreqfield-beam') !== undefined) ? jQuery('#yachtino-specreqfield-beam').val() : '',
            'height': (jQuery('#yachtino-specreqfield-height') !== undefined) ? jQuery('#yachtino-specreqfield-height').val() : '',
            'weight': (jQuery('#yachtino-specreqfield-weight') !== undefined) ? jQuery('#yachtino-specreqfield-weight').val() : '',
            'fromPlace': (jQuery('#yachtino-specreqfield-fromPlace') !== undefined) ? jQuery('#yachtino-specreqfield-fromPlace').val() : '',
            'fromCt': (jQuery('#yachtino-specreqfield-fromCt') !== undefined) ? jQuery('#yachtino-specreqfield-fromCt').val() : '',
            'toPlace': (jQuery('#yachtino-specreqfield-toPlace') !== undefined) ? jQuery('#yachtino-specreqfield-toPlace').val() : '',
            'toCt': (jQuery('#yachtino-specreqfield-toCt') !== undefined) ? jQuery('#yachtino-specreqfield-toCt').val() : '',
            'prefDateFrom': (jQuery('#yachtino-specreqfield-prefDateFrom') !== undefined) ? jQuery('#yachtino-specreqfield-prefDateFrom').val() : '',

            'itid': jQuery('#yachtino-specreqfield-itid').val(),
            'itemtype': jQuery('#yachtino-specreqfield-itemtype').val(),
            'kind': jQuery('#yachtino-specreqfield-kind').val(),
            'lg': jQuery('#yachtino-specreqfield-lg').val(),
            'view': window.location.href,
            'domain': window.location.host,
        };

        if (!data['itid'] || !data['itemtype']) {
            return;
        }

        jQuery('.yachtino-request-error').addClass('yachtino-hidden');

        const required = ['cname', 'cemail', 'ccountry'];
        var hasError = false;
        jQuery.each(required, function(key, value) {
            if (data[value]) {
                jQuery('#yachtino-specreqfield-' + value).removeClass('yachtino-field-err');
                jQuery('#l-yachtino-specreqfield-' + value).removeClass('yachtino-label-err');
            } else {
                jQuery('#yachtino-specreqfield-' + value).addClass('yachtino-field-err');
                jQuery('#l-yachtino-specreqfield-' + value).addClass('yachtino-label-err');
                hasError = true;
            }
        });

        if (hasError) {
            jQuery('#yachtino-specrequest-msgbox-err').removeClass('yachtino-hidden');
            yachtinoHideLoaderGif();
            return;

        } else {
            jQuery('#yachtino-specrequest-msgbox-err').addClass('yachtino-hidden');
        }

        // send to server and then to API
        jQuery.ajax({
            type: 'POST',
            url: yachtino_public.ajax_url,
            data: {
                action: 'yachtino_send_specrequest',
                req: JSON.stringify(data)
            },
            success: function (data) {

                // some errors (eg. email in wrong format)
                if (typeof data.errors != 'undefined') {
                    var errMsg = '';
                    jQuery.each(data.errors.errMsg, function(key, value) {
                        errMsg += value + '<br>';
                    });
                    jQuery('#yachtino-specrequest-msgbox-err').html(errMsg);

                    jQuery.each(data.errors.errField, function(key, value) {
                        jQuery('#yachtino-specreqfield-' + key).addClass('yachtino-field-err');
                        jQuery('#l-yachtino-specreqfield-' + key).addClass('yachtino-label-err');
                    });
                    jQuery('#yachtino-specrequest-msgbox-err').removeClass('yachtino-hidden');

                // request sent
                } else {
                    jQuery('#yachtino-specrequest-msgbox-ok').removeClass('yachtino-hidden');
                    // hide form (not more requests for this boat)
                    jQuery('#yachtino-specrequest-form').addClass('yachtino-hidden');
                }
                jQuery('html,body').animate({scrollTop: jQuery('#yachtino-specrequest-box').offset().top},'slow');
                yachtinoHideLoaderGif();
            },
            error: function (XMLHttpRequest) {
                console.log(XMLHttpRequest);
                yachtinoHideLoaderGif();
            }
        });
    });


    /* ###### send contact request ############# */

    jQuery('form#yachtino-request-form').on('submit', function (e) {
        e.preventDefault();

        var data = {
            'cname': (jQuery('#yachtino-reqfield-cname').val()) ? jQuery('#yachtino-reqfield-cname').val() : '',
            'cemail': jQuery('#yachtino-reqfield-cemail').val(),
            'cphone': (jQuery('#yachtino-reqfield-cphone').val()) ? jQuery('#yachtino-reqfield-cphone').val() : '',
            'caddress': (jQuery('#yachtino-reqfield-caddress').val()) ? jQuery('#yachtino-reqfield-caddress').val() : '',
            'cpostcode': (jQuery('#yachtino-reqfield-cpostcode').val()) ? jQuery('#yachtino-reqfield-cpostcode').val() : '',
            'ccity': (jQuery('#yachtino-reqfield-ccity').val()) ? jQuery('#yachtino-reqfield-ccity').val() : '',
            'ccountry': jQuery('#yachtino-reqfield-ccountry').val(),
            'datef': (jQuery('#yachtino-reqfield-datef') !== undefined) ? jQuery('#yachtino-reqfield-datef').val() : '',
            'datet': (jQuery('#yachtino-reqfield-datef') !== undefined) ? jQuery('#yachtino-reqfield-datet').val() : '',
            'ctext': jQuery('#yachtino-reqfield-ctext').val(),

            'itid': jQuery('#yachtino-reqfield-itid').val(),
            'itemtype': jQuery('#yachtino-reqfield-itemtype').val(),
            'lg': jQuery('#yachtino-reqfield-lg').val(),
            'view': window.location.href,
            'domain': window.location.host,
        };

        if (!data['itid'] || !data['itemtype']) {
            return;
        }

        // open overlay with waiting wheel
        yachtinoShowLoaderGif();

        jQuery('.yachtino-request-error').addClass('yachtino-hidden');

        const required = ['cname', 'cemail', 'ccountry', 'ctext'];
        if (data['itemtype'] == 'cboat') {
            required.push('datef');
            required.push('datet');
        }
        var hasError = false;
        jQuery.each(required, function(key, value) {
            if (data[value]) {
                jQuery('#yachtino-reqfield-' + value).removeClass('yachtino-field-err');
                jQuery('#l-yachtino-reqfield-' + value).removeClass('yachtino-label-err');
            } else {
                jQuery('#yachtino-reqfield-' + value).addClass('yachtino-field-err');
                jQuery('#l-yachtino-reqfield-' + value).addClass('yachtino-label-err');
                hasError = true;
            }
        });

        if (hasError) {
            jQuery('#yachtino-request-msgbox-err').removeClass('yachtino-hidden');
            yachtinoHideLoaderGif();
            return;

        } else {
            jQuery('#yachtino-request-msgbox-err').addClass('yachtino-hidden');
        }

        // send to server and then to API
        jQuery.ajax({
            type: 'POST',
            url: yachtino_public.ajax_url,
            data: {
                action: 'yachtino_send_request',
                req: JSON.stringify(data)
            },
            success: function (data) {

                // some errors (eg. email in wrong format)
                if (typeof data.errors != 'undefined') {
                    var errMsg = '';
                    jQuery.each(data.errors.errMsg, function(key, value) {
                        errMsg += value + '<br>';
                    });
                    jQuery('#yachtino-request-msgbox-err').html(errMsg);

                    jQuery.each(data.errors.errField, function(key, value) {
                        jQuery('#yachtino-reqfield-' + key).addClass('yachtino-field-err');
                        jQuery('#l-yachtino-reqfield-' + key).addClass('yachtino-label-err');
                    });
                    jQuery('#yachtino-request-msgbox-err').removeClass('yachtino-hidden');

                // request sent
                } else {
                    jQuery('#yachtino-request-msgbox-ok').removeClass('yachtino-hidden');
                    // hide form (not more requests for this boat)
                    jQuery('#yachtino-request-form').addClass('yachtino-hidden');
                }
                jQuery('html,body').animate({scrollTop: jQuery('#yachtino-request-form-box').offset().top},'slow');
                yachtinoHideLoaderGif();
            },
            error: function (XMLHttpRequest) {
                console.log(XMLHttpRequest);
                yachtinoHideLoaderGif();
            }
        });
    });

});