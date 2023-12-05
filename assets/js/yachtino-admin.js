/**
* Yachtino boat listing - WordPress plugin.
* @author      Yachtino
* @package     yachtino
*/
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
jQuery('#yachtino-lightbox' + boxId).removeClass('yachtino-hidden');
yachtinoResizeLightBox(boxId);
}
function yachtinoResizeLightBox(boxId)
{
var wX = jQuery(window).scrollLeft();
var wWdt = jQuery(window).width();
var bWdt = jQuery('#yachtino-lightbox' + boxId).width();
if (bWdt >= wWdt || wWdt < 440) {
var newWdt = wX;
jQuery('#yachtino-lightbox' + boxId).width(wWdt);
} else {
var newWdt = wX + ((wWdt - bWdt) / 2);
newWdt -= 300;
if (newWdt < 0) {
newWdt = 0;
}
}
console.log('lightbox: wX = ' + wX + ', wWdt = ' + wWdt + ', bWdt = ' + bWdt + ', newWdt = ' + newWdt);
jQuery('#yachtino-lightbox' + boxId).css('left', newWdt); 
var wY = jQuery(window).scrollTop();
var wHgt = jQuery(window).height();
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
jQuery('#yachtino-lightbox' + boxId).css('top', newHgt); }
function yachtinoCloseLightBox(boxId)
{
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
function yachtinoShowLoaderGif() {
jQuery('#yachtino-overlay-wait').removeClass('yachtino-hidden');
jQuery('#yachtino-waitbox').removeClass('yachtino-hidden');
}
function yachtinoHideLoaderGif() {
jQuery('#yachtino-overlay-wait').addClass('yachtino-hidden');
jQuery('#yachtino-waitbox').addClass('yachtino-hidden');
}
jQuery(function() {
jQuery('#yachtino-lightbox-closex1, #yachtino-lightOkButton1').on('click', function() {
yachtinoCloseLightBox(1);
});
jQuery('body').on('click', '.yachtino-js-closeLbox1', function() {
yachtinoCloseLightBox(1);
});
});
/**
* Yachtino boat listing - WordPress plugin.
* @author      Yachtino
* @package     yachtino
*/
function openAddModule()
{
var cont = jQuery('#yachtino-adding-box').html();
yachtinoIdContentLightBox[1] = 'yachtino-adding-box';
jQuery('#yachtino-adding-box').html('');
yachtinoShowLightBoxWhole(1, cont);
}
jQuery(document).ready(function () {
function changeLgsForModule()
{
var x = jQuery('#yachtino-moduleid option:selected').attr('data-addpath');
var itype = jQuery('#yachtino-moduleid option:selected').attr('data-itemtype');
if (typeof itype == 'undefined') {
itype = jQuery('#itemtype').val();
}
jQuery('.yachtino-js-add-to-path').text(x);
if (x == '/') {
jQuery('.yachtino-placeholder-box').addClass('yachtino-hidden');
} else {
jQuery('.yachtino-js-one-pholder-model').removeClass('yachtino-hidden');
jQuery('.yachtino-js-one-pholder-country').removeClass('yachtino-hidden');
jQuery('.yachtino-js-one-pholder-name').addClass('yachtino-hidden');
jQuery('.yachtino-js-one-pholder-yearBuilt').addClass('yachtino-hidden');             jQuery('.yachtino-js-one-pholder-manufacturer').addClass('yachtino-hidden');             jQuery('.yachtino-js-one-pholder-boatType').addClass('yachtino-hidden');
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
jQuery('.yachtino-js-delete-item').on('click', function () {
var cont = jQuery('#yachtino-deleting-box').html();
yachtinoIdContentLightBox[1] = 'yachtino-deleting-box';
jQuery('#yachtino-deleting-box').html('');
var id = jQuery(this).attr('data-id');
var p = id.split('-');
yachtinoShowLightBoxWhole(1, cont);
if (p[0] == 'page') {             jQuery('#masterid').val(p[1]);
} else {             jQuery('#moduleid').val(p[1]);
}
jQuery('#yachtino-placeholder-name').text(jQuery(this).attr('data-name'));
});
jQuery('#yachtino-add-module').on('click', function () {
openAddModule();
});
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
jQuery('#layout_list, #layout_tiles').on('click', function () {
var isTiles = jQuery('#layout_tiles').prop('checked');
if (isTiles) {
jQuery('#yachtino-number-columns').removeClass('yachtino-hidden');
} else {
jQuery('#yachtino-number-columns').addClass('yachtino-hidden');
}
});
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
