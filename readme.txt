=== Yachtino boat listing ===
Contributors: yachtino
Tags: boats, yachts, yachting, yachtcharter, yachtall, happycharter, sale, brokerage, boating, charter, listing, yacht-charter
Requires at least: 6.0
Tested up to: 6.7
Stable tag: 1.5.7
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
* Go to "Yachtino->Add module" and create your first module for boat list. For that you need a previously created page for detail view.
* Go to "Yachtino->Add page" and create your page for boat list. That URL you can put eg. into your site navigation.
* That's it!

== Changelog ==

= 1.5.7 =
28 November 2024
Fixed: Searching boats for boat manufacturer

= 1.5.6 =
17 June 2024
Fixed: Security lack for form sending for boat insurance/financing

= 1.5.5 =
19 January 2024
Fixed: Boat list - tiles for charter boats, displaying of charter price whole visible if no discounts.
Changed: You can get sent search criteria from outside, also all sent empty params.

= 1.5.4 =
17 January 2024
New: possible to build a boat list with boats of a single user (for portals with more users)
Change: better catching of old boat IDs, possible to call the new boat ID from outside (if integrated as shortcode)
Fixed: Search form - field sailboat/motorboat was not cleared if the URL contained "sailboat" - wrong routing

= 1.5.3 =
10 January 2024
New: for boats for sale - new fields in boat data: price for auction, price for boat sharing

= 1.5.2 =
26 December 2023
Change: set page number into page title tag and H1 tag if it is not first page (boat list, for SEO)

= 1.5.1 =
05 December 2023
New: possiblity to set H1 tag also for shortcode
Change: set H1 tag in module, no longer in page

= 1.5.0 =
05 December 2023
Fixed: possiblity to set an own page/URL for boat detail view, different URLs (one per language) possible
New: function for calling meta data from API before calling shortcode (prepare head for page with shortcode)
New: possibility to put own criteria to calling shortcode for boat list: [yachtino_module lg="en" name="your_module_name" criteria="btid=1"]

= 1.4.6 =
17 September 2023
New: boat list, a 3-columns-grid possible, not only 4 columns
New: possibility to set an own page/URL for boat detail view, not only page/URL from the plugin

= 1.4.5 =
15 Juni 2023
Fixed: if $_SERVER['REMOTE_ADDR'] is not set

= 1.4.4 =
15 Juni 2023
Fixed: if $_SERVER['REQUEST_URI'] is not set

= 1.4.3 =
8 Juni 2023
Changed: the length of unique database field "name" changed from 255 to 100 (tables yachtino_modules and yachtino_route_master)
Fixed: no reset was possible for search form in special URLs with fixed filters

= 1.4.2 =
17 May 2023
Fixed: wrong language for special requests (insurance, transport...)

= 1.4.1 =
6 May 2023
Fixed: bug migrating from form plugin shiplisting

= 1.4.0 =
6 May 2023
New: Possibility to define the place for search form in the boat list (at the top or on the left)
Fixed: Layout boat list - tiles and search form

= 1.3.1 =
6 May 2023
Fixed: layout problem in Chrome for request form

= 1.3.0 =
6 May 2023
New: Sending insurance, financing and transport request

= 1.2.2 =
18 April 2023
Fixed: Bug in checking compatibility with older PHP versions when running with PHP 7

= 1.2.1 =
14 April 2023
New: Meta tags for rel hreflang (not only for language switcher) for Polylang plugin

= 1.2.0 =
7 April 2023
Change: Field in search form possible even if set in filters
New: Forward page to other route if search criteria overwrite the module filters

= 1.1.1 =
5 April 2023
New: Filters for boat list - custom filters possible

= 1.0.1 =
4 April 2023
Change: Slug - old: yachtino, new: yachtino-boat-listing
Change: Complete translation

= 1.0.0 =
4 April 2023
Initial version
