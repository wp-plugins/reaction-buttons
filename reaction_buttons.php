<?php
  /*
   Plugin Name: Reaction Buttons
   Plugin URI: http://blog.jl42.de/reaction-buttons/
   Description: Adds Buttons for very simple and fast feedback to your post. Inspired by Blogger.
   Version: 0.9.2
   Author: Jakob Lenfers
   // Author URI: http://blog.jl42.de

   I used the sociable plugin as template.

   Copyright 2010-present Jakob Lenfers <jakob@drss.de>

   This program is free software; you can redistribute it and/or modify
   it under the terms of the GNU General Public License as published by
   the Free Software Foundation; either version 2 of the License, or
   (at your option) any later version.

   This program is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
   GNU General Public License for more details.

   You should have received a copy of the GNU General Public License
   along with this program; if not, write to the Free Software
   Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
  */

// Determine the location
$reaction_buttons_plugin_path = WP_CONTENT_URL.'/plugins/'.plugin_basename(dirname(__FILE__)).'/';

/**
 * Quote the button name, so that JQuery works
 */
function prepare_js_jl($str) {
	return str_replace("'", "\\\\'", esc_js(trim($str)));
}


/**
 * Replace spaces with three underscores for class names
 */
function prepare_attr_jl($str) {
	return esc_attr(str_replace(" ", "___", stripslashes(trim($str))));
}


/**
 * Returns the reaction buttons.
 */
function reaction_buttons_html() {
	if (get_post_meta(get_the_ID(),'_reaction_buttons_off',true) or !get_option(reaction_buttons_activate)) {
		return "";
	}
	
	$post_id = get_the_ID();
	if(get_option(reaction_buttons_usecookies)){
		$json = stripslashes($_COOKIE["reaction_buttons_" . $post_id]);
		$cookie = json_decode($json, true);
	}
	if(!is_array($cookie)) $cookie = array();

	// Start preparing the output
	$html = "\n<div id='reaction_buttons_post" . $post_id . "' class='reaction_buttons'>\n";

	 // If a tagline is set, display it above the buttons
	 $tagline = get_option("reaction_buttons_tagline");
	 if ($tagline != "") {
		 $html .= '<div class="reaction_buttons_tagline">';
		 $html .= htmlspecialchars($tagline);
		 $html .= "</div>";
	 }

	// get the buttons and strip whitespaces
	$buttons = explode(",", preg_replace("/,\s+/", ",", get_option('reaction_buttons_button_names')));
	
	// print every button
	foreach($buttons as $button){
		$clean_button = stripslashes(trim($button));
		$count = intval(get_post_meta(get_the_ID(), "_reaction_buttons_" . $clean_button, true));
		$html .= "<span class='reaction_button_" . prepare_attr_jl($button) . "_count";
		if (array_key_exists(addslashes($clean_button), $cookie) && $cookie[addslashes($clean_button)]) {
			$html .= " voted'>";
		}
		else {
			$html .= "' onclick=\"reaction_buttons_increment_button_ajax('" . get_the_ID() . "', '" .
			prepare_js_jl($button) . "');\"'>";
		}
		$html .= $clean_button . "&nbsp;<span>(" . $count . ")</span></span> ";
	}
	$html .= "</div>\n";

	return $html;
}


/**
 * Hook the_content to output html if we should display on any page
 */
$reaction_buttons_contitionals = get_option('reaction_buttons_conditionals');
if (is_array($reaction_buttons_contitionals) and in_array(true, $reaction_buttons_contitionals)) {
	add_filter('the_content', 'reaction_buttons_display_hook');
	add_filter('the_excerpt', 'reaction_buttons_display_hook');
	
	/**
	 * Loop through the settings and check whether Sociable should be outputted.
	 */
	function reaction_buttons_display_hook($content='') {
		$conditionals = get_option('reaction_buttons_conditionals');
		if ((is_home()	   and $conditionals['is_home']) or
		    (is_single()   and $conditionals['is_single']) or
		    (is_page()	   and $conditionals['is_page']) or
		    (is_category() and $conditionals['is_category']) or
		    (is_tag()	   and $conditionals['is_tag']) or
		    (is_date()	   and $conditionals['is_date']) or
		    (is_author()   and $conditionals['is_author']) or
		    (is_search()   and $conditionals['is_search'])) {
			$content .= reaction_buttons_html();
		}
		return $content;
	}
 }


/**
 * Set the default settings on activation on the plugin.
 */
function reaction_buttons_activation_hook() {
	return reaction_buttons_restore_config(false);
}
register_activation_hook(__FILE__, 'reaction_buttons_activation_hook');


/**
 * Add the Sociable menu to the Settings menu
 * @param boolean $force if set to true, force updates the settings.
 */
function reaction_buttons_restore_config($force=false) {

	if ($force or !is_array(get_option('reaction_buttons_conditionals')))
		update_option('reaction_buttons_conditionals',
		              array('is_home' => True,
		                    'is_single' => True,
		                    'is_page' => True,
		                    'is_category' => True,
		                    'is_tag' => True,
		                    'is_date' => True,
		                    'is_search' => False,
		                    'is_author' => False,
		                    ));

	if ( $force or !( get_option('reaction_buttons_activate')) ) {
		update_option('reaction_buttons_activate', true);
	}
	
	if ( $force or !( get_option('reaction_buttons_tagline')) ) {
		update_option('reaction_buttons_tagline', "What do you think of this post?");
	}

	if ( $force or !( get_option('reaction_buttons_button_names')) ) {
		update_option('reaction_buttons_button_names', "Awesome, Interesting, Useful, Boring, Sucks");
	}

	if ( $force or !( get_option('reaction_buttons_usecss')) ) {
		update_option('reaction_buttons_usecss', true);
	}

	if ( $force or !( get_option('reaction_buttons_usecookies')) ) {
		update_option('reaction_buttons_usecookies', false);
	}
}

/**
 * Removes button data that isn't used anymore
 */
function reaction_buttons_clean_old_button_names(){
	global $wpdb;
	$table = $wpdb->prefix . "postmeta";
	
	$buttons = explode(",", preg_replace("/,\s+/", ",", get_option('reaction_buttons_button_names')));
	$delete_meta_ids = array();

	// get the Reaction Buttons datat out of the db
	$reactions = $wpdb->get_results("SELECT meta_id,meta_key FROM $table where meta_key like '_reaction_buttons%'");
	
	// check what records can be deleted
	foreach ($reactions as $reaction){
		if(!in_array(substr($reaction->meta_key, 18), $buttons)){
			$delete_meta_ids[] = $reaction->meta_id;
		}
	}

	// delete those records from the db
	if ( !empty( $delete_meta_ids ) ) {
		$wpdb->query("DELETE FROM $table where meta_id IN (" . implode(",", $delete_meta_ids) .");");
	}
	reaction_buttons_message(__("Removed unused data.", 'reaction_buttons'));
}


/**
 * If the user has the (default) setting of using the reaction_buttons CSS, load it.
 */
function reaction_buttons_css() {
	if (get_option('reaction_buttons_usecss') == true) {
		global $reaction_buttons_plugin_path;
		wp_enqueue_style('reaction_buttons_css',$reaction_buttons_plugin_path.'reaction_buttons.css'); 
	}
}
add_action('wp_print_styles', 'reaction_buttons_css');

/**
 * Reaction Buttons javascript. Uses ajax to get a vote, disable the
 * possibility to vote and refresh the counter.
 */
function reaction_buttons_js_header() {
	$nonce = wp_create_nonce( 'reaction_buttons' );
	?>
	<script	type='text/javascript'><!--
	function prepare_attr_jl(str) {
		return str.replace(" ", "___");
	}
	function reaction_buttons_increment_button_ajax(post_id, button){
			jQuery.ajax({
				type: "post",url: "<?php bloginfo( 'wpurl' ); ?>/wp-admin/admin-ajax.php", dataType: 'json',
					data: { action: 'reaction_buttons_increment_button_php', post_id: post_id, button: button, _ajax_nonce: '<?php echo $nonce; ?>' },
					success: function(data){
						if(data['cookie']){
							// Set the cookie, which expires after 3 days. Hope that helps to circumvent
							// the problem that browsers only have to save 30 cookies per domain.
							jQuery.cookie("reaction_buttons_" + post_id, JSON.stringify(data['cookie']), {expires: 3});
						}
						jQuery("#reaction_buttons_post" + post_id + " span.reaction_button_" + prepare_attr_jl(button) + "_count").removeAttr('onclick');
						jQuery("#reaction_buttons_post" + post_id + " span.reaction_button_" + prepare_attr_jl(button) + "_count span").html("("+data['count']+")");
						jQuery("#reaction_buttons_post" + post_id + " span.reaction_button_" + prepare_attr_jl(button) + "_count").addClass('voted');
					}
			});
		}
	--></script>
	<script type='text/javascript' src='<?php echo WP_CONTENT_URL.'/plugins/'.plugin_basename(dirname(__FILE__)) . '/jquery.cookie.js'; ?>'></script>
	<?php
	
}

// add the javascript stuff
wp_enqueue_script("jquery");
wp_enqueue_script("json2");
add_action('wp_head', 'reaction_buttons_js_header' );
add_action('wp_ajax_reaction_buttons_increment_button_php', 'reaction_buttons_increment_button_php', 1, 2);
add_action('wp_ajax_nopriv_reaction_buttons_increment_button_php', 'reaction_buttons_increment_button_php', 1, 2);


/**
 * Increments the clicked button. Gets called through ajax.
 */
function reaction_buttons_increment_button_php(){
	check_ajax_referer("reaction_buttons");

	if(!$_POST['post_id'] || !$_POST['button']) die();
	$post_id = intval($_POST['post_id']);
	$button = stripslashes($_POST['button']);
	$result = array();

	// get all the buttons, stripped of whitespaces
	$buttons = explode(",", preg_replace("/,\s/", ",", get_option('reaction_buttons_button_names')));
	// if the ajax request submitted a button which isn't in the config, don't do anything
	if(!in_array($button, $buttons)) die();

	// get old button value and update it
	$current = intval(get_post_meta($post_id, "_reaction_buttons_" . stripslashes($button), true));
	update_post_meta($post_id, "_reaction_buttons_" . stripslashes($button), ++$current);

	if(get_option(reaction_buttons_usecookies)){
		if ( $_COOKIE["reaction_buttons"] ) {
			$json = stripslashes($_COOKIE["reaction_buttons_" . $post_id]);
			$cookie = json_decode($json, true);
			if(!is_array($cookie)) $cookie = array();
		}
		else {
			$cookie = array();
		}
		
		$cookie[$button] = true;
		$result['cookie'] = $cookie;
	}
	
	$result['count'] = $current;

	// return the new value, so that the js can insert it into the blog
	echo json_encode($result);
	die();
}


/**
 * Update message, used in the admin panel to show messages to users.
 */
function reaction_buttons_message($message) {
	echo "<div id=\"message\" class=\"updated fade\"><p>$message</p></div>\n";
}


/**
 * Displays a checkbox that allows users to disable Reaction Buttons on a
 * per post or page basis.
 */
function reaction_buttons_meta() {
	global $post;
	$reaction_buttons_off = false;

	if ( get_post_meta($post->ID, '_reaction_buttons_off', true) ) {
		$reaction_buttons_off = true;
	} 
	?>
		<input type="checkbox" id="reaction_buttons_off" name="reaction_buttons_off" <?php checked($reaction_buttons_off); ?>/>
		<label for="reaction_buttons_off"><?php _e('Disable Reaction Buttons?','reaction_buttons') ?></label>
	<?php
					                   }

/**
 * Add the checkbox defined above to post and page edit screens.
 */
function reaction_buttons_meta_box() {
	add_meta_box('reaction_buttons','Reaction Buttons','reaction_buttons_meta','post','side');
	add_meta_box('reaction_buttons','Reaction Buttons','reaction_buttons_meta','page','side');
}
add_action('admin_menu', 'reaction_buttons_meta_box');


/**
 * If the post is inserted, set the appropriate state for the Reaction Buttons off setting.
 */
function reaction_buttons_insert_post($pID) {
	if ( isset($_POST['reaction_buttons_off']) ) {
		if ( !get_post_meta($pID, '_reaction_buttons_off',true) ) {
			add_post_meta($pID, '_reaction_buttons_off', true, true);
		}
	} else {
		if ( get_post_meta($pID, '_reaction_buttons_off',true) ) {
			delete_post_meta($pID, '_reaction_buttons_off');
		}
	}
}
add_action('wp_insert_post', 'reaction_buttons_insert_post');


/**
 * Add the Reaction Buttons menu to the Settings menu
 */
function reaction_buttons_admin_menu() {
	add_options_page('Reaction buttons', 'Reaction Buttons', 8, 'reaction_buttons', 'reaction_buttons_submenu');
}
add_action('admin_menu', 'reaction_buttons_admin_menu');


/**
 * Displays the Reaction Button admin menu
 */
function reaction_buttons_submenu() {
	global $reaction_buttons_plugin_path;

	// restore the default config
	if (isset($_REQUEST['restore']) && $_REQUEST['restore']) {
		check_admin_referer('reaction_buttons_config');
		reaction_buttons_restore_config(true);
		reaction_buttons_message(__("Restored all settings to defaults. You might want to also delete unused data now.", 'reaction_buttons'));
	}
	// deletes unused data
	else if (isset($_REQUEST['remove']) && $_REQUEST['remove']) {
		reaction_buttons_clean_old_button_names();
	}
	// saves the settings from the page
	else if (isset($_REQUEST['save']) && $_REQUEST['save']) {
		check_admin_referer('reaction_buttons_config');
		$error = "";

		// save the different settings (boolean, text, array of bool)
		foreach ( array('activate', 'usecss', 'usecookies') as $val ) {
			if ( isset($_POST[$val]) && $_POST[$val] )
				update_option('reaction_buttons_'.$val,true);
			else
				update_option('reaction_buttons_'.$val,false);
		}

		foreach ( array('tagline') as $val ) {
			if ( !$_POST[$val] )
				update_option( 'reaction_buttons_'.$val, '');
			else
				update_option( 'reaction_buttons_'.$val, $_POST[$val] );
		}
		
		if ( !$_POST['button_names'] ) {
			update_option( 'reaction_buttons_button_names', '');
		}
		else {
			if(strpos($_POST['button_names'], '"')){
				$error .= __('Error: Button Names cannot contain quotes (").', 'reaction_buttons') . "<br />";
			}
			else {
				update_option( 'reaction_buttons_button_names', $_POST['button_names'] );
			}
		}
	
		
		$conditionals = Array();
		if (!$_POST['conditionals'])
			$_POST['conditionals'] = Array();
		
		$curconditionals = get_option('reaction_buttons_conditionals');
		if (!array_key_exists('is_feed',$curconditionals)) {
			$curconditionals['is_feed'] = false;
		}
		foreach($curconditionals as $condition=>$toggled)
			$conditionals[$condition] = array_key_exists($condition, $_POST['conditionals']);
			
		update_option('reaction_buttons_conditionals', $conditionals);

		// done saving
		if ( $error ) {
			$error = $error . __("Some settings couldn't be saved. More details in the error message below:<br />", 'reaction_buttons');
			reaction_buttons_message($error);
		}
		else {
			reaction_buttons_message(__("Saved changes.", 'reaction_buttons'));
		}
	}
	
	/**
	 * Display options.
	 */
	?>
	<form action="<?php echo attribute_escape( $_SERVER['REQUEST_URI'] ); ?>" method="post">
	<?php
		if ( function_exists('wp_nonce_field') )
			 wp_nonce_field('reaction_buttons_config');
	?>
		<div class="wrap">
			<?php screen_icon(); ?>
			<h2><?php _e("Reaction Buttons Options", 'reaction_buttons'); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row" valign="top">
						<?php _e("Show Reaction Buttons", "reaction_buttons"); ?>
					</th>
					<td>
						<input type="checkbox" name="activate" <?php checked( get_option('reaction_buttons_activate'), true ) ; ?> />
					</td>
				</tr>	
				<tr>
					<th scope="row" valign="top">
						<?php _e("Tagline:", "reaction_buttons"); ?>
					</th>
					<td>
						<?php _e("Text above the reaction buttons.", 'reaction_buttons'); ?><br/>
						<input size="80" type="text" name="tagline" value="<?php echo attribute_escape(stripslashes(get_option('reaction_buttons_tagline'))); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row" valign="top">
						<?php _e("Reaction Buttons:", "reaction_buttons"); ?>
					</th>
					<td>
						<?php _e("Reaction Button Titles, comma seperated.", 'reaction_buttons'); ?><br/>
						<input size="80" type="text" name="button_names" value="<?php echo attribute_escape(stripslashes(get_option('reaction_buttons_button_names'))); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row" valign="top">
						<?php _e("Position:", "reaction_buttons"); ?>
					</th>
					<td>
						<?php _e("Chose the pages on which to display the reaction buttons.", 'reaction_buttons'); ?><br/>
						<br/>
						<?php
							// Load conditions under which Reaction Buttons displays
							$conditionals	= get_option('reaction_buttons_conditionals');
						?>
						<input type="checkbox" name="conditionals[is_home]"<?php checked($conditionals['is_home']); ?> /> <?php _e("Front page of the blog", 'reaction_buttons'); ?><br/>
						<input type="checkbox" name="conditionals[is_single]"<?php checked($conditionals['is_single']); ?> /> <?php _e("Individual blog posts", 'reaction_buttons'); ?><br/>
						<input type="checkbox" name="conditionals[is_page]"<?php checked($conditionals['is_page']); ?> /> <?php _e('Individual WordPress "Pages"', 'reaction_buttons'); ?><br/>
						<input type="checkbox" name="conditionals[is_category]"<?php checked($conditionals['is_category']); ?> /> <?php _e("Category archives", 'reaction_buttons'); ?><br/>
						<input type="checkbox" name="conditionals[is_tag]"<?php checked($conditionals['is_tag']); ?> /> <?php _e("Tag listings", 'reaction_buttons'); ?><br/>
						<input type="checkbox" name="conditionals[is_date]"<?php checked($conditionals['is_date']); ?> /> <?php _e("Date-based archives", 'reaction_buttons'); ?><br/>
						<input type="checkbox" name="conditionals[is_author]"<?php checked($conditionals['is_author']); ?> /> <?php _e("Author archives", 'reaction_buttons'); ?><br/>
						<input type="checkbox" name="conditionals[is_search]"<?php checked($conditionals['is_search']); ?> /> <?php _e("Search results", 'reaction_buttons'); ?><br/>
					</td>
				</tr>
				<tr>
					<th scope="row" valign="top">
						<?php _e("Use CSS:", "reaction_buttons"); ?>
					</th>
					<td>
						<input type="checkbox" name="usecss" <?php checked( get_option('reaction_buttons_usecss'), true ); ?> /> <?php _e("Use the Reaction Buttons stylesheet?", "reaction_buttons"); ?>
					</td>
				</tr>
				<tr>
					<th scope="row" valign="top">
						<?php _e("Use Cookies:", "reaction_buttons"); ?>
					</th>
					<td>
						<input type="checkbox" name="usecookies" <?php checked( get_option('reaction_buttons_usecookies'), true ); ?> /> <?php _e("Use Cookies to make it harder to vote twice?", "reaction_buttons"); ?>
					</td>
				</tr>
				<tr>
					<td>&nbsp;</td>
					<td>
						<span class="submit"><input name="save" value="<?php _e("Save Changes", 'reaction_buttons'); ?>" type="submit" /></span>
						<span class="submit"><input name="restore" value="<?php _e("Restore Built-in Defaults", 'reaction_buttons'); ?>" type="submit"/></span>
						<span class="submit"><input name="remove" value="<?php _e("Remove unused data", 'reaction_buttons'); ?>" type="submit"/></span>
					</td>
				</tr>
			</table>
		</div>
	</form>
<?php
}


/**
 * Add a settings link to the Plugins page, so people can go straight from the plugin page to the
 * settings page.
 */
function reaction_buttons_filter_plugin_actions( $links, $file ){
	// Static so we don't call plugin_basename on every plugin row.
	static $this_plugin;
	if ( ! $this_plugin ) $this_plugin = plugin_basename(__FILE__);
	
	if ( $file == $this_plugin ){
		$settings_link = '<a href="options-general.php?page=reaction_buttons">' . __('Settings') . '</a>';
		array_unshift( $links, $settings_link ); // before other links
	}
	return $links;
}
add_filter( 'plugin_action_links', 'reaction_buttons_filter_plugin_actions', 10, 2 );

function reaction_buttons_widget() {
	global $wpdb;
	$table = $wpdb->prefix . "postmeta";
	$buttons = explode(",", preg_replace("/,\s+/", ",", get_option('reaction_buttons_button_names')));
	$widget = "";

	$widget .= "<h2 class='widgettitle'>" . __("Reaction Buttons", 'reaction_buttons') . "</h2>";

	foreach($buttons as $button){
		$posts = $wpdb->get_results("SELECT post_id,meta_value FROM $table WHERE " .
			"meta_key = '_reaction_buttons_$button' ORDER BY meta_value DESC LIMIT 3");
		$widget .= "<strong>$button</strong><br/><ul>";
		foreach($posts as $postdb){
			$post = get_post(intval($postdb->post_id));
			$count = intval($postdb->meta_value);
			$widget .= "<li><a href='" . get_permalink($post->ID) . "'>" . $post->post_title . " ($count)</a></li>";
		}
		$widget .= "</ul>";
		
	}

	
	
	echo $widget;
}

function reaction_buttons_init_widget() {
	register_sidebar_widget(__('Reaction Buttons', 'reaction_buttons'), 'reaction_buttons_widget');    
}
add_action("plugins_loaded", "reaction_buttons_init_widget");

?>