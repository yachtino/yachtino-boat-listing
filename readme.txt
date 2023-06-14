=== Yachtino boat listing ===
Contributors: yachtino
Tags: boats, yachts, yachting, yachtcharter, yachtall, happycharter, sale, brokerage, boating, charter, listing, yacht-charter
Requires at least: 6.0
Tested up to: 6.2
Stable tag: 1.4.3
Requires PHP: 8.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Display your boats and yachts for sale from yachtall.com or yacht charter offers from happycharter.com in your own website.

== Description ==

Display your boats and yachts for sale from yachtall.com or yacht charter offers from happycharter.com in your own website.
You need to have an account on yachtall.com or happycharter.com to use this plugin. Then you can integrate your boats into your website with a few clicks.

== Installation ==

* Install the plugin and activate it.
* Contact Yachtino Support to get your API Key and Site-ID
* Go to WP-Admin "Yachtino" and enter your API-Key and Site-ID
* Go to "Yachtino->Add module" and create your first module - very first for boat detail view.
* Go to "Yachtino->Add page" and create your first page with the module for boat detail view.
* Go to "Yachtino->Add module" and create your first module for boat list. For that you need a previous created page for detail view.
* Go to "Yachtino->Add page" and create your page for boat list. That URL you can put eg. into your site navigation.
* That's it!

== Changelog ==

= 1.4.3 =
Changed: the length of unique database field "name" changed from 255 to 100 (tables yachtino_modules and yachtino_route_master)
Fixed: no reset was possible for search form in special URLs with fixed filters

= 1.4.2 =
Fixed: wrong language for special requests (insurance, transport...)

= 1.4.1 =
Fixed: bug migrating from form plugin shiplisting

= 1.4.0 =
New: Possibility to define the place for search form in the boat list (at the top or on the left)
Fixed: Layout boat list - tiles and search form

= 1.3.1 =
Fixed: layout problem in Chrome for request form

= 1.3.0 =
New: Sending insurance, financing and transport request

= 1.2.2 =
Fixed: Bug in checking compatibility with older PHP versions when running with PHP 7

= 1.2.1 =
New: Meta tags for rel hreflang (not only for language switcher) for Polylang plugin

= 1.2.0 =
Change: Field in search form possible even if set in filters
New: Forward page to other route if search criteria overwrite the module filters

= 1.1.1 =
New: Filters for boat list - custom filters possible

= 1.0.1 =
Change: Slug - old: yachtino, new: yachtino-boat-listing
Change: Complete translation

= 1.0.0 =
Initial version
