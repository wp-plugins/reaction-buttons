=== Reaction Buttons ===
Tags: feedback, polls, button, comment
Requires at least: 2.9.1
Tested up to: 3.0
Stable tag: 1.0

Adds Buttons for very simple and fast feedback to your post. Inspired by Blogger.

== Description ==

This addon adds buttons below your posts (or somewhere else) to make it easy to get reactions to the post, but without the hassle of writing a whole comment. It makes it easier for the reader to interact with you. The buttons are configurable (how many, what text, position) and simply are counters to how often they were clicked. There is also a widget and a shortcode to show the top x posts with the most clicks for each button.

The idea is inspired by a [blogger feature](http://bloggerindraft.blogspot.com/2008/08/new-feature-reactions.html) and since it's been my first addon, I borrowed the structure from [sociable](http://wordpress.org/extend/plugins/sociable/).

== Installation ==

Nothing fancy, just like any wordpress addon:

1. Upload and unzip the plugin to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Optionally configure the plugin in the settings tab

You can also use the widget to show the top x posts with the most clicks for each button in your sidebar. Or alternatively you can use the shortcode [reaction_buttons_most_clicks] to insert the same information somewhere in your post. (Takes limit_posts as argument to set up how many posts per button should be shown: Default: [reaction_buttons_most_clicks limit_posts=3])

== Frequently Asked Questions ==

= Did I get any questions so far? =

No. :-)

== Screenshots ==

1. Shows a german default installation with Reaction Buttons and some clicks on them.
2. Shows the sidebar widget with some dummy data.

== Changelog ==
= 1.1 =
* added reaction_buttons_click_count($post_id) to include number of reactions per post.

= 1.0 =
* small changes for 3.0
* tested with 3.0rc3

= 0.9.9.2 =
* added code for a second widget and statistics page: Show the top x posts with the most overall clicks.

= 0.9.9.1 =
* added code for a statistics page in the backend from Fábio Silva

= 0.9.9 =
* added a shortcode for the widget (show top x posts...)

= 0.9.8 =
* added the possibility to deactivate reaction buttons based on categories.

= 0.9.7 =
* fixed a small error that prevented the ajax update in buttons with more than one whitespace.

= 0.9.6 =
* hopefully solves the [problem with mod_security](http://blog.jl42.de/reaction-buttons/comment-page-1/#comment-812) due to a filename with "cookie" in it... *sigh* 

= 0.9.5.1 =
* removed the possibility to multi vote by clicking really fast (before the ajax response came in)

= 0.9.5 =
* added the shortcode [reaction_buttons] (can be activated in the config)
* added the possibility to add the buttons after the post, above the post, via shortcode and directly in your theme by using a php function. Got the idea for those options from [TYCB](http://wordpress.org/extend/plugins/thanks-you-counter-button/), thanks! :)
* fixed a small settings error

= 0.9.4.1 =
* fixed a bug in the widget, sorting was screwed

= 0.9.4 =
* bugfixes and cleanup on the widget
* small cleanups in the settings

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
* Also the addon (at least the ajax part) doesn't work with certain special chars like exclamation marks. If thats a problem for someone I can see if there is a way to fix it, for now I'm all right with it. :)
