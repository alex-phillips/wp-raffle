<?php
/*
* Wp entry Shortcode
* A shortcode created to display a entry or series of entries when used in the editor or other areas
*/


//defines the functionality for the entry shortcode
class wp_entry_shortcode
{

	//on initialize
	public function __construct()
	{
		add_action('init', array($this, 'register_entry_shortcodes')); //shortcodes
	}

	//entry shortcode
	public function register_entry_shortcodes()
	{
		add_shortcode('wp_entries', array($this, 'entry_shortcode_output'));
	}

	//shortcode display
	public function entry_shortcode_output($atts, $content = '', $tag)
	{

		//get the global wp_simple_entries class
		global $wp_simple_entries;

		//build default arguments
		$arguments = shortcode_atts(array(
			'entry_id' => '',
			'number_of_entries' => -1
		), $atts, $tag);

		//uses the main output function of the entry class
		$html = $wp_simple_entries->get_entries_output($arguments);

		return $html;
	}
}
$wp_entry_shortcode = new wp_entry_shortcode;
