<?php
  /*
   Plugin Name: Reaction Buttons
   Plugin URI: http://blog.jl42.de/reaction-buttons/
   Description: Adds Buttons for very simple and fast feedback to your post. Inspired by Blogger.
   Version: 1.4.1
   Author: Jakob Lenfers
   Author URI: http://blog.jl42.de

   I used the sociable plugin as template.

   Copyright 2010-present Jakob Lenfers <jakob@lenfers.org>

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
	// check if reaction buttons are somehow deactivated for this post
	if (get_post_meta(get_the_ID(),'_reaction_buttons_off',true) or !get_option(reaction_buttons_activate)) {
		return "";
	}
	// check if this is an excluded category
	$excluded_categories = get_option(reaction_buttons_excluded_categories);
	$post_categories = wp_get_post_categories(get_the_ID());
	foreach($post_categories as $post_cat){
		if (array_key_exists($post_cat, $excluded_categories) && $excluded_categories[$post_cat]) {
			return "";
		}
	}

	// get the id of the current post
	$post_id = get_the_ID();

	// vote settings
	$already_voted_text = get_option("reaction_buttons_already_voted_text");
	$only_one_vote = get_option("reaction_buttons_only_one_vote");
	$use_as_counter = get_option("reaction_buttons_use_as_counter");
	$use_cookies = get_option("reaction_buttons_usecookies") && !$use_as_counter;

	// show results only after your vote
	$show_after_votes = get_option("reaction_buttons_show_after_votes");

	// if use of cookies is activated, check for them
	if($use_cookies){
		$json = stripslashes($_COOKIE["reaction_buttons_" . $post_id]);
		$cookie = json_decode($json, true);
	}
	if(!is_array($cookie)) $cookie = array();

	// Start preparing the output
	$html = "\n<div id='reaction_buttons_post" . $post_id . "' class='reaction_buttons'>\n";

	// If a reaction summary is set, display it above the rest
	$reaction_summary = get_option("reaction_buttons_reaction_summary");
	if ($reaction_summary != "") {
		$html .= reaction_buttons_reaction_summary($reaction_summary);
	}

	// If a tagline is set, display it above the buttons
	$tagline = get_option("reaction_buttons_tagline");
	if ($tagline != "") {
		$html .= '<div class="reaction_buttons_tagline">';
		$html .= stripslashes($tagline);
		$html .= "</div>";
	}

	// get the buttons and strip whitespaces
	$buttons = explode(",", preg_replace("/,\s+/", ",", get_option('reaction_buttons_button_names')));


	$already_voted_other = false;
	// checks if the user has already voted on this post
	if(!$use_as_counter){
		foreach($buttons as $button){
			$clean_button = stripslashes(trim($button));
			if (array_key_exists(addslashes($clean_button), $cookie) && $cookie[addslashes($clean_button)]) {
				$already_voted_other = true;
				break;
			}
		}
	}

	// print every button
	foreach($buttons as $button){
		$clean_button = stripslashes(trim($button));
		$count = intval(get_post_meta(get_the_ID(), "_reaction_buttons_" . $clean_button, true));
		$html .= "<a class='reaction_button reaction_button_" . prepare_attr_jl($button) . "_count";
		$voted = array_key_exists(addslashes($clean_button), $cookie) && $cookie[addslashes($clean_button)];
		if(($voted || $only_one_vote && $already_voted_other) && !$use_as_counter){
			if(empty($already_voted_text)){
				$html .= " voted'>";
			}
			else{
				$html .= " voted' href=\"javascript:alert('".htmlspecialchars($already_voted_text)."');\">";
			}
		}
		else{
			$html .= "' href=\"javascript:reaction_buttons_increment_button_ajax('" . get_the_ID() . "', '" .
			prepare_js_jl($button) . "');\">";
		}
		if(!$show_after_votes || $already_voted_other){
			$html .= $clean_button . "&nbsp;<span class='count'>(<span class='count_number'>" . $count . "</span>)</span></a> ";
		}
		else{
			$html .= $clean_button . "&nbsp;<span class='count' style='display: none;'>(<span class='count_number'>" . $count . "</span>)</span></a> ";
		}
	}
	$html .= "</div>\n";

	return $html;
}


/**
 * Hook the_content to output html if we should display on any page
 */
$reaction_buttons_conditionals = get_option('reaction_buttons_conditionals');
$reaction_buttons_position_settings = get_option('reaction_buttons_position_settings');
if (is_array($reaction_buttons_conditionals) and in_array(true, $reaction_buttons_conditionals) and
    is_array($reaction_buttons_position_settings) and in_array(true, $reaction_buttons_position_settings)) {
	if ($reaction_buttons_position_settings['after'] or $reaction_buttons_position_settings['before']) {
		add_filter('the_content', 'reaction_buttons_display_hook');
		add_filter('the_excerpt', 'reaction_buttons_display_hook');

		/**
		 * Loop through the settings and check whether reaction buttons should be outputted.
		 */
		function reaction_buttons_display_hook($content='') {
			$conditionals = get_option('reaction_buttons_conditionals');
			$position_settings = get_option('reaction_buttons_position_settings');
			if ((is_home()	   and $conditionals['is_home']) or
			    (is_single()   and $conditionals['is_single']) or
			    (is_page()	   and $conditionals['is_page']) or
			    (is_category() and $conditionals['is_category']) or
			    (is_tag()	   and $conditionals['is_tag']) or
			    (is_date()	   and $conditionals['is_date']) or
			    (is_author()   and $conditionals['is_author']) or
			    (is_search()   and $conditionals['is_search'])) {
				// check whether to put them before or after the post (or both)
				if($position_settings['before']){
					$content = reaction_buttons_html() . $content;
				}
				if($position_settings['after']){
					$content .= reaction_buttons_html();
				}
			}
			return $content;
		}
	}

	if ($reaction_buttons_position_settings['shortcode']) {
		add_shortcode('reaction_buttons', 'reaction_buttons_html');
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

	if ( $force or ( get_option('reaction_buttons_activate', "NOTSET") == "NOTSET") ) {
		update_option('reaction_buttons_activate', true);
	}

	if ($force or !is_array(get_option('reaction_buttons_position_settings')))
		update_option('reaction_buttons_position_settings',
		              array('after' => True,
		                    'before' => False,
		                    'shortcode' => False
		                    ));

	if ( $force or !( get_option('reaction_buttons_tagline')) ) {
		update_option('reaction_buttons_tagline', "What do you think of this post?");
	}

	if ( $force or !( get_option('reaction_buttons_button_names')) ) {
		update_option('reaction_buttons_button_names', "Awesome, Interesting, Useful, Boring, Sucks");
	}

	if ( $force or !( get_option('reaction_buttons_show_after_votes')) ) {
		update_option('reaction_buttons_show_after_votes', false);
	}

	if ( $force or !( get_option('reaction_buttons_only_one_vote')) ) {
		update_option('reaction_buttons_only_one_vote', false);
	}

		if ( $force or !( get_option('reaction_buttons_use_as_counter')) ) {
		update_option('reaction_buttons_use_as_counter', false);
	}
	
	if ( $force or !( get_option('reaction_buttons_usecookies')) ) {
		update_option('reaction_buttons_usecookies', true);
	}

	if ( $force or !( get_option('reaction_buttons_already_voted_text')) ) {
		update_option('reaction_buttons_already_voted_text', "");
	}

	if ( $force or !( get_option('reaction_buttons_reaction_summary')) ) {
		update_option('reaction_buttons_already_voted_text', "");
	}

	if ( $force or !( get_option('reaction_buttons_clear_supported_caches')) ) {
		update_option('reaction_buttons_clear_supported_caches', true);
	}

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

	if ($force or !is_array(get_option('reaction_buttons_excluded_categories')))
		update_option('reaction_buttons_excluded_categories', array());

	if ( $force or ( get_option('reaction_buttons_usecss', "NOTSET") == "NOTSET" ) ) {
		update_option('reaction_buttons_usecss', true);
	}

}

/**
 * Button Statistics Page
 */
function reaction_buttons_button_statistic_page(){
?>
	<div class="wrap">
		<?php screen_icon(); ?>
		<h2><?php _e("Reaction Buttons Button Statistics", 'reaction_buttons'); ?></h2>
        <table class="form-table" border="1" style="border: 1px solid #818181;">
			<?php
            $pagination = reaction_buttons_button_paginate_statistics($page = 1, $per_page = 10);
            echo reaction_buttons_get_top_button_posts($pagination['perPage'],  0, "", $pagination['page'], $output_as_table = true);
            ?>
        </table>
        <div style="width: 100%; margin-top: 20px;">
        	Pages:
            <form action="<?php echo attribute_escape( $_SERVER['REQUEST_URI'] ); ?>" method="post">
				<input name="button_statistics" value="<?php _e("Statistics page", 'reaction_buttons'); ?>" type="hidden" />
			<?php
                for( $i=1; $i < ceil($pagination['totalPages']) + 1; $i++ ){

                    if($pagination['page'] == $i) {
                    ?>
                        <span style="background: #898989; color:#FFFFFF; padding:2px 5px;"><?=$i?></span>

                    <?php
                    }

                    else {

                    ?>
                        <input name="num" value="<?=$i?>" type="submit" />

                    <?php
                    }
                }
            ?>
            </form>
        </div>
	</div>
<?php
}

/**
 * Clicked Statistics Page
 */
function reaction_buttons_clicked_statistic_page(){
?>
	<div class="wrap">
		<?php screen_icon(); ?>
		<h2><?php _e("Reaction Buttons Clicked Statistics", 'reaction_buttons'); ?></h2>
		<p><?php _e("For this to work correctly, you may need to delete unused button data from old button names on the settings page.", 'reaction_buttons'); ?></p>
        <table class="form-table" border="1" style="border: 1px solid #818181;">
			<?php
            $pagination = reaction_buttons_clicked_paginate_statistics($page = 1, $per_page = 10);
            echo reaction_buttons_get_top_clicked_posts($pagination['perPage'], $pagination['page']);
            ?>
        </table>
        <div style="width: 100%; margin-top: 20px;">
        	Pages:
            <form action="<?php echo attribute_escape( $_SERVER['REQUEST_URI'] ); ?>" method="post">
				<input name="clicked_statistics" value="<?php _e("Statistics page", 'reaction_buttons'); ?>" type="hidden" />
			<?php
                for( $i=1; $i < ceil($pagination['totalPages']) + 1; $i++ ){

                    if($pagination['page'] == $i) {
                    ?>
                        <span style="background: #898989; color:#FFFFFF; padding:2px 5px;"><?=$i?></span>

                    <?php
                    }

                    else {

                    ?>
                        <input name="num" value="<?=$i?>" type="submit" />

                    <?php
                    }
                }
            ?>
            </form>
        </div>
	</div>
<?php
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
	$already_voted_text = get_option("reaction_buttons_already_voted_text");
	$only_one_vote = get_option("reaction_buttons_only_one_vote"); 
	$show_after_votes = get_option("reaction_buttons_show_after_votes");
	$use_as_counter = get_option("reaction_buttons_use_as_counter");
	?>
	<script	type='text/javascript'><!--
	function prepare_attr_jl(str) {
		return str.replace(/\ /g, "___");
	}
	function reaction_buttons_increment_button_ajax(post_id, button){
		var already_voted_text = '<?php echo htmlspecialchars($already_voted_text); ?>';
		var only_one_vote = <?php echo $only_one_vote ? "true" : "false"; ?>;
		var show_after_votes = <?php echo $show_after_votes ? "true" : "false"; ?>;
		var use_as_counter = <?php echo $use_as_counter ? "true" : "false"; ?>;

		if(jQuery("#reaction_buttons_post" + post_id + " .reaction_button_" + prepare_attr_jl(button) + "_count").hasClass('voted')){
			return;
		}
		
		if(!use_as_counter){
			// remove the href attribute before sending the request to make
			// sure no one votes more than once by clicking ten times fast
			if(only_one_vote){
				// remove all the href's from the posts and replace it by the
				// alert not to vote twice if set
				if(already_voted_text){
					jQuery("#reaction_buttons_post" + post_id + " .reaction_button").attr('href', 'javascript:alert(\'' + already_voted_text + '\');');
				}
				else{
					jQuery("#reaction_buttons_post" + post_id + " .reaction_button").removeAttr('href');
				}
			}
			else{
				// remove/replace only on the clicked button
				if(already_voted_text){
					jQuery("#reaction_buttons_post" + post_id + " .reaction_button_" + prepare_attr_jl(button) + "_count").attr('href', 'javascript:alert(\'' + already_voted_text + '\');');
				}
				else{
					jQuery("#reaction_buttons_post" + post_id + " .reaction_button_" + prepare_attr_jl(button) + "_count").removeAttr('href');
				}
			}
		}
		jQuery.ajax({
				type: "post",url: "<?php bloginfo( 'wpurl' ); ?>/wp-admin/admin-ajax.php", dataType: 'json',
					data: { action: 'reaction_buttons_increment_button_php', post_id: post_id, button: button, _ajax_nonce: '<?php echo $nonce; ?>' },
					success: function(data){
						jQuery("#reaction_buttons_post" + post_id + " .reaction_button_" + prepare_attr_jl(button) + "_count .count").html("("+data['count']+")");
						if(only_one_vote){
							jQuery("#reaction_buttons_post" + post_id + " .reaction_button").addClass('voted');
						}
						else{
							jQuery("#reaction_buttons_post" + post_id + " .reaction_button_" + prepare_attr_jl(button) + "_count .count").addClass('voted');
						}
						if(show_after_votes){
							jQuery("#reaction_buttons_post" + post_id + " .reaction_button .count").removeAttr('style');
						}
					}
			});
		}
	--></script>
	<?php

}

// add the javascript stuff
wp_enqueue_script("jquery");
wp_enqueue_script("json2");
//wp_register_script('jquery_kekse', plugins_url('reaction-buttons/jquery.kekse.js'));
//wp_enqueue_script('jquery_kekse');
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

	// support for clearing the articles cache if w3total cache is installed
	if(get_option(reaction_buttons_clear_supported_caches)){
		if (function_exists('w3tc_pgcache_flush_post')) {
			w3tc_pgcache_flush_post($post_id);
		}
	}

	if(get_option(reaction_buttons_usecookies)){
		if ( $_COOKIE["reaction_buttons_" . $post_id] ) {
			$json = stripslashes($_COOKIE["reaction_buttons_" . $post_id]);
			$cookie = json_decode($json, true); 
			if(!is_array($cookie)) $cookie = array();
		}
		else {
			$cookie = array();
		}

		$cookie[$button] = true;
                $cookie_path_pos = strpos(site_url("", "http"), "/", 8);
                $cookie_path = $cookie_path_pos ? substr(site_url("", "http"), $cookie_path_pos) : "/";
                setcookie("reaction_buttons_" . $post_id, json_encode($cookie), time()+3600*24*3, $cookie_path);
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
	// redirect to statistic page
	else if (isset($_REQUEST['button_statistics']) && $_REQUEST['button_statistics']) {
		reaction_buttons_button_statistic_page();
	}
	// redirect to statistic page
	else if (isset($_REQUEST['clicked_statistics']) && $_REQUEST['clicked_statistics']) {
		reaction_buttons_clicked_statistic_page();
	}
	// saves the settings from the page
	else if (isset($_REQUEST['save']) && $_REQUEST['save']) {
		check_admin_referer('reaction_buttons_config');
		$error = "";

		// save the different settings (boolean, text, array of bool)
		foreach ( array('activate', 'usecss', 'usecookies', 'only_one_vote', 'use_as_counter', 'show_after_votes', 'clear_supported_caches') as $val ) {
			if ( isset($_POST[$val]) && $_POST[$val] )
				update_option('reaction_buttons_'.$val,true);
			else
				update_option('reaction_buttons_'.$val,false);
		}

		foreach ( array('tagline', 'already_voted_text', 'reaction_summary') as $val ) {
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


		$position_settings = Array();
		if (!$_POST['position_settings'])
			$_POST['position_settings'] = Array();

		$curposition_settings = get_option('reaction_buttons_position_settings');
		foreach($curposition_settings as $condition=>$toggled)
			$position_settings[$condition] = array_key_exists($condition, $_POST['position_settings']);

		update_option('reaction_buttons_position_settings', $position_settings);


		$conditionals = Array();
		if (!$_POST['conditionals'])
			$_POST['conditionals'] = Array();

		$curconditionals = get_option('reaction_buttons_conditionals');
		foreach($curconditionals as $condition=>$toggled)
			$conditionals[$condition] = array_key_exists($condition, $_POST['conditionals']);

		update_option('reaction_buttons_conditionals', $conditionals);


		$excluded_categories = Array();
		if (!$_POST['excluded_categories'])
			$_POST['excluded_categories'] = Array();

		foreach($_POST['excluded_categories'] as $condition=>$toggled)
			$excluded_categories[$condition] = array_key_exists($condition, $_POST['excluded_categories']);

		update_option('reaction_buttons_excluded_categories', $excluded_categories);


		// done saving
		if ( $error ) {
			$error = __("Some settings couldn't be saved. More details in the error message below:<br />", 'reaction_buttons') . $error;
			reaction_buttons_message($error);
		}
		else {
			reaction_buttons_message(__("Changes saved. <a href=''>Back</a>", 'reaction_buttons'));
		}
	}
	else {
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
						<?php _e("Position in the post:", "reaction_buttons"); ?>
					</th>
					<td>
						<?php _e("Where should the reaction buttons appear?", 'reaction_buttons'); ?><br/>
						<?php _e('(The default "after the post" should work well for most blogs.)', 'reaction_buttons'); ?><br/>
						<?php
							// Load conditions under which Reaction Buttons displays
							$position_settings = get_option('reaction_buttons_position_settings');
						?>
						<input type="checkbox" name="position_settings[after]"<?php checked($position_settings['after']); ?> /> <?php _e("Place the reaction buttons after the post", 'reaction_buttons'); ?><br/>
						<input type="checkbox" name="position_settings[before]"<?php checked($position_settings['before']); ?> /> <?php _e("Place the reaction buttons above the post", 'reaction_buttons'); ?><br/>
						<input type="checkbox" name="position_settings[shortcode]"<?php checked($position_settings['shortcode']); ?> /> <?php _e("Activate the shortcode [reaction_buttons] to place them somewhere in your post.", 'reaction_buttons'); ?><br/>
						<?php _e("You can also set the reaction buttons manually in your theme, by calling the function reaction_buttons_html(). But beware that it needs to be inside of <a href='http://codex.wordpress.org/The_Loop'>The Loop</a>.", 'reaction_buttons'); ?><br/>
					</td>
				</tr>
				<tr>
					<th scope="row" valign="top">
						<?php _e("Tagline:", "reaction_buttons"); ?>
					</th>
					<td>
						<?php _e("Text above the reaction buttons. HTML is allowed in this field.", 'reaction_buttons'); ?><br/>
						<input size="80" type="text" name="tagline" value="<?php echo attribute_escape(stripslashes(get_option('reaction_buttons_tagline'))); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row" valign="top">
						<?php _e("Reaction Buttons:", "reaction_buttons"); ?>
					</th>
					<td>
						<?php _e("Reaction button titles, comma seperated.<br />You may use spaces, but should stay away from exclamation marks and such. If your button doesn't update after you click it, there might be something like an exclamation mark in its name.", 'reaction_buttons'); ?><br/>
						<input size="80" type="text" name="button_names" value="<?php echo attribute_escape(stripslashes(get_option('reaction_buttons_button_names'))); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row" valign="top">
						<?php _e("Show results after vote:", "reaction_buttons"); ?>
					</th>
					<td>
						<input type="checkbox" name="show_after_votes" <?php checked( get_option('reaction_buttons_show_after_votes')); ?> /> <?php _e("Show the current numbers of the reaction buttons only after the user voted.", "reaction_buttons"); ?>
					</td>
				</tr>
				<tr>
					<th scope="row" valign="top">
						<?php _e("Only one vote:", "reaction_buttons"); ?>
					</th>
					<td>
						<input type="checkbox" name="only_one_vote" <?php checked( get_option('reaction_buttons_only_one_vote')); ?> /> <?php _e("If checked a user is only allowed to vote once per post and not once per button.", "reaction_buttons"); ?>
					</td>
				</tr>
				<tr>
					<th scope="row" valign="top">
						<?php _e("Use as counter:", "reaction_buttons"); ?>
					</th>
					<td>
						<input type="checkbox" name="use_as_counter" <?php checked( get_option('reaction_buttons_use_as_counter')); ?> /> <?php _e("If checked a user can click multiple times on the button.", "reaction_buttons"); ?>
					</td>
				</tr>
				<tr>
					<th scope="row" valign="top">
						<?php _e("Use cookies:", "reaction_buttons"); ?>
					</th>
					<td>
						<input type="checkbox" name="usecookies" <?php checked( get_option('reaction_buttons_usecookies'), true ); ?> /> <?php _e("Use cookies to make it harder to vote twice? (Only if 'Use as counters' is disabled)", "reaction_buttons"); ?>
					</td>
				</tr>
				<tr>
					<th scope="row" valign="top">
						<?php _e("Already voted popup text:", "reaction_buttons"); ?>
					</th>
					<td>
						<?php _e("A text to popup if a users tries to vote twice. Leave empty to disable this function.", 'reaction_buttons'); ?><br/>
						<input size="80" type="text" name="already_voted_text" value="<?php echo attribute_escape(stripslashes(get_option('reaction_buttons_already_voted_text'))); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row" valign="top">
						<?php _e("Reaction Summary", "reaction_buttons"); ?>
					</th>
					<td>
						<?php _e("Reaction summary. Use it to tell your users what's been clicked most. Leave empty to disable this function.", 'reaction_buttons'); ?><br/><?php _e("%s is going to be replaced with the name of the most clicked button, e.g. 'Most people think this post is %s!'", 'reaction_buttons'); ?>
						<input size="80" type="text" name="reaction_summary" value="<?php echo attribute_escape(stripslashes(get_option('reaction_buttons_reaction_summary'))); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row" valign="top">
						<?php _e("Clear cache:", "reaction_buttons"); ?>
					</th>
					<td>
						<input type="checkbox" name="clear_supported_caches" <?php checked( get_option('reaction_buttons_clear_supported_caches'), true ); ?> /> <?php _e("Try to clear cache from supported caching plugins to make the vote show up at once?", "reaction_buttons"); ?><br/>
						<?php _e("Currently supported systems are: W3 Total Cache", "reaction_buttons"); ?><br/>
						<?php _e("If you need another plugin added here, please consult the <a href='http://wordpress.org/extend/plugins/reaction-buttons/faq/'>Reaction Buttons FAQ</a>.", "reaction_buttons"); ?>
					</td>
				</tr>
				<tr>
					<th scope="row" valign="top">
						<?php _e("Page types:", "reaction_buttons"); ?>
					</th>
					<td>
						<?php _e("Chose the pages on which to display the reaction buttons.", 'reaction_buttons'); ?><br/>
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
						<?php _e("Exclude categories:", "reaction_buttons"); ?>
					</th>
					<td>
						<?php _e("Don't show reaction buttons on posts which are in the following categories.", 'reaction_buttons'); ?><br/>
						<?php
                                                        $categories = get_categories(array('hide_empty'=>false));
							// Load categoeries where Reaction Buttons shouldn't be displayed
							$excluded_categories = get_option('reaction_buttons_excluded_categories');
							foreach ($categories as $cat){
						?>
						<input type="checkbox" name="excluded_categories[<?php echo $cat->cat_ID; ?>]"<?php checked($excluded_categories[$cat->cat_ID]); ?> /> <?php echo $cat->name ?><br />
						<?php
							}
						?>
					</td>
				</tr>
				<tr>
					<th scope="row" valign="top">
						<?php _e("Use CSS:", "reaction_buttons"); ?>
					</th>
					<td>
						<input type="checkbox" name="usecss" <?php checked( get_option('reaction_buttons_usecss'), true ); ?> /> <?php _e("Use the Reaction Buttons stylesheet?", "reaction_buttons"); ?><br />
						If you want to customize the look of Reaction Buttons, copy the content of the <a href="<?php echo $reaction_buttons_plugin_path?>reaction_buttons.css" target="_blank">reaction_buttons.css</a> into your css, modify it and disable this option.
					</td>
				</tr>
				<tr>
					<td>&nbsp;</td>
					<td>
						<span class="submit"><input name="save" value="<?php _e("Save Changes", 'reaction_buttons'); ?>" type="submit" /></span>
						<span class="submit"><input name="restore" value="<?php _e("Restore Built-in Defaults", 'reaction_buttons'); ?>" type="submit"/></span>
						<span class="submit"><input name="remove" value="<?php _e("Remove unused data", 'reaction_buttons'); ?>" type="submit"/></span>
                        <span class="submit"><input name="button_statistics" value="<?php _e("Button statistics page", 'reaction_buttons'); ?>" type="submit"/></span>
                        <span class="submit"><input name="clicked_statistics" value="<?php _e("Most clicked statistics page", 'reaction_buttons'); ?>" type="submit"/></span>
					</td>
				</tr>
			</table>
		</div>
	</form>
<?php
	}
}


/**
 * Add a settings link to the plugins page, so people can go straight from the plugin page to the
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

/**
 * Function to gather the top $limit_posts for each Raction Button. Used for the widget
 * and the shortcode
 */
function reaction_buttons_get_top_button_posts($limit_posts = 3, $excerpt_length = 0, $only_buttons = "", $page = 1, $output_as_table = false){
	global $wpdb;
	$table = $wpdb->prefix . "postmeta";

	// check options
	if($limit_posts < 1) return "";

	// get the buttons from the options and remove the unwanted ones if applicable
	$buttons = explode(",", preg_replace("/,\s+/", ",", get_option('reaction_buttons_button_names')));
	if(!empty($only_buttons)){
		$buttons = array_intersect($buttons, $only_buttons);
	}

	// output var
	$html = "";
	$html_table_th = "";
	$html_table = "";

	// for pagination
	$offset = ($page - 1) * $limit_posts;

	// get all buttons and get the top $limit_posts for those buttons
	foreach($buttons as $button){
		$posts = $wpdb->get_results("SELECT post_id,meta_value FROM $table WHERE " .
			"meta_key = '_reaction_buttons_$button' ORDER BY CAST(meta_value AS UNSIGNED) DESC LIMIT $limit_posts OFFSET $offset");

		$output_as_table == false ? $html .= "<h3>$button</h3>" : $html_table_th .= '<th style="text-align: center">'.$button.'</th>';

		if($limit_posts > 1) $output_as_table == false ? $html .= "<ol>" : $html .= "<td style='vertical-align: top;'>";

		foreach($posts as $postdb){
			$post = get_post(intval($postdb->post_id));
			$count = intval($postdb->meta_value);

			if($limit_posts > 1){
				$output_as_table == false ? $html .= "<li>" : $html .= "";
			}
			else{
				$output_as_table == false ? $html .= "<p>" : $html .= "";
			}

			$html .= "<a href='" . get_permalink($post->ID) . "'>" . $post->post_title . '&nbsp;<span style="color: #000; font-weight: bold">('.$count.')</span></a>';
			if($excerpt_length > 0){
				$html .= "<p class='reaction_buttons_excerpt'>" . wp_trim_words($post->post_content, $excerpt_length) . "</p>";
			}

			if($limit_posts > 1){
				$output_as_table == false ? $html .= "</li>" : $html .= "";
			}
			else{
				$output_as_table == false ? $html .= "</p>" : $html .= "";
			}
		}

		if($limit_posts > 1) $output_as_table == false ? $html .= "</ol>" : $html .= "</td>";
	}

	$html_table = "<tr>" . $html_table_th . "</tr><tr>" . $html . "</tr>";
	return $output_as_table == false ? $html : $html_table;
}

/**
 * Function to gather the top clicked posts.
 */
function reaction_buttons_get_top_clicked_posts($limit_posts = 5, $page = 1){
	global $wpdb;
	$table = $wpdb->prefix . "postmeta";
	// check options
	if($limit_posts < 1) return "";
	// output var
	$html = "";

	// for pagination
	$offset = ($page - 1) * $limit_posts;

	$posts = $wpdb->get_results("SELECT post_id, SUM(CAST(meta_value AS UNSIGNED)) AS count FROM $table WHERE meta_key LIKE '_reaction_buttons%' GROUP BY post_id ORDER BY SUM(CAST(meta_value AS UNSIGNED)) DESC LIMIT $limit_posts OFFSET $offset;");
//	$html .= "<h3>Posts with most clicks over all buttons</h3>";

	if($limit_posts > 1) $html .= "<ol>";

	foreach($posts as $postdb){
		$post = get_post(intval($postdb->post_id));
		$count = intval($postdb->count);

		if($limit_posts > 1){
			$html .= "<li>";
		}
		else{
			$html .= "<p>";
		}
		$html .= "<a href='" . get_permalink($post->ID) . "'>" . $post->post_title . '&nbsp;<span style="color: #000; font-weight: bold">('.$count.')</span></a>';
		if($limit_posts > 1){
			$html .= "</li>";
		}
		else{
			$html .= "</p>";
		}
	}

	if($limit_posts > 1) $html .= "</ol>";

	return $html;
}

/**
*  Pagination for statistics page
*/
function reaction_buttons_button_paginate_statistics($page = 1, $per_page = 10){
	global $wpdb;
	$table = $wpdb->prefix . "postmeta";
	$buttons = explode(",", preg_replace("/,\s+/", ",", get_option('reaction_buttons_button_names')));

	$maxRecords = 0;
	foreach($buttons as $button) {
		$result = $wpdb->get_results("SELECT COUNT(post_id) as count FROM $table WHERE meta_key = '_reaction_buttons_$button'");
		if(intval($result[0]->count) > $maxRecords) $maxRecords = intval($result[0]->count);
	}

	$per_page = (int)$per_page;
	$totalPages = ceil($maxRecords / $per_page);
	$num = $_POST['num'];
	$page = !empty($num) ? (int)$num : 1;
	$pagination = array("perPage" => $per_page, "totalPages" => $totalPages, "page" => $page);

	return $pagination;
}

/**
*  Pagination for statistics page
*/
function reaction_buttons_clicked_paginate_statistics($page = 1, $per_page = 10){
	global $wpdb;
	$table = $wpdb->prefix . "postmeta";

	$maxRecords = $wpdb->query("SELECT post_id from $table WHERE meta_key LIKE '_reaction_buttons%' GROUP BY post_id;");

	$per_page = (int)$per_page;
	$totalPages = ceil($maxRecords / $per_page);
	$num = $_POST['num'];
	$page = !empty($num) ? (int)$num : 1;
	$pagination = array("perPage" => $per_page, "totalPages" => $totalPages, "page" => $page);

	return $pagination;
}



/**
 * A widget that displays the posts with the most clicks for each button.
 */
function reaction_buttons_widget() {
	// how many posts to show per button?
	$limit_posts = get_option('reaction_buttons_widget_count');
	if (!(is_numeric($limit_posts) && 0 < intval($limit_posts))) $limit_posts = 3;
	$limit_posts = intval($limit_posts);

	// get title or set default title
	$title = get_option('reaction_buttons_widget_title');
	if (empty($title)) $title = __("Most clicked buttons", 'reaction_buttons');

	// should an exceprt be shown and how many words?
	$excerpt_count = get_option('reaction_buttons_widget_excerpt');
	if (!(is_numeric($excerpt_count) && 0 <= intval($excerpt_count))) $excerpt_count = 0;
	$excerpt_count = intval($excerpt_count);

	// should only a few buttons be shown?
	$only_buttons = get_option('reaction_buttons_widget_buttons');
	$only_buttons = explode(",", preg_replace("/,\s+/", ",", $only_buttons));

	// gather the output
	$widget = "<div class='widget_reaction_buttons widget'>";
	$widget .= "<h2 class='widgettitle'>" . $title . "</h2>";
	$widget .= reaction_buttons_get_top_button_posts($limit_posts, $excerpt_count, $only_buttons, $page = 1, $output_as_table = false);
	$widget .= "</div>";
	echo $widget;
}

/**
 * Add settings to the widget page
 */
function reaction_buttons_widget_control(){
	echo "<p>" . __("This widget shows the posts with the most clicks for each button.", 'reaction_buttons') . "</p>";

	// show the current settings and the dialog
	?>
	<p><label><?php _e("Title:", 'reaction_buttons') ?><br />
	<input name="reaction_buttons_widget_title" type="text" value="<?php echo get_option('reaction_buttons_widget_title') ?>" /></label></p>
	<p><label><?php _e("How many posts to show:", 'reaction_buttons') ?><br />
	<input name="reaction_buttons_widget_count" type="text" value="<?php echo get_option('reaction_buttons_widget_count'); ?>" /></label></p>
	<p><label><?php _e("How many words as excerpts? 0 deactivates the excerpt:", 'reaction_buttons') ?><br />
	<input name="reaction_buttons_widget_excerpt" type="text" value="<?php echo get_option('reaction_buttons_widget_excerpt'); ?>" /></label></p>
	<p><label><?php _e("Show only those buttons (comma seperated, default all):", 'reaction_buttons') ?><br />
	<input name="reaction_buttons_widget_buttons" type="text" value="<?php echo get_option('reaction_buttons_widget_buttons'); ?>" /></label></p>

	<?php
	// validate the input and update the settings
	if (isset($_POST['reaction_buttons_widget_title'])){
		update_option('reaction_buttons_widget_title', attribute_escape($_POST['reaction_buttons_widget_title']));
	}

	if (isset($_POST['reaction_buttons_widget_count'])){
		$count = $_POST['reaction_buttons_widget_count'];
		if (is_numeric($count) && 0 < intval($count)) {
			update_option('reaction_buttons_widget_count', attribute_escape($count));
		}
		else {
			reaction_buttons_message(__("Please input a positiv number!", 'reaction_buttons'));
		}
	}
	if (isset($_POST['reaction_buttons_widget_excerpt'])){
		$excerpt = $_POST['reaction_buttons_widget_excerpt'];
		if (is_numeric($excerpt) && 0 <= intval($excerpt)) {
			update_option('reaction_buttons_widget_excerpt', attribute_escape($excerpt));
		}
		else {
			update_option('reaction_buttons_widget_excerpt', 0);
		}
	}
	if (isset($_POST['reaction_buttons_widget_buttons'])){
		update_option('reaction_buttons_widget_buttons', attribute_escape($_POST['reaction_buttons_widget_buttons']));
	}
}

/**
 * A widget that displays the posts with the most clicks for each button.
 */
function reaction_buttons_clicked_widget() {
	// how many posts to show?
	$limit_posts = get_option('reaction_buttons_widget_count');
	if (!(is_numeric($limit_posts) && 0 < intval($limit_posts))) $limit_posts = 10;
	$limit_posts = intval($limit_posts);

	// get title or set default title
	$title = get_option('reaction_buttons_clicked_widget_title');
	if (empty($title)) $title = __("Most clicked posts", 'reaction_buttons');

	// gather the output
	$widget = "<div class='widget_reaction_buttons_clicked widget'>";
	$widget .= "<h2 class='widgettitle'>" . $title . "</h2>";
	$widget .= reaction_buttons_get_top_clicked_posts($limit_posts, $page = 1, $output_as_table = false);
	$widget .= "</div>";
	echo $widget;
}

/**
 * Add settings to the widget page
 */
function reaction_buttons_clicked_widget_control(){
	echo "<p>" . __("This widget shows the posts with the most clicks over all buttons.", 'reaction_buttons') . "</p>";

	// show the current settings and the dialog
	?>
	<p><label><?php _e("Title:", 'reaction_buttons') ?><br />
	<input name="reaction_buttons_clicked_widget_title" type="text" value="<?php echo get_option('reaction_buttons_clicked_widget_title') ?>" /></label></p>
	<p><label><?php _e("How many posts to show:", 'reaction_buttons') ?><br />
	<input name="reaction_buttons_clicked_widget_count" type="text" value="<?php echo get_option('reaction_buttons_clicked_widget_count'); ?>" /></label></p>

	<?php
	// validate the input and update the settings
	if (isset($_POST['reaction_buttons_clicked_widget_title'])){
		update_option('reaction_buttons_clicked_widget_title', attribute_escape($_POST['reaction_buttons_clicked_widget_title']));
	}

	if (isset($_POST['reaction_buttons_clicked_widget_count'])){
		$count = $_POST['reaction_buttons_clicked_widget_count'];
		if (is_numeric($count) && 0 < intval($count)) {
			update_option('reaction_buttons_clicked_widget_count', attribute_escape($count));
		}
		else {
			reaction_buttons_message(__("Please input a positiv number!", 'reaction_buttons'));
		}
	}
}

/**
 * register the widget functions
 */
function reaction_buttons_init_widget() {
	register_sidebar_widget(__('Reaction Buttons button statistics', 'reaction_buttons'), 'reaction_buttons_widget');
	register_widget_control(__('Reaction Buttons button statistics', 'reaction_buttons'), 'reaction_buttons_widget_control');
	register_sidebar_widget(__('Reaction Buttons most clicked posts', 'reaction_buttons'), 'reaction_buttons_clicked_widget');
	register_widget_control(__('Reaction Buttons most clicked posts', 'reaction_buttons'), 'reaction_buttons_clicked_widget_control');
}
add_action("plugins_loaded", "reaction_buttons_init_widget");

/**
 * Function for the shortcode [reaction_buttons_most_clicks]
 *
 * shortcode [reaction_buttons_most_clicks] shows the most clicked post of each button.
 * Takes the following parameter:
 * * limit_posts: to specify the number of posts to show per button. (default 3)
 * * excerpt_length: number of words of the article to show as an excerpt.
 *                   0 deactivates the excerpt. (default deactivated)
 * * only_buttons: comma separated list of buttons to show. Default is to show all
 */
function reaction_buttons_most_clicks($atts) {
	extract(shortcode_atts(array('limit_posts' => 3, 'excerpt_length' => 0, 'only_buttons' => ""), $atts));

	$only_buttons = explode(",", preg_replace("/,\s+/", ",", $only_buttons));
	$html = "<div class='reaction_buttons_most_clicks'>";
	$html .= reaction_buttons_get_top_button_posts($limit_posts, $excerpt_length, $only_buttons, $page = 1, $output_as_table = false);
	$html .= "</div>";
	return $html;
}
add_shortcode('reaction_buttons_most_clicks', 'reaction_buttons_most_clicks');


function reaction_buttons_click_count($post_id){
	global $wpdb;
	$table = $wpdb->prefix . "postmeta";

	if(!is_int($post_id)) return;

	$result = $wpdb->get_results("select sum(meta_value) as count from $table where post_id=$post_id and meta_key like '_reaction_buttons%'");
	return $result[0]->count;
}

/**
 * Returns a text including the name of the button clicked the most. 
 * Use "%s" in the summary_text for the position of the buttons name.
 */
function reaction_buttons_reaction_summary($summary_text = "Most people think this post is %s!"){
	global $wpdb;
	$table = $wpdb->prefix . "postmeta";

	// find the most clicked button for this post
	$buttons = $wpdb->get_results("SELECT meta_key FROM ". $table ." WHERE post_id=". get_the_ID() ." AND meta_key LIKE '_reaction_buttons%'  ORDER BY CAST(meta_value AS UNSIGNED) DESC LIMIT 1;", ARRAY_A);

	// return nothing in case nothing is found (e.g. no button clicked yet) and aquire the button name
	if(empty($buttons)) return "";
	if(0 != strpos($buttons[0]['meta_key'], '_reaction_buttons_')){
		return "";
	}
	$button = substr($buttons[0]['meta_key'], 18);
	
	return "<div class='reaction_buttons_reaction_summary'>". sprintf($summary_text, $button) ."</div>";
}

/**
 * Function for the shortcode [reaction_buttons_reaction_summary]
 * 
 * shortcode [reaction_buttons_reaction_summary] returns a text including the
 * name of the button clicked the most. The text can be configured with the
 * variable "summary_text" in the shortcode. Use "%s" in the text for the
 * position of the buttons name.
 */
function reaction_buttons_reaction_summary_shortcode($atts){
	extract(shortcode_atts(array('summary_text' => "Most people think this post is %s!"), $atts));
	
	return reaction_buttons_reaction_summary($summary_text);
}
add_shortcode('reaction_buttons_reaction_summary', 'reaction_buttons_reaction_summary_shortcode');

?>
