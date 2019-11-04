<?php
/*
 * Wp entry Widget
 * Defines the widget to be used to showcase single or multiple entries
 */


//main widget used for displaying entries
class wp_entry_widget extends WP_widget
{

    //initialise widget values
    public function __construct()
    {
        //set base values for the widget (override parent)
        parent::__construct(
            'wp_entry_widget',
            'WP entry Widget',
            array('description' => 'A widget that displays your entries')
        );
        add_action('widgets_init', array($this,'register_wp_entry_widgets'));
    }

    //handles public display of the widget
    //$args - arguments set by the widget area, $instance - saved values
    public function widget($args, $instance)
    {

        //get wp_simple_entry class (as it builds out output)
        global $wp_simple_entries;

        //pass any arguments if we have any from the widget
        $arguments = array();
        //if we specify a entry

        //if we specify a single entry
        if ($instance['entry_id'] != 'default') {
            $arguments['entry_id'] = $instance['entry_id'];
        }
        //if we specify a number of entries
        if ($instance['number_of_entries'] != 'default') {
            $arguments['number_of_entries'] = $instance['number_of_entries'];
        }

        //get the output
        $html = '';

        $html .= $args['before_widget'];
        $html .= $args['before_title'];
        $html .= 'entries';
        $html .= $args['after_title'];
        //uses the main output function of the entry class
        $html .= $wp_simple_entries->get_entries_output($arguments);
        $html .= $args['after_widget'];

        echo $html;
    }

    //handles the back-end admin of the widget
    //$instance - saved values for the form
    public function form($instance)
    {
        //collect variables
        $entry_id = (isset($instance['entry_id']) ? $instance['entry_id'] : 'default');
        $number_of_entries = (isset($instance['number_of_entries']) ? $instance['number_of_entries'] : 5); ?>
		<p>Select your options below</p>
		<p>
			<label for="<?php echo $this->get_field_name('entry_id'); ?>">entry to display</label>
			<select class="widefat" name="<?php echo $this->get_field_name('entry_id'); ?>" id="<?php echo $this->get_field_id('entry_id'); ?>" value="<?php echo $entry_id; ?>">
				<option value="default">All entries</option>
				<?php
                $args = array(
                    'posts_per_page'	=> -1,
                    'post_type'			=> 'wp_entries'
                );
        $entries = get_posts($args);
        if ($entries) {
            foreach ($entries as $entry) {
                if ($entry->ID == $entry_id) {
                    echo '<option selected value="' . $entry->ID . '">' . get_the_title($entry->ID) . '</option>';
                } else {
                    echo '<option value="' . $entry->ID . '">' . get_the_title($entry->ID) . '</option>';
                }
            }
        } ?>
			</select>
		</p>
		<p>
			<small>If you want to display multiple entries select how many below</small><br/>
			<label for="<?php echo $this->get_field_id('number_of_entries'); ?>">Number of entries</label>
			<select class="widefat" name="<?php echo $this->get_field_name('number_of_entries'); ?>" id="<?php echo $this->get_field_id('number_of_entries'); ?>" value="<?php echo $number_of_entries; ?>">
				<option value="default">All</option>
			</select>
		</p>
		<?php
    }

    //handles updating the widget
    //$new_instance - new values, $old_instance - old saved values
    public function update($new_instance, $old_instance)
    {
        $instance = array();

        $instance['entry_id'] = $new_instance['entry_id'];
        $instance['number_of_entries'] = $new_instance['number_of_entries'];

        return $instance;
    }

    //registers our widget for use
    public function register_wp_entry_widgets()
    {
        register_widget('wp_entry_widget');
    }
}
$wp_entry_widget = new wp_entry_widget;

?>
