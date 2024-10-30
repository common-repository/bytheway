<?php
/*
Plugin Name: ByTheWay Annotations for Wordpress
Plugin URI: http://btw.wechselberger.org
Description: This plugin provides nested shortcodes for displaying and hiding annotations in posts and pages.
Version: 1.0.1
Author: Ulrich Wechselberger
Author URI: http://www.wechselberger.org/
License: GPL2
*/


/*  Copyright 2013  Ulrich Wechselberger  (email : post@ulrichwechselberger.de)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/



class btw {

	/* ----------------------------------------------------
	 * Set/Get allowed attributes and their default values
	 ---------------------------------------------------- */
	static $allowedattributes = array();
	
	static function checkoptions() {
	
		//check if options are already setup. If not, apply default settings (usually only applicable after first installation)
		if(!get_option('btw_general')) {
			$btw_general = array(
					'htmltag' => 'span',		// html tag the shortcodes are replaced with
					'effecttype' => 'fade',		// jQuery effect for text toggling
					'effectoptions' => '150',	// jQuery effect options
					'removeps' => '0'			// use </br></br> instead of paragraphs (workaround. Switch to 1 if you use annotations containing linebreaks)
			);
			add_option('btw_general', $btw_general);
		} 
		if(!get_option('btw_defaultatts')) {
			$defaultatts = array(
					'state' => 'collapsed', 		// annotations are collapsed 
					'mybuttonclass' => '',        	// no second class is used within annotations
					'myannotationclass' => '',      // no second class is used within annotations
					'labelexpanded' => '-',  		// expanded buttons contain a "-" (indicating a closing action)
					'labelcollapsed' => '+',  		// collapsed buttons contain a "+" (indicating an expandeding action)
					'tooltipexpanded' => 'Collapse annotation',  // tooltip for collapsed buttons
					'tooltipcollapsed' => 'Expand annotation'    // tooltip for expanded buttons
			);
			add_option('btw_defaultatts', $defaultatts);
		} 
		if(!get_option('btw_modedefaults')) {
			$modedefaults = array(
					'modetag' => 'a',
					'showmodelabel' => 'Show all annotations', 
					'hidemodelabel' => 'Hide all annotations', 
					'resetmodelabel' => 'Reset all annotations' 
			);
			add_option('btw_modedefaults', $modedefaults);
		} 		
		
	}	
	
	
	
	
	/* ----------------------------------------------------
	 * Load the jquery libraries coming with Wordpress
	 ---------------------------------------------------- */
	static function load_jq() {
		wp_enqueue_script( 'jquery' );	
		wp_enqueue_script( 'jquery-effects-core' );	

		$settings = get_option('btw_general');
		if ( $settings['effecttype'] == 'fade' ) {
			wp_enqueue_script( 'jquery-effects-fade' );	
		} elseif ( $settings['effecttype'] == 'slide') {
			wp_enqueue_script( 'jquery-effects-slide' );	
		}
	}

	
	
	/* ----------------------------------------------------
	 * Load custom css file 
	 ---------------------------------------------------- */	
	static function add_css() {
		wp_register_style( 'btwcss', plugins_url( 'styles.css' , __FILE__ ) );
		wp_enqueue_style( 'btwcss' );
	}

	

	/* ----------------------------------------------------
	 * Insert jquery function for text toggling
	 ---------------------------------------------------- */	
	static function add_scripts() {
		$settings = get_option('btw_general');
		?>
		<script>
		jQuery( document ).ready(function() {
			/* On load, hide annotations that are not supposed to be initially expanded */
			jQuery('.btw-button-collapsed').next('.btw-content').hide();
			
			/* Toggle function for single buttons */
			jQuery('.btw-button').click(function () {
				if (jQuery(this).hasClass('btw-button-expanded')) {
					jQuery(this).html(jQuery(this).data('labelcollapsed')).prop('title', jQuery(this).data('tooltipcollapsed')).removeClass("btw-button-expanded").addClass("btw-button-collapsed");
					jQuery(this).next('.btw-content').hide(<?php echo '"'. $settings['effecttype'] .'", '. $settings['effectoptions'] ?>);
				} else {
					jQuery(this).prop('title', jQuery(this).data('tooltipexpanded')).html(jQuery(this).data('labelexpanded')).removeClass("btw-button-collapsed").addClass("btw-button-expanded");
					jQuery(this).next('.btw-content').show(<?php echo '"'. $settings['effecttype'] .'", '. $settings['effectoptions'] ?>);
				}
			});
			
			/* Functions for toggling all annotations */
			jQuery('.btw-quietmode').click(function () {
				jQuery('.btw-button').hide(<?php echo '"'. $settings['effecttype'] .'", '. $settings['effectoptions'] ?>);
				jQuery('.btw-content').hide(<?php echo '"'. $settings['effecttype'] .'", '. $settings['effectoptions'] ?>);
			});
			jQuery('.btw-chattymode').click(function () {
				jQuery('.btw-button').hide(<?php echo '"'. $settings['effecttype'] .'", '. $settings['effectoptions'] ?>);
				jQuery('.btw-content').show(<?php echo '"'. $settings['effecttype'] .'", '. $settings['effectoptions'] ?>);
			});
			jQuery('.btw-resetmode').click(function () {
				jQuery('.btw-button').show(<?php echo '"'. $settings['effecttype'] .'", '. $settings['effectoptions'] ?>);
				jQuery('.btw-button-expanded').next().show(<?php echo '"'. $settings['effecttype'] .'", '. $settings['effectoptions'] ?>);
				jQuery('.btw-button-collapsed').next().hide(<?php echo '"'. $settings['effecttype'] .'", '. $settings['effectoptions'] ?>);
			});			
		});
		</script>
		<?php
	}

	
	 
	/* ----------------------------------------------------
	 * Manipulate and return the content.
	 ---------------------------------------------------- */	
	static function return_btw( $content ) {
	
		$settings = get_option('btw_general');
		$defaultatts = get_option('btw_defaultatts');
		
		
		// Creates the $attreplace array which contains stuff to be removed from the users' input 
		$attreplace = array( '"' );
		foreach ( $defaultatts as $attribute => $value ) {
			$attreplace[] = $attribute . '='; 
		}
	
	
		// Looks for appearing [btw *] shortcodes within the content and copies them into the (nested) $shortcodes[0] array
		preg_match_all ( "{\[btw.*?\]}i", $content, $shortcodes ); 													   
		

		// Creates an array which will later be filled with expandeding html code corresponding to the expandeding btw-shortcodes used within the content 
		$filtered = array();

	
		// Fills the $filtered-array with html-code, corresponding to the $shortcodes[0] array
		foreach ( $shortcodes[0] as $num => $shortcode ) {
			
			// assignes either user-defined or default attribute values to each shortcode
			foreach ( $defaultatts as $attribute => $default ) {
				if ( stristr( $shortcode, $attribute ) ) { // shortcode contains user-defined attributes and values: extract values
					// Extract user-defined attribute
					preg_match_all ( "{". $attribute ."=\".*?\"}i", $shortcode, $usedattributes );
					
					// trim user input and create dynamic variable (named after attribute)
					$$attribute = str_ireplace ( $attreplace , '' , $usedattributes[0][0] );
				} 
				else { // shortcode does not contain user-defined attributes: assign defaults
					$$attribute = $default;
				}
			}
			
			// define class, tooltip and label of buttons, according to shortcode state
			if ( $state == 'expanded' ) {
				$buttonstate = "btw-button-expanded"; 
				$label = $labelexpanded;
				$tooltip = $tooltipexpanded;
			} else {
				$buttonstate = "btw-button-collapsed"; 
				$label = $labelcollapsed;
				$tooltip = $tooltipcollapsed;
			} 		
			
			// Finally fills the $filtered-array
			// button
			@$filtered[$num] .= ' <'. $settings['htmltag'] .' data-tooltipcollapsed="'. $tooltipcollapsed .'" data-tooltipexpanded="'. $tooltipexpanded .'" data-labelcollapsed="'. $labelcollapsed .'" data-labelexpanded="'. $labelexpanded .'" title="'. $tooltip .'" class="btw-button '. $buttonstate .' '. $mybuttonclass .' ">'. $label .'</'. $settings['htmltag'] .'> ';
			// content
			@$filtered[$num] .= ' <'. $settings['htmltag'] .' class="btw-content '. $myannotationclass .'" > ';

			
		}
		
		// Replaces shortcodes with prepared html codes
		$content = str_replace ( $shortcodes[0], $filtered, $content);
		$content = str_ireplace ( '[/btw]', '</'.$settings['htmltag'] .'>', $content);
		
		
		// If requested, replace paragraphs with line breaks
		if ($settings['removeps']) {
			$content = self::removeps($content);
		}
			
		

		// Returns content to wordpress for output
		return $content;
	}
	
	
	
	/* ----------------------------------------------------
	 * Replace paragraphs within annotations with double line breaks in order to prevent auto closed annotations
	 ---------------------------------------------------- */
	static function removeps($content) {
		$settings = get_option('btw_general');
		$content = str_ireplace ( "</p>\n<p>", "</br>\n</br>\n", $content);
		return $content;
	}
	
	

	/* ----------------------------------------------------
	 * Shortcodes for chatty and quiet mode
	 ---------------------------------------------------- */
	// Shortcode for quietmode
	static function shortcode_quiet( $atts ) {
		$settings = get_option('btw_modedefaults');
		extract( shortcode_atts( array(
			'label' => $settings['hidemodelabel'],
			'tag' => $settings['modetag']
		), $atts ) );
		return '<'. $tag . ' class="btw-quietmode">'. $label . '</'. $tag .'>';
	}
	// Shortcode for chattymode
	static function shortcode_chatty( $atts ) {
		$settings = get_option('btw_modedefaults');
		extract( shortcode_atts( array(
			'label' => $settings['showmodelabel'],
			'tag' => $settings['modetag']
		), $atts ) );
		return '<'. $tag . ' class="btw-chattymode">'. $label .'</'. $tag .'>';
	}
	// Shortcode for resetting annotations to initial states
	static function shortcode_reset( $atts ) {
	$settings = get_option('btw_modedefaults');
		extract( shortcode_atts( array(
			'label' => $settings['resetmodelabel'],
			'tag' => $settings['modetag']
		), $atts ) );
		return '<'. $tag . ' class="btw-resetmode">'. $label .'</'. $tag .'>';
	}	
	
	
	
	/* -------------------------------------------------------
	 * Fixing the mysterious excerpt issue with some templates
	 ------------------------------------------------------ */
	static function trim_excerpt($text = '') {
		$raw_excerpt = $text;
		if ( '' == $text ) {
			$text = get_the_content('');

			$text = strip_shortcodes( $text );
			
			// for some reason I don't get, some templates mess up the excerpt. 
			// moving the following line 5 lines down magically fixes this issue. Weird. 
			#$text = apply_filters('the_content', $text);
			$text = str_replace(']]>', ']]&gt;', $text);
			$excerpt_length = apply_filters('excerpt_length', 55);
			$excerpt_more = apply_filters('excerpt_more', ' ' . '[&hellip;]');
			$text = wp_trim_words( $text, $excerpt_length, $excerpt_more );
			// tadaaa! 
			$text = apply_filters('the_content', $text);
		}
		return apply_filters('trim_excerpt', $text, $raw_excerpt);
	}

	 
	
	/* ----------------------------------------------------
	 * Admin menus
	 ---------------------------------------------------- */
	
	// register all btw settings
	static function options_init(){
		
		// Sections
		add_settings_section('btw_defaults', 'Default Annotation Attribute Values', array('btw', 'options_defaultsdescription'), 'btw_settingspage');
		add_settings_section('btw_modedefaults', 'Default Attribute Values for Global Hide/Show/Reset buttons', array('btw', 'options_modedefaultsdescription'), 'btw_settingspage');
		add_settings_section('btw_general', 'General Settings', array('btw', 'options_generaldescription'), 'btw_settingspage');
		
		// General settings
		add_settings_field('btw_htmltag', 'Html tag for buttons and annotations (without angle brackets)', array('btw', 'options_drawhtmltag'), 'btw_settingspage', 'btw_general', array( 'label_for' => 'btw_general[htmltag]' ));
		add_settings_field('btw_effecttype', 'jQuery toggling effect type', array('btw', 'options_draweffecttype'), 'btw_settingspage', 'btw_general', array( 'label_for' => 'btw_general[effecttype]' ));
		add_settings_field('btw_effectoptions', 'jQuery toggling options ', array('btw', 'options_draweffectoptions'), 'btw_settingspage', 'btw_general', array( 'label_for' => 'btw_general[effectoptions]' ));
		add_settings_field('btw_removeps', 'Replace paragraphs with line breaks? ', array('btw', 'options_drawremoveps'), 'btw_settingspage', 'btw_general', array( 'label_for' => 'btw_general[removeps]' ));
		
		// Default annotation attribute fields
		add_settings_field('btw_state', 'Initial annotation state', array('btw', 'options_drawstate'), 'btw_settingspage', 'btw_defaults', array( 'label_for' => 'btw_defaultatts[state]' ));
		add_settings_field('btw_mybuttonclass', 'Additional button CSS class', array('btw', 'options_drawmybuttonclass'), 'btw_settingspage', 'btw_defaults', array( 'label_for' => 'btw_defaultatts[mybuttonclass]' ));
		add_settings_field('btw_myannotationclass', 'Additional annotation CSS class', array('btw', 'options_drawmyannotationclass'), 'btw_settingspage', 'btw_defaults', array( 'label_for' => 'btw_defaultatts[myannotationclass]' ));
		add_settings_field('btw_labelexpanded', 'Label on expanded annotations', array('btw', 'options_drawlabelexpanded'), 'btw_settingspage', 'btw_defaults', array( 'label_for' => 'btw_defaultatts[labelexpanded]' ));
		add_settings_field('btw_labelcollapsed', 'Label on collapsed annotations', array('btw', 'options_drawlabelcollapsed'), 'btw_settingspage', 'btw_defaults', array( 'label_for' => 'btw_defaultatts[labelcollapsed]' ));
		add_settings_field('btw_tooltipexpanded', 'Tooltip for expanded buttons', array('btw', 'options_drawtooltipexpanded'), 'btw_settingspage', 'btw_defaults', array( 'label_for' => 'btw_defaultatts[tooltipexpanded]' ));
		add_settings_field('btw_tooltipcollapsed', 'Tooltip for collapsed buttons', array('btw', 'options_drawtooltipcollapsed'), 'btw_settingspage', 'btw_defaults', array( 'label_for' => 'btw_defaultatts[tooltipcollapsed]' ));
		
		// Default mode attribute values
		add_settings_field('btw_modetag', 'Html tag for mode buttons (without angle brackets)', array('btw', 'options_drawmodetag'), 'btw_settingspage', 'btw_modedefaults', array( 'label_for' => 'btw_defaultmodeatts[modetag]' ));
		add_settings_field('btw_showmodelabel', 'Label for show all annotations switch', array('btw', 'options_drawshowmodelabel'), 'btw_settingspage', 'btw_modedefaults', array( 'label_for' => 'btw_defaultmodeatts[showmodelabel]' ));
		add_settings_field('btw_hidemodelabel', 'Label for hide all annotations switch', array('btw', 'options_drawhidemodelabel'), 'btw_settingspage', 'btw_modedefaults', array( 'label_for' => 'btw_defaultmodeatts[hidemodelabel]' ));
		add_settings_field('btw_resetmodelabel', 'Label for reset all annotations switch', array('btw', 'options_drawresetmodelabel'), 'btw_settingspage', 'btw_modedefaults', array( 'label_for' => 'btw_defaultmodeatts[resetmodelabel]' ));
		
		
		// Register Settings
		register_setting( 'btw_settings', 'btw_defaultatts', array('btw', 'sanitize_text' ));
		register_setting( 'btw_settings', 'btw_general', array('btw', 'sanitize_text' ));
		register_setting( 'btw_settings', 'btw_modedefaults', array('btw', 'sanitize_text' ));
		
	}
	
	// Sanitize user input (cf. http://wp.tutsplus.com/tutorials/the-complete-guide-to-the-wordpress-settings-api-part-7-validation-sanitisation-and-input-i/)
	static function sanitize_text($input) {
		$output = array();
		foreach( $input as $key => $value ) {
			// Check to see if the current option has a value. If so, process it.
			if( isset( $input[$key] ) ) {
				// Strip all HTML and PHP tags and properly handle quoted strings
				$output[$key] = strip_tags( stripslashes( $input[ $key ] )); 
			}
		}
		// Return the array processing any additional functions filtered by this action
		return apply_filters( 'sanitize_text', $output, $input ); 
	}
	
	// Callback functions for displaying the settings section's descriptions
	static function options_generaldescription() {
		?>
		<p>
			Messing around with these options can lead to unexpected behavior, so do not change if you do not know what you are doing.<br/>
			Quotes or HTML tags are not allowed. However, you can use HTML entities such as <code>&amp;quot;</code>.
		</p>
		<?php
	}
	static function options_defaultsdescription() {
		?>
		<p>
			Per default, ByTheWay uses the following attribute values. These can be overridden by adding attribute values within the btw-shortcodes.<br/>
			Again, quotes or HTML tags are not allowed. Use HTML entities such as <code>&amp;quot;</code> for displaying special HTML characters.
		</p>
		<?php
	}
	static function options_modedefaultsdescription() {
		?>
		<p>
			ByTheWay uses the following default values for global show/hide/reset buttons. These can be overridden by adding attribute values within the respective shortcodes.<br/>
			Quotes or HTML tags are not allowed. Use HTML entities such as <code>&amp;quot;</code> for displaying special HTML characters.
		</p>
		<?php
	}	


	
	// Callback functions for displaying input fields
	// General Settings
	static function options_drawhtmltag() {
		$btw_general = get_option('btw_general');
		echo '<input name="btw_general[htmltag]" size="40" type="text" value="'. $btw_general['htmltag'] .'" />';
	}
	static function options_draweffecttype() {
		$btw_general = get_option('btw_general');
		?>
		<select name="btw_general[effecttype]">
			<option value="fade" <?php selected( $btw_general['effecttype'], 'fade' ); ?>>fade</option>
			<option value="slide" <?php selected( $btw_general['effecttype'], 'slide' ); ?>>slide</option>
		</select>
		<?php
	}
	static function options_draweffectoptions() {
		$btw_general = get_option('btw_general');
		echo '<input name="btw_general[effectoptions]" size="40" type="text" value="'. $btw_general['effectoptions'] .'" />';
	}
	static function options_drawremoveps() {
		$btw_general = get_option('btw_general');
		?>
		<select name="btw_general[removeps]">
			<option value="1" <?php selected( $btw_general['removeps'], '1' ); ?>>yes</option>
			<option value="0" <?php selected( $btw_general['removeps'], '0' ); ?>>no</option>
		</select>		
		<?php
	}
	// Default attribute vales
	static function options_drawstate() {
		$btw_defaultatts = get_option('btw_defaultatts');
		?>
		<select name="btw_defaultatts[state]">
			<option value="collapsed" <?php selected( $btw_defaultatts['state'], 'collapsed' ); ?>>collapsed</option>
			<option value="expanded" <?php selected( $btw_defaultatts['state'], 'expanded' ); ?>>expanded</option>
		</select>		
		<?php
	}
	static function options_drawmybuttonclass() {
		$btw_defaultatts = get_option('btw_defaultatts');
		echo '<input name="btw_defaultatts[mybuttonclass]" size="20" type="text" value="'. $btw_defaultatts['mybuttonclass'] .'" />';
	}
	static function options_drawmyannotationclass() {
		$btw_defaultatts = get_option('btw_defaultatts');
		echo '<input name="btw_defaultatts[myannotationclass]" size="20" type="text" value="'. $btw_defaultatts['myannotationclass'] .'" />';
	}
	
	static function options_drawlabelexpanded() {
		$btw_defaultatts = get_option('btw_defaultatts');
		echo '<input name="btw_defaultatts[labelexpanded]" size="10" type="text" value="'. $btw_defaultatts['labelexpanded'] .'" />';
	}		
	static function options_drawlabelcollapsed() {
		$btw_defaultatts = get_option('btw_defaultatts');
		echo '<input name="btw_defaultatts[labelcollapsed]" size="10" type="text" value="'. $btw_defaultatts['labelcollapsed'] .'" />';
	}
	static function options_drawtooltipexpanded() {
		$btw_defaultatts = get_option('btw_defaultatts');
		echo '<input name="btw_defaultatts[tooltipexpanded]" size="40" type="text" value="'. $btw_defaultatts['tooltipexpanded'] .'" />';
	}		
	static function options_drawtooltipcollapsed() {
		$btw_defaultatts = get_option('btw_defaultatts');
		echo '<input name="btw_defaultatts[tooltipcollapsed]" size="40" type="text" value="'. $btw_defaultatts['tooltipcollapsed'] .'" />';
	}
	// Default mode attributes
	static function options_drawmodetag() {
		$btw_modedefaults = get_option('btw_modedefaults');
		echo '<input name="btw_modedefaults[modetag]" size="20" type="text" value="'. $btw_modedefaults['modetag'] .'" />';
	}
	static function options_drawshowmodelabel() {
		$btw_modedefaults = get_option('btw_modedefaults');
		echo '<input name="btw_modedefaults[showmodelabel]" size="20" type="text" value="'. $btw_modedefaults['showmodelabel'] .'" />';
	}
	static function options_drawhidemodelabel() {
		$btw_modedefaults = get_option('btw_modedefaults');
		echo '<input name="btw_modedefaults[hidemodelabel]" size="20" type="text" value="'. $btw_modedefaults['hidemodelabel'] .'" />';
	}
	static function options_drawresetmodelabel() {
		$btw_modedefaults = get_option('btw_modedefaults');
		echo '<input name="btw_modedefaults[resetmodelabel]" size="20" type="text" value="'. $btw_modedefaults['resetmodelabel'] .'" />';
	}
	
	// add the link to the options page
	static function options_addmenu() {
		add_options_page('ByTheWay Settings', 'ByTheWay', 'manage_options', 'btw-plugin', array('btw', 'options_drawpage'));
	}
	
	// draw the options page
	static function options_drawpage() {
		?>
		<div class="wrap">
			<div id="icon-options-general" class="icon32"><br></div>
			
			<h2>ByTheWay Annotations</h2>
			
			<form action="options.php" method="post">
			<?php 
			// This outputs the hidden bits that we need to make our options page both do what we want and to make it secure with a nonce. The string “plugin-options” can be anything, as long as it’s unique. There is another call we’re going to have to make with this same string late
			settings_fields('btw_settings');
			
			// This is going to output all of our input fields. Text input boxes, radio fields, anything we like. Obviously though, we have to tell it what those fields are and look like somewhere. We do both of these things in the next sectio
			do_settings_sections('btw_settingspage'); 
			
			submit_button();
			?>
			</form>
		</div>
		<?php
	}
	
	

	
	
}

/* ----------------------------------------------------
 * Action hooks and shortcodes
 ---------------------------------------------------- */

if ( is_admin() ){ // these are only needed in the admin backend
	add_action('admin_init', array('btw', 'options_init'));
	add_action('admin_menu', array('btw', 'options_addmenu'));
	add_action('wp_loaded', array('btw', 'checkoptions'));
} else { // these are only needed in the frontend
	add_action('init', array('btw', 'load_jq'), 100);
	add_action ( 'wp_head', array('btw', 'add_css'), 100);
	add_action ( 'wp_footer', array('btw', 'add_scripts'), 100);
	add_filter('the_content', array('btw', 'return_btw'), 999);
	
	add_shortcode( 'btw-quiet', array('btw', 'shortcode_quiet'));
	add_shortcode( 'btw-chatty', array('btw', 'shortcode_chatty'));
	add_shortcode( 'btw-reset', array('btw', 'shortcode_reset'));
	
	add_filter( 'the_excerpt', 'do_shortcode');
	
	// Fix for the mysterious excerpt issue in some templates
	remove_filter( 'get_the_excerpt', 'wp_trim_excerpt'  ); 
	add_filter( 'get_the_excerpt', array('btw', 'trim_excerpt' ));
	add_filter( 'the_excerpt', 'do_shortcode' ); 

}



 



