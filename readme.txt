=== Reaction Buttons ===
Tags: button, comment, posts
Requires at least: 2.9.1
Tested up to: 2.9.2
Stable tag: 0.9.2

Adds Buttons for very simple and fast feedback to your post. Inspired by Blogger.

== Description ==

This addon adds buttons below your posts to make it easy to get reactions to the post, but without the hassle of writing a whole comment. It makes it easier for the reader to interact with you. The buttons are configurable (how many, what text) and simply are counters to how often they were clicked.

The idea is inspired by a [blogger feature](http://bloggerindraft.blogspot.com/2008/08/new-feature-reactions.html) and since it's been my first addon, I borrowed the structure from [sociable](http://wordpress.org/extend/plugins/sociable/).

== Installation ==

Nothing fancy, just like any wordpress addon:

1. Upload and unzip the plugin to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Optionally configure the plugin in the settings tab

== Frequently Asked Questions ==

= Did I get any questions so far? =

No. :-)

== Screenshots ==

1. Shows a german default installation with Reaction Buttons and some clicks on them.
2. Shows the sidebar widget with some dummy data.

== Changelog ==

= 0.9.3 (beta) =
* added a widget, that shows the posts with the most clicks for each button.

= 0.9.2 =
* Added the possibility to activate cookies in the backend, so that you can only vote once on that browser. (Anyone with malicios intent can circumvent that pretty easily of course...)

= 0.9.1 =
* fixed issues with spaces
* fixed issues with apostrophs
* some changes in the settings area

= 0.9 =
* First public release.

== Restrictions ==
* There cannot be spaces in html classes, so the plugin wouldn't work if there would be buttons named "great article" *and* "great___article", because it converts the spaces into three underscores.