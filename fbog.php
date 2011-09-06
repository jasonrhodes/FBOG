<?php
/**
 * @package FBOG for WordPress
 * @version 1.0
 */
/*
Plugin Name: FBOG for WordPress
Plugin URI: http://jhu.edu
Description: Creates the Facebook open graph tags for each kind of content. // Note: This version has a slight mod for the Share This plugin to be integrated with this one. It's in the fbog_generate_like_button() function, calling the st_makeEntries() function from Share This.
Author: Jason Rhodes
Version: 1.0
Author URI: http://jasonthings.com
*/

define( 'FBOG_VERSION', '1.0.0' );
define( 'THIS_PLUGIN_URL', WP_PLUGIN_URL . '/fbog' );
define( 'THIS_IMAGE_DIR', THIS_PLUGIN_URL . '/images' );


function fbog_get_thumbnail_url($id) {
	if(!isset( $id )) { return false; }
	$thumb_id = get_post_thumbnail_id($id);
	$image_url = wp_get_attachment_image_src($thumb_id);
	return $image_url[0];
}


function fbog_init() {
		wp_register_style('fbog.css', THIS_PLUGIN_URL . '/fbog.css');
		wp_enqueue_style('fbog.css');
		//wp_register_script('fbog.js', THIS_PLUGIN_URL . '/fbog.js', array('jquery'), "1.0.0", true);
		//wp_enqueue_script('fbog.js');
		wp_register_style( 'fbog-admin', THIS_PLUGIN_URL . '/fbog-admin.css' );
		
		error_reporting( 0 );
}
add_action( 'init', 'fbog_init' );

function fbog_add_tags() {
	
	global $post;
	$id = get_the_ID();
	$post = get_post( $id );
	
	//var_dump( $post );
	
	// Set some checks (so we don't have to call WP methods over and over)
	$is_front = is_front_page();
	$is_single = is_single();
	$is_page = is_page();
	$is_archive = is_archive();
	$is_category = is_category();
	
	$sitename = get_bloginfo( 'sitename' );
	
	// Create tag content, based on current WP object
	$defaults = get_option( 'fbog_options' );
	
	// og:title
	$title = get_the_title( $id );
	if ( $is_front ) $title = $sitename;
	$title = !!$title ? $title : $sitename;
	
	if( $is_category ) {
		$category = get_the_category( $id ); 
		$title = "Category Archive | " . $category[0]->cat_name;
	}
	
	// og:description
	$description = $post->post_excerpt;
	if ( !$description ) {
		$description = strip_tags( $post->post_content );
		$description = trim( $description );
		if ( strlen( $description ) > 200 ) {
			$description = substr( $description, 0, 200 ) . " [...]";
		}
	}
	
	// og:type
	$default_og_type = $defaults['og_type'];
	if ( $default_og_type == "" ) { $default_og_type = "website"; }
	$type = $is_single ? "article" : $default_og_type;
	
	// og:image
	$default_image_url = $defaults['og_image'];
	if ( !!$default_image_url && !fopen( $default_image_url, "r" ) ) { $default_image_url = ""; }
	$image = fbog_get_thumbnail_url($id) ? fbog_get_thumbnail_url($id) : $default_image_url;
	
	$url = get_permalink();
	$admin_id = array();
	$app_id = array();
	
	// Output tags
	echo "\n<!-- Facebook Open Graph Tags -->\n";
	echo "<meta property=\"og:title\" content=\"{$title}\" />\n";
	echo "<meta property=\"og:description\" content=\"{$description}\" />\n";
	echo "<meta property=\"og:type\" content=\"{$type}\" />\n";
	echo "<meta property=\"og:image\" content=\"{$image}\" />\n";
	echo "<meta property=\"og:url\" content=\"{$url}\" />\n";
	echo "<meta property=\"og:site_name\" content=\"{$sitename}\" />\n";
	if( !empty($defaults['fb_admins']) ) {
		echo "<meta property=\"fb:admins\" content=\"{$defaults['fb_admins']}\" />\n";
	}
	if( !empty($defaults['fb_app_id']) ) {
		echo "<meta property=\"fb:admins\" content=\"{$defaults['fb_app_id']}\" />\n";
	}
	echo "<!-- End Facebook Open Graph Tags -->\n\n";
}
add_action( 'wp_head', 'fbog_add_tags' );


// Add the options page, use the $handle to reference back 
// so we can include the stylesheet on this options page
function fbog_add_options_page() {
	$handle = add_options_page( 'Facebook Open Graph (FBOG) for WordPress', 'Facebook Open Graph', 'manage_options', 'fbog-plugin-settings', 'fbog_create_options_page' );
	add_action( 'admin_print_styles-' . $handle, 'fbog_admin_stylesheet' );
}
add_action( 'admin_menu', 'fbog_add_options_page' );


// Separate function to enqueue my stylesheet, it only gets included on my settings page
function fbog_admin_stylesheet() {
	wp_enqueue_style( 'fbog-admin' );
}

function setup_pre($text, $inline=true) {
	if ( $inline ) {
		return "<span class='pre'>".esc_attr($text)."</span>";
	}
	else {
		return "<pre>".esc_attr($text)."</pre>";
	}
}

// Register options, tie them into the admin_init
function fbog_admin_init() {
	register_setting( 'fbog_options', 'fbog_options', 'fbog_validate_options' );
	add_settings_section( 'fbog_og_defaults', 'Open Graph Defaults', 'fbog_og_defaults_explain', 'fbog-plugin-settings' );
		$type = setup_pre("<og:type>");
		add_settings_field( 'fbog_og_type', "Type {$type}", 'fbog_og_type_input', 'fbog-plugin-settings', 'fbog_og_defaults' );
		$image = setup_pre("<og:image>");
		add_settings_field( 'fbog_og_image', "Image {$image}", 'fbog_og_image_input', 'fbog-plugin-settings', 'fbog_og_defaults' );
		$admins = setup_pre("<fb:admins>");
		add_settings_field( 'fbog_fb_admins', "Admins {$admins}", 'fbog_og_admins_input', 'fbog-plugin-settings', 'fbog_og_defaults' );
		$app_id = setup_pre("<fb:app_id>");
		add_settings_field( 'fbog_fb_app_id', "App ID {$app_id}", 'fbog_og_app_id_input', 'fbog-plugin-settings', 'fbog_og_defaults' );
		
	add_settings_section( 'fbog_like', 'Like Buttons', 'fbog_like_explain', 'fbog-plugin-settings' );
		$label = "Show <img class='like-button-inline' src='".THIS_IMAGE_DIR."/fb-like-button.png' /> buttons on:";
		add_settings_field( 'fbog-like-input', $label, 'fbog_like_input', 'fbog-plugin-settings', 'fbog_like' );
		add_settings_field( 'fbog-like-location', "Top | Bottom | Both<br><br><small>Show like button above content, below, or both?</small>", 'fbog_like_location', 'fbog-plugin-settings', 'fbog_like' );
		
		add_settings_field( 'fbog-like-layout', 'Layout Style', 'fbog_like_layout', 'fbog-plugin-settings', 'fbog_like' );
		add_settings_field( 'fbog-like-show-faces', 'Show faces?', 'fbog_like_show_faces', 'fbog-plugin-settings', 'fbog_like' );
		add_settings_field( 'fbog-like-verb', 'Verb', 'fbog_like_verb', 'fbog-plugin-settings', 'fbog_like' );
		add_settings_field( 'fbog-like-color-scheme', 'Color Scheme', 'fbog_like_color_scheme', 'fbog-plugin-settings', 'fbog_like' );
		add_settings_field( 'fbog-like-width', 'Width', 'fbog_like_width', 'fbog-plugin-settings', 'fbog_like' );
		
}	
add_action( 'admin_init', 'fbog_admin_init' );


// Callbacks
function fbog_validate_options( $input ) {
	return $input;
}

function fbog_og_defaults_explain() {
	echo '<p class="message message-green">You can set default values for some of the Open Graph tags. To learn more about Open Graph tags and what each one does, take a look at <a href="http://developers.facebook.com/docs/opengraph/">the Facebook Developer docs for the Open Graph protocol</a>.</p>';
}

function fbog_like_explain() {
	echo '<p class="message message-green">Customize your "like" buttons&mdash;how they look, where they appear, etc.</p>';
}


function fbog_og_type_input() {
	$options = get_option( 'fbog_options' );
	$og_type = $options['og_type'];
	
	$fbog_types = array(
		array(
			'heading' => 'Activities',
			'types' => array(
				'activity',
				'sport'
			)
		),
		array(
			'heading' => 'Businesses',
			'types' => array(
				'bar',
				'company',
				'cafe',
				'hotel',
				'restaurant'
			)
		),
		array(
			'heading' => 'Groups',
			'types' => array(
				'cause',
				'sports_league',
				'sports_team'
			)
		),
		array(
			'heading' => 'Organizations',
			'types' => array(
				'band',
				'government',
				'non_profit',
				'school',
				'university'
			)
		),
		array(
			'heading' => 'People',
			'types' => array(
				'actor',
				'athlete',
				'author',
				'director',
				'musician',
				'politician',
				'public_figure'
			)
		),
		array(
			'heading' => 'Places',
			'types' => array(
				'city',
				'country',
				'landmark',
				'state_province'
			)
		),
		array(
			'heading' => 'Products and Entertainment',
			'types' => array(
				'album',
				'book',
				'drink',
				'food',
				'game',
				'product',
				'song',
				'movie',
				'tv_show'
			)
		),
		array(
			'heading' => 'Websites',
			'types' => array(
				'blog',
				'website',
				'article'
			)
		)
	);
	
	echo "<select id='og_type' name='fbog_options[og_type]' type='text' value='{$og_type}'>";
	// List of supported types, from http://developers.facebook.com/docs/opengraph/#types
		echo "<option value=''>--Select a supported type--</option>";
		foreach ( $fbog_types as $grp ) {
			echo "<optgroup label=\"{$grp['heading']}\">";
			foreach ( $grp['types'] as $type ) {
				echo "<option";
				if($og_type == $type) {
					echo " selected";
				}
				echo ">{$type}</option>";
			}
			echo "</optgroup>";
		}
	echo "</select>";
	echo "<br>";
	echo "<small>ex. University, Band, Restaurant | <a href='http://developers.facebook.com/docs/opengraph/#types'>More details about supported types</a><br><em>Note: Single blog posts will have 'og:type' set to 'article' as Facebook recommends.</em></small>";
}

function fbog_og_image_input() {
	$options = get_option( 'fbog_options' );
	$og_image = $options['og_image'];
	if ( $og_image == "" || $og_image == " " ) { $og_image = "http://"; }
	echo "<input id='og_image' name='fbog_options[og_image]' type='text' value='{$og_image}' />";
	echo "<br>";
	echo "<small>Full URL for a default image, if none exists for the post or page</small>";
}

function fbog_og_admins_input() {
	$options = get_option( 'fbog_options' );
	$fb_admins = $options['fb_admins'];
	echo "<input id='fb_admins' name='fbog_options[fb_admins]' type='text' value='{$fb_admins}' />";
	echo "<br>";
	echo "<small>A comma-separated list of Facebook user IDs associated with this site</small>";
}

function fbog_og_app_id_input() {
	$options = get_option( 'fbog_options' );
	$fb_app_id = $options['fb_app_id'];
	echo "<input id='fb_app_id' name='fbog_options[fb_app_id]' type='text' value='{$fb_app_id}' />";
	echo "<br>";
	echo "<small>The Facebook Platform application ID that administers this page</small>";
}

function fbog_like_input() {
	$options = get_option( 'fbog_options' );
	$like_options = $options['like_options']; // array
	
	//$like_checkboxes = array( 'post', 'page' ); 
	$post_types = array( 'post', 'page' );
	
	if ( function_exists( 'get_post_types' ) ) {
		$post_types = get_post_types( '', 'objects' );
	}
	
	$exclude = array( 'attachment', 'revision', 'nav_menu_item' ); // Don't list these for possible "like" buttons
	
	echo "<ul>";
		foreach( $post_types as $post_type) {
		
			if ( function_exists( 'get_post_types' ) ) {
				$value = $post_type->name;
				$singular = $post_type->labels->singular_name;
				$plural = $post_type->labels->name;
			}
			else {
				$value = $post_type;
				$singular = ucwords( $post_type );
				$plural = ucwords( $post_type . "s" );
			}
			
			if ( !in_array( $value, $exclude ) ) {
				echo "<li class='input label inline'>";
				echo "<input id='like-{$value}' type='checkbox' name='fbog_options[like_options][]' value='{$value}'";
				if ( is_array( $like_options ) && in_array( $value, $like_options ) ) { echo " checked"; }
				echo " />";
				echo "<label for='like-{$value}'>".$plural."</label>";
				echo "</li>";
			}
		}
		echo "<li class='input label inline'>";
			echo "<input id='like-loop' type='checkbox' name='fbog_options[like_options][]' value='in-loop'";
			if( is_array( $like_options ) && in_array( 'in-loop', $like_options ) ) { echo " checked"; }
			echo " />";
			echo "<label for='like-loop'>In the loop</label>";
		echo "</li>";
	echo "</ul>";
}

function fbog_like_location() {
	$options = get_option( 'fbog_options' );
	$selected = $options['like_location'];
	
	$location_array = array( 
		"Top" => 'top', 
		"Bottom" => 'bottom', 
		"Both" => 'top_and_bottom' 
	);
	
	echo "<ul>";
	foreach ( $location_array as $label => $loc ) {
		echo "<li class='input label inline'>";
		echo "<input id='location-{$loc}' type='radio' name='fbog_options[like_location]' value='{$loc}'";
		if ( $loc == $selected ) { echo " checked"; }
		echo " />";
		echo "<label for='location-{$loc}'>". $label ."</label>";
		echo "</li>";
	}
	echo "</ul>";
	
}

function fbog_like_layout() {
	
	$options = get_option( 'fbog_options' );
	$selected = $options['layout'];
	
	$layouts = array( 
		array( 'label' => 'Standard', 'value' => 'standard' ),
		array( 'label' => 'Button Count', 'value' => 'button_count' ),
		array( 'label' => 'Box Count', 'value' => 'box_count' )
	);
	
	echo "<select id='fbog_like_layout' name='fbog_options[layout]'>";
	// List of supported types, from http://developers.facebook.com/docs/opengraph/#types
		//echo "<option value=''>--Select a layout--</option>";
		foreach ( $layouts as $lay_arr ) {
			echo "<option";
			if( $lay_arr['value'] == $selected ) {
				echo " selected";
			}
			echo ">{$lay_arr['value']}</option>";
		}
	echo "</select>";
	echo "<br>";
	echo "<small>Determines the size and amount of social context next to the button.</small>";	

}

function fbog_like_show_faces() {

	$options = get_option( 'fbog_options' );
	$selected = $options['show_faces'];
	
	echo "<input type='checkbox' name='fbog_options[show_faces]'";
	if ( !!$selected ) { echo " checked"; }
	echo " />";
	echo " ";
	echo "<small>Check this to show FB profile pictures below your button</small>";
	
}

function fbog_like_verb() {

	$options = get_option( 'fbog_options' );
	$selected = $options['verb'];
	
	$verbs = array( 'like', 'recommend' );
	
	echo "<select id='fbog_like_verb' name='fbog_options[verb]'>";
	foreach ( $verbs as $verb ) {
		echo "<option";
		if ( $verb == $selected ) { echo " selected"; }
		echo ">{$verb}</option>";
	}
	echo "</select>";
	echo "<br>";
	echo "<small>Currently only 'like' and 'recommend' are supported.</small>";

}

function fbog_like_font() {

}

function fbog_like_color_scheme() {
	
	$options = get_option( 'fbog_options' );
	$selected = $options['colorscheme'];
	
	$colors = array( 'light', 'dark' );
	
	echo "<select id='fbog_like_color_scheme' name='fbog_options[colorscheme]'>";
	foreach ( $colors as $col ) {
		echo "<option value='{$col}'";
		if ( $col == $selected ) { echo " selected"; }
		echo ">{$col}</option>";
	}
	echo "</select>";
	
	echo "<br>";
	echo "<small>Currently only 'light' and 'dark' are available.</small>";

}

function fbog_like_width() {

	$options = get_option( 'fbog_options' );
	$selected = $options['width'];
	if ( !$selected ) { $selected = "300"; }
	
	echo "<input id='fbog_like_width' name='fbog_options[width]' type='text' value='{$selected}' />";
	echo "<br>";
	echo "<small>Width of the rendered plugin on the page, in pixels</small>";
	
}


// Build the actual options page
function fbog_create_options_page() {
	?>
	<div class="wrap">
		<div class="title">
			<img class="icon" src="<?=THIS_IMAGE_DIR?>/f_logo.png" width="40px" height="40px" />
			<h2>FBOG Settings</h2>
			<div class="clear"></div>
		</div>
		<div class="option-content">
			<small>Explanations from <a href="http://developers.facebook.com/docs/opengraph/">http://developers.facebook.com/docs/opengraph/</a></small>
			<p>Open Graph tags are <span class="pre"><?php echo esc_attr('<meta>'); ?></span> tags that you add to the <span class="pre"><?php echo esc_attr('<head>'); ?></span> of your website to describe the entity your page represents (university, band, restaurant, etc.). They tell Facebook how to display information about your page when someone shares or likes it.</p>
			<p>An Open Graph tag looks like this:</p>
			<pre><?php echo esc_attr('<meta property="og:tag name" content="tag value"/>'); ?></pre>		
			<p>This plugin adds Open Graph tags to the <span class="pre"><?php echo esc_attr('<head>'); ?></span> of each WordPress page, based on the type of page, information about your site, and the defaults you can set below.</p>
			
			<form action="options.php" method="post">
			<?php settings_fields( 'fbog_options' ); ?>
			<?php do_settings_sections( 'fbog-plugin-settings' ); ?>
			<input name="Submit" type="submit" value="Save Changes" /> 
			</form>
	
		</div>
		<div class="for-developers">
			<h3>For Developers</h3>
			<p>If you want to drop a like button onto a page, you have two options. Use this PHP function in your template files:</p>
			<pre><?php echo esc_attr('<?php'); ?><br>    <?php echo esc_attr('fbog_insert_like_button();'); ?><br><?php echo esc_attr('?>'); ?></pre>
			<p>or drop this shortcode into the editor:</p>
			<pre>[fbog-like]</pre>
			<p>Note: The shortcode can be helpful if you want to allow users to add the like button on a per post basis.</p>
		</div>

		<div class="clear"></div>
	</div>
	<?php
}

function fbog_generate_like_button($defaults = array(
	'url' => '',
	'layout' => 'standard',
	'show_faces' => 'false',
	'width' => '300px',
	'verb' => 'like',
	'font' => '',
	'colorscheme' => 'light'
)) {
	$options = get_option( 'fbog_options' );
	$like_options = $options['like_options'];
	
	foreach ( $defaults as $key => $value ) {
		$$key = !!$options[$key] ? urlencode( $options[$key] ) : urlencode( $value );
	}
	
	$show_faces = !$show_faces || $show_faces == "false" ? "false" : "true";
	
	if ( $layout == "standard" ) {
		$height = $show_faces == 'true' ? '80' : '35';
	}
	elseif ( $layout == "button_count" ) { $height = "20"; }
	else ( $height = "65" );
	
	$url = urlencode( get_permalink() );
	
	$code = "<div class='fbog-share-line'>";
	
	if ( function_exists('st_makeEntries') ) { $code .= st_makeEntries(); } 
	
	$code .= "<div class='fbog-like' style='width: {$width};'>";
	$code .= '<iframe src="http://www.facebook.com/plugins/like.php?';
	
	$query_string = array(
		"href={$url}", 
		"layout={$layout}", 
		"show_faces={$show_faces}",
		"width={$width}",
		"action={$verb}",
		"font={$font}",
		"colorscheme={$colorscheme}",
		"height={$height}"
	);
	$code .= join( "&amp;", $query_string );
	
	$code .= "\" scrolling=\"no\" \"frameborder=\"0\" style=\"border:none; overflow:hidden; width:450px; height:80px;\" allowTransparency=\"true\"";

	
	$code .= "\"></iframe>";
	$code .= "</div>";
	
	$code .= "</div><!-- end .fbog-share-line -->";
	
	return $code;
	
}

function fbog_add_like_filter( $filter, $loc ) {
	
	if ( $loc == "top" ) {
		add_filter( $filter, 'fbog_like_button_before_content' );
	}
	elseif ( $loc == "top_and_bottom" ) {
		add_filter( $filter, 'fbog_like_button_before_after_content' );
	}
	else {
		add_filter( $filter, 'fbog_like_button_after_content' );
	}

}

function fbog_add_like_button() {
	global $post;
	$options = get_option( 'fbog_options' );
	$like_options = $options['like_options'];
	$post_types = array( 'post', 'page' );
	
	if ( function_exists( 'get_post_types' ) ) {
		$post_types = get_post_types( '', 'objects' );
	}

	$loc = $options['like_location'];
	
	if ( function_exists( 'get_post_type' ) ) { 
		$pt = get_post_type( $post );
	}
	
	foreach( $post_types as $post_type ) {
		$name = !!$post_type->name ? $post_type->name : $post_type;
		if( $pt == $name && in_array( $name, $like_options ) ) {
			fbog_add_like_filter( 'the_content', $loc );
			//add_filter( 'the_content', 'fbog_like_button_after_content' );
		}
	}
	
	if( is_array( $like_options ) && in_array( 'in-loop', $like_options ) ) {
		fbog_add_like_filter( 'the_excerpt', $loc );
		//add_filter( 'the_excerpt', 'fbog_like_button_after_content' );
	}

}
add_action( 'wp_head', 'fbog_add_like_button', 10 );


function fbog_like_button_after_content( $content ) {
	return $content . fbog_generate_like_button();
}

function fbog_like_button_before_content( $content ) {
	return fbog_generate_like_button() . $content;
}

function fbog_like_button_before_after_content ( $content ) {
	
	return fbog_generate_like_button() . $content . fbog_generate_like_button();
}
