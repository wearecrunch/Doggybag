![WP Instagram Widget](http://cloud.scott.ee/images/wp-instagram-widget.png)

# WP Instagram Widget

* Status: ✔ Active
* Contributors: [@scottsweb](http://twitter.com/scottsweb)
* Description: WP Instagram widget is a no fuss WordPress widget to showcase your latest Instagram pics.
* Author: [Scott Evans](http://scott.ee)
* Author URI: [http://scott.ee](http://scott.ee)
* License: GNU General Public License v2.0
* License URI: [http://www.gnu.org/licenses/gpl-2.0.html](http://www.gnu.org/licenses/gpl-2.0.html)

## About

WP Instagram widget is a no fuss WordPress widget to showcase your latest Instagram pics. It does not require you to provide your login details or sign in via oAuth.

The widget is built with the following philosophy:

* Use sensible and simple markup
* Provide no styles/css - it is up to you to style the widget to your theme and taste
* Cache where possible - filters are provided to adjust cache timings
* Require little setup - avoid oAuth for example

## Installation

To install this plugin:

* Upload the `wp-instagram-widget` folder to the `/wp-content/plugins/` directory
* Activate the plugin through the 'Plugins' menu in WordPress
* That's it!

Alternatively you can search for the plugin from your WordPress dashboard and install from there.

Visit [WordPress.org for a comprehensive guide](http://codex.wordpress.org/Managing_Plugins#Manual_Plugin_Installation) on in how to install WordPress plugins.

## Hooks & Filters

The plugin has five filters. The first allows you adjust that cache time for retrieving the images from Instagram:

```
add_filter('null_instagram_cache_time', 'my_cache_time');

function my_cache_time() {
	return HOUR_IN_SECONDS;
}
```

The second allows you to filter video results from the widget:

```
add_filter('wpiw_images_only', '__return_true');
```

The rest allow you to add custom classes to each list item, link or image:

```
add_filter( 'wpiw_item_class', 'my_instagram_class' );
add_filter( 'wpiw_a_class', 'my_instagram_class' );
add_filter( 'wpiw_img_class', 'my_instagram_class' );

function my_instagram_class( $classes ) {
	$classes = "instagram-image";
	return $classes;
}
```

In version 1.3 you also have two new hooks for adding custom output before and after the widget:

```
wpiw_before_widget
wpiw_after_widget
```

In version 1.4 and above you can also customise the image loop completely by creating a `parts/wp-instagram-widget.php` file in your theme.

## Frequently Asked Questions

...

## Changelog

####1.9.1
* Fix bug with some image URLs

####1.9
* WordPress.com VIP checked
* Updated CDN matching to ensure correct image sizes are served at all times
* Shortened the transient key - should mean we bump into the character limit less often
* Remove use of `extract`

####1.8.1
* Apologies for all the recent updates, all good things though!
* Update plugin text domain ready for translate.wordpress.org
* Introduce an 'original' image size if you want to use non-square originals
* Introduce new filter for `<ul>` class `wpiw_list_class`

####1.8
* Bring back image captions
* Fix small PHP error in 1.7

####1.7
* Bring back image sizes
* Use thumbnails that are square (originals are in the data returned and can be used)
* Remove @ from usernames

####1.6
* Compatibility with 4.3

####1.5.1
* Invalidate old transients

####1.5
* Remove null framework support
* Fix breaking change by Instagram whilst maintaining old style support
* Remove thumbnail size option

####1.4
* Introduce class filters
* Only set a transient if images are returned
* Optional template part for complete output control

####1.3.1
* Force lowercase usernames
* Correct hook name

####1.3
* Option to open links in new window
* Support for video items (with filter to disable this)
* New actions for adding custom output to the widget
* Support for https://
* Correctly escape attributes

####1.2.1
* Change transient name due to data change

####1.2
* Better error handling
* Encode emoji as they cause transient issues

####1.1
* Fix issue with Instagram feed
* Add composer.json

####1.0
* Initial release

