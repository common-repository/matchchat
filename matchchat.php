<?php
/*
Plugin Name: Matchchat
Plugin URI: http://www.matchchat.co.uk
Description: Engage your fans like never before.
Version: 2.3.2
Author: Matchchat Ltd
Author URI: http://www.matchchat.co.uk
*/
if (basename($_SERVER['SCRIPT_NAME']) == basename(__FILE__)) exit('Please do not load this page directly');

// NATIVE TRENDS
class Native_Trends extends WP_Widget{
	function Native_Trends(){
		$widget_ops = array(
			'classname' => 'Native Trends',
			'description' => 'Make your posts go viral with Native Trends.'
		);
		$this->WP_Widget('Native_Trends', 'Native Trends', $widget_ops);
	}

	function form($instance){
		$instance = wp_parse_args((array)$instance, array(
			'title' => ''
		));
		$title = $instance['title'];
?>
	<p>
		Native Trends&trade; connects your readers to the latest and most viral articles on your site, increasing your pageviews and advertising revenue.
	</p>
<?php
	}

	function update($new_instance, $old_instance){
		$instance = $old_instance;
		$instance['title'] = $new_instance['title'];
		return $instance;
	}

	function widget($args, $instance){
		extract($args, EXTR_SKIP);
		//echo $before_widget;

		// WIDGET CODE GOES HERE

		require("settings.php");

		echo "<div id='nvtrends'></div>";

		echo "\n<script type = 'text/javascript'>
				setTimeout(function(){
					var nv = document.createElement('script'); nv.type = 'text/javascript';
					nv.src = 'http://" . $MC_SETTINGS['trends_endpoint'] . "/js/trends.js';
					nv.async = true;
					(document.getElementsByTagName('head')[0] || document.getElementsByTagName('body')[0]).appendChild(nv);
				},1);
			</script>";

		//echo $after_widget;
	}
}

add_action('widgets_init', create_function('', 'return register_widget("Native_Trends");'));

// MATCHCHAT

function loadMC($value) {
	if (is_admin()){
		return;
	}
	
	$oldcomments = MC_get_wp_comments();
	
	if ($oldcomments == 0){
		if(is_single())
		{
			return dirname(__FILE__) . '/commentsystem.php';
		} else {
			return dirname(__FILE__) . '/nocomments.php';
		}
	}
}

function MC_get_wp_comments(){
	$showhistory = get_option('mc_show_history', 'true');
	
	if ($showhistory != 'true'){
		return 0;
	}
	
	global $wpdb;
	$postID = get_the_ID();

	if (!isset($postID) || $postID == ''){
		return 0;
	}
	
	$query = "SELECT COUNT(*) AS comment_count FROM {$wpdb->comments} WHERE comment_approved = 1 AND comment_post_ID = '" . $postID . "'";
	$comments = $wpdb->get_results($query);
	
	if ($comments){
		return $comments[0]->comment_count;
	} else {
		return 0;
	}
}

function MC_get_comments_number($value){
	if (is_admin()){
		return 0;
	}
	
	$showcount = get_option('mc_show_count', 'false');
	if ($showcount == 'false'){
		return 0;
	}
	
	$oldcomments = MC_get_wp_comments();
	if ($oldcomments == 0){
		global $wpdb;
		$postID = get_the_ID();
		$query = "CREATE TABLE IF NOT EXISTS mc_commentcache (
			ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_ID bigint(20) unsigned NOT NULL,
			comments bigint(20) unsigned NOT NULL,
			time bigint(20) unsigned NOT NULL,
			PRIMARY KEY (ID),
			UNIQUE KEY postID (post_ID),
			CONSTRAINT mc_postcommentcache FOREIGN KEY (post_ID)
			REFERENCES {$wpdb->posts}(ID)
			ON DELETE CASCADE ON UPDATE CASCADE
		) ENGINE=InnoDB";
		
		$dbstatus = $wpdb->query($query);
		if ($dbstatus){
			$cachelimit = intval(get_option('mc_cache_limit', 1800));
			if ($cachelimit < 0) $cachelimit = 0;
			$query = "SELECT comments FROM mc_commentcache WHERE post_ID = " . $postID . " AND time>=(UNIX_TIMESTAMP() - " . $cachelimit . ")"; 
			$cache = $wpdb->get_results($query);
			if ($cache)
			{
				return $cache[0]->comments;
			}
		}
		
		$wpcomments = MC_get_wp_comments();
		if ($wpcomments != 0 && $wpcomments != ''){
			return $wpcomments;
		}

		require('settings.php');
		
		$host = get_site_url();
		$host = str_replace('http://', '', $host);
		$host = str_replace('https://', '', $host);
		try{
			$ch = curl_init();
			$url = 'http://' . $MC_SETTINGS['api_endpoint'] . '/totalcomments?blog=' . $host . '&article=' . get_the_ID();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_TIMEOUT, 2);
			$data = curl_exec($ch);
			curl_close($ch);
			
			$data = json_decode($data);
			if ($data->comment_count){
				$query = "INSERT INTO mc_commentcache (post_ID,comments,time) 
					VALUES (" . $postID . "," . $data->comment_count . "," . time() . ") 
					ON DUPLICATE KEY UPDATE comments=" . $data->comment_count . ",time=UNIX_TIMESTAMP()";
				$dbstatus = $wpdb->query($query);
				return $data->comment_count;
			} else {
				return 0;
			}
		}
		catch (Exception $e) {
			return 0;
		}
	} else {
		return $oldcomments;
	}
}

add_filter('comments_template', 'loadMC');
add_filter('get_comments_number', 'MC_get_comments_number');


/** ADMIN MENU **/
add_action( 'admin_menu', 'mc_plugin_menu' );

function mc_plugin_menu(){
	//add_options_page( 'Matchchat Plugin Options', 'Matchchat Plugin', 'manage_options', 'mc-plugin-settings', 'mc_plugin_options' );	
	add_menu_page ( 'Matchchat Plugin Options', 'Matchchat', 'manage_options', 'mc-plugin-settings', 'mc_plugin_options' );
	add_submenu_page ( 'mc-plugin-settings', 'Matchchat Plugin Options', 'Settings', 'manage_options', 'mc-plugin-settings', 'mc_plugin_options' );
	add_submenu_page ( 'mc-plugin-settings', 'Moderate Comments', 'Moderate Comments', 'manage_options', 'mc-plugin-moderate', 'mc_plugin_moderate' );
}

function mc_plugin_options() {
	if ( !current_user_can( 'manage_options' ) )  {
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
	}
?>
<style>
	.mc_wp-options p{
		font-style:italic;
	}
</style>
<div class="wrap mc_wpoptions">
	<style>
		.mc_wpoptions{

		}
		.mc_wpoptions br{
			margin-top:10px;
		}
		.mc_wpoptions .optiondescription{
			padding:30px 10px;
		}
		.mc_wpoptions .optiondescription p{
			margin-top:10px;
			font-size:13px;
		}
	</style>
	<h2>Matchchat Settings</h2>
	<form method="post" action="options.php">
		<?php wp_nonce_field('update-options'); ?>
		<table class="form-table">
			<tr valign="top">
				<th scope="row" style="vertical-align:middle;">Show original comments?</th>
				<td>
					Yes <input type="radio" name="mc_show_history" value="true"<?php if (get_option('mc_show_history', "true") == 'true') { ?> checked<?php } ?> />
					No <input type="radio" name="mc_show_history" value="false"<?php if (get_option('mc_show_history', "true") == 'false') { ?> checked<?php } ?> />
				</td>
				<td class="optiondescription">
					<p>This setting will keep your existing comments on all articles that you wrote before installing Matchchat.</p>
					<p><strong>Set to "Yes" by default.</strong></p>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row" style="vertical-align:middle;">Show comment count in lists?</th>
				<td>
					Yes <input type="radio" name="mc_show_count" value="true"<?php if (get_option('mc_show_count', "false") == 'true') { ?> checked<?php } ?> />
					No <input type="radio" name="mc_show_count" value="false"<?php if (get_option('mc_show_count', "false") == 'false') { ?> checked<?php } ?> />
				</td>
				<td class="optiondescription">
					<p>If you have a theme which shows the number of comments for every article on your homepage ("0 comments"), you can enable this.</p>
					<p><strong>Note:</strong> This may slow down your site if you have a lot of articles on your page.</p>
					<p><strong>Set to "No" by default.</strong></p>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row" style="vertical-align:middle;">MC cache limit (seconds)?</th>
				<td>
					<input type="number" name="mc_cache_limit" value="<?= get_option('mc_cache_limit', 1800) ?>" />
				</td>
				<td class="optiondescription">
					<p>If you're showing comment counts and you're site's running a little slower, try increasing this number. 300 for 5 minutes, 1800 for 30 minutes, etc.<br></p>
					<p><strong>Set to "1800" by default.</strong></p>
				</td>
			</tr>
		</table>
		<input type="hidden" name="action" value="update" />
		<input type="hidden" name="page_options" value="mc_show_history,mc_show_count,mc_cache_limit" />
		<p class="submit">
			<input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
		</p>
	</form>
</div>
<?php
}

function mc_plugin_moderate() {
	if ( !current_user_can( 'manage_options' ) )  {
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
	}
	?>
		<div class="wrap">
			<h2>Comments Moderation</h2>		
			<iframe src="https://panel.matchchat.co.uk/" width="100%" style="width:100%;min-width:300px;min-height:600px;border:0;"></iframe>
		</div>
	<?php
}