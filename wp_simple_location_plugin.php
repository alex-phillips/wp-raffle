<?php
/*
Plugin Name: Wordpress Simple entry Plugin
Plugin URI:  https://github.com/simonrcodrington/Introduction-to-WordPress-Plugins---entry-Plugin
Description: Creates an interfaces to manage store / business entries on your website. Useful for showing entry based information quickly. Includes both a widget and shortcode for ease of use.
Version:     1.0.0
Author:      Simon Codrington
Author URI:  http://www.simoncodrington.com.au
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

class wp_simple_entry
{

    //properties
    private $wp_raffle_trading_hour_days = array();

    //magic function (triggered on initialization)
    public function __construct()
    {
        add_action('init', array($this, 'register_entry_content_type')); //register entry content type
        add_action('add_meta_boxes', array($this, 'add_entry_meta_boxes')); //add meta boxes
        add_action('save_post_wp_raffles', array($this, 'save_entry')); //save entry
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts_and_styles')); //admin scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_public_scripts_and_styles')); //public scripts and styles

        // Add custom columns for viewing
        add_action('manage_edit-wp_raffles_columns', [$this, 'entry_columns']);
        add_action('manage_wp_raffles_posts_custom_column', [$this, 'handle_custom_columns'], 10, 2);

        // Add export button and functionality
        add_action('restrict_manage_posts', [$this, 'add_export_button']);
        add_action('init', [$this, 'export_all_entries']);

        // Function to handle user-submitted form
        add_shortcode('enter_raffle_page', [$this, 'enter_raffle_page']);

        add_filter('the_content', array($this, 'prepend_entry_meta_to_content')); //gets our meta data and dispayed it before the content

        register_activation_hook(__FILE__, array($this, 'plugin_activate')); //activate hook
        register_deactivation_hook(__FILE__, array($this, 'plugin_deactivate')); //deactivate hook
    }

    public function add_export_button($post_type)
    {
        $screen = get_current_screen();

        if ($this->check_user_role('administrator') && $post_type === 'wp_raffles' && isset($screen->parent_file) && preg_match('#edit\.php#', $screen->parent_file)) {
            echo <<<__HTML__
            <input type="submit" name="export_all_raffle_entries" id="export_all_raffle_entries" class="button button-primary" value="Export All Posts">
            <script type="text/javascript">
                jQuery(function($) {
                    $('#export_all_posts').insertAfter('#post-query-submit');
                });
            </script>
__HTML__;
        }
    }

    public function enter_raffle_page()
    {
        if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['new-raffle-entry'])) {
            $name = $_POST['wp_raffle_name'];
            $phone = $_POST['wp_raffle_phone'];
            $email = $_POST['wp_raffle_email'];

            // Check to make sure phone number and email are unique
            $query = new WP_Query([
                'post_type' => 'wp_raffles',
                'meta_query' => [
                    [
                        'key' => 'wp_raffle_phone',
                        'value' => $phone,
                        'compare' => '=',
                    ],
                ],
            ]);
            if ($query->have_posts()) {
                return "It looks like you've already signed up!";
            }

            $query = new WP_Query([
                'post_type' => 'wp_raffles',
                'meta_query' => [
                    [
                        'key' => 'wp_raffle_email',
                        'value' => $email,
                        'compare' => '=',
                    ],
                ],
            ]);
            if ($query->have_posts()) {
                return "It looks like you've already signed up!";
            }

            $id = wp_insert_post([
                'post_type' => 'wp_raffles',
                'post_author' => 1,
                'post_status' => 'private',
            ]);

            $this->save_entry($id);

            return "You've entered the contest! Your raffle number is $id";
        }

        $nonce = wp_nonce_field('wp_raffle_nonce', 'wp_raffle_nonce_field', true, false);

        return <<<__HTML__
<form method="post">
    <input type="hidden" name="new-raffle-entry">
    $nonce
    Name: <input type="text" name="wp_raffle_name"><br>
    Phone number: <input type="text" name="wp_raffle_phone"><br>
    Email: <input type="email" name="wp_raffle_email"><br>
    <input type="submit" value="Submit">
</form>
__HTML__;
    }

    public function export_all_entries()
    {
        if (isset($_GET['export_all_raffle_entries'])) {
            if ($this->check_user_role('administrator')) {
                $arg = array(
                    'post_type' => 'wp_raffles',
                    'posts_per_page' => -1,
                );

                global $post;
                $arr_post = get_posts($arg);
                if ($arr_post) {

                    header('Content-type: text/csv');
                    header('Content-Disposition: attachment; filename="entries.csv"');
                    header('Pragma: no-cache');
                    header('Expires: 0');

                    $file = fopen('php://output', 'w');

                    fputcsv($file, array('Name', 'Phone #', 'Email', 'Raffle #'));

                    foreach ($arr_post as $post) {
                        fputcsv($file, [
                            get_post_meta($post->ID, 'wp_raffle_name', true),
                            get_post_meta($post->ID, 'wp_raffle_phone', true),
                            get_post_meta($post->ID, 'wp_raffle_email', true),
                            $post->ID,
                        ]);
                    }

                    exit();
                }
            }
        }
    }

    private function check_user_role($roles)
    {
        if (!is_array($roles)) {
            $roles = [$roles];
        }

        $user = wp_get_current_user();
        if (array_intersect($roles, $user->roles)) {
            return true;
        }

        return false;
    }

    public function entry_columns($columns)
    {
        return [
            'cb' => '&lt;input type="checkbox" />',
            'name' => __('Name'),
            'phone' => __('Phone'),
            'id' => __('Raffle #'),
        ];
    }

    public function handle_custom_columns($column, $id)
    {
        global $post;
        switch ($column) {
            case 'name':
                echo get_post_meta($id, 'wp_raffle_name', true);
                break;
            case 'phone':
                echo get_post_meta($id, 'wp_raffle_phone', true);
                break;
            case 'id':
                echo $id;
                break;
            default:
                break;
        }
    }

    //register the entry content type
    public function register_entry_content_type()
    {
        //Labels for post type
        $labels = array(
            'name'               => 'entry',
            'singular_name'      => 'entry',
            'menu_name'          => 'Raffle Entries',
            'name_admin_bar'     => 'Entry',
            'add_new'            => 'Add New',
            'add_new_item'       => 'Add New Entry',
            'new_item'           => 'New Entry',
            'edit_item'          => 'Edit Entry',
            'view_item'          => 'View Entry',
            'all_items'          => 'All Entries',
            'search_items'       => 'Search Entries',
            'parent_item_colon'  => 'Parent Entry:',
            'not_found'          => 'No entries found.',
            'not_found_in_trash' => 'No entries found in Trash.',
        );
        //arguments for post type
        $args = array(
            'labels'            => $labels,
            'public'            => false,
            'publicly_queryable' => false,
            'show_ui'           => true,
            'show_in_nav'       => true,
            'query_var'         => true,
            'hierarchical'      => false,
            'supports'          => array(''),
            'has_archive'       => true,
            'menu_position'     => 20,
            'show_in_admin_bar' => true,
            'menu_icon'         => 'dashicons-tickets-alt',
            'rewrite'            => array('slug' => 'entries', 'with_front' => 'true'),
            'can_export' => true,
        );
        //register post type
        register_post_type('wp_raffles', $args);
    }

    //adding meta boxes for the entry content type*/
    public function add_entry_meta_boxes()
    {
        add_meta_box(
            'wp_raffle_meta_box', //id
            'Entry Information', //name
            array($this, 'entry_meta_box_display'), //display function
            'wp_raffles', //post type
            'normal', //entry
            'default' //priority
        );
    }

    //display function used for our custom entry meta box*/
    public function entry_meta_box_display($post)
    {

        //set nonce field
        wp_nonce_field('wp_raffle_nonce', 'wp_raffle_nonce_field');

        //collect variables
        $wp_raffle_name = get_post_meta($post->ID, 'wp_raffle_name', true);
        $wp_raffle_phone = get_post_meta($post->ID, 'wp_raffle_phone', true);
        $wp_raffle_email = get_post_meta($post->ID, 'wp_raffle_email', true);
        $wp_raffle_number = get_post_meta($post->ID, 'wp_raffle_number', true); ?>
            <p>Enter additional information about your entry </p>
            <div class="field-container">
                <?php
                        //before main form elementst hook
                        do_action('wp_raffle_admin_form_start'); ?>
                <div class="field">
                    <label for="wp_raffle_name">Name</label>
                    <input type="tel" name="wp_raffle_name" id="wp_raffle_name" value="<?php echo $wp_raffle_name; ?>" />
                </div>
                <div class="field">
                    <label for="wp_raffle_phone">Phone</label>
                    <input type="tel" name="wp_raffle_phone" id="wp_raffle_phone" value="<?php echo $wp_raffle_phone; ?>" />
                </div>
                <div class="field">
                    <label for="wp_raffle_email">Email</label>
                    <input type="email" name="wp_raffle_email" id="wp_raffle_email" value="<?php echo $wp_raffle_email; ?>" />
                </div>
                <div class="field">
                    <label for="wp_raffle_email">Raffle Number</label>
                    <input type="text" disabled name="wp_raffle_number" id="wp_raffle_number" value="<?php echo $wp_raffle_number; ?>" />
                </div>
                <?php
                        //after main form elementst hook
                        do_action('wp_raffle_admin_form_end'); ?>
            </div>
    <?php
        }

        //triggered on activation of the plugin (called only once)
        public function plugin_activate()
        {

            //call our custom content type function
            $this->register_entry_content_type();
            //flush permalinks
            flush_rewrite_rules();
        }

        //trigered on deactivation of the plugin (called only once)
        public function plugin_deactivate()
        {
            //flush permalinks
            flush_rewrite_rules();
        }

        //append our additional meta data for the entry before the main content (when viewing a single entry)
        public function prepend_entry_meta_to_content($content)
        {
            global $post, $post_type;

            //display meta only on our entries (and if its a single entry)
            if ($post_type == 'wp_raffles' && is_singular('wp_raffles')) {

                //collect variables
                $wp_raffle_id = $post->ID;
                $wp_raffle_name = get_post_meta($post->ID, 'wp_raffle_name', true);
                $wp_raffle_phone = get_post_meta($post->ID, 'wp_raffle_phone', true);
                $wp_raffle_email = get_post_meta($post->ID, 'wp_raffle_email', true);
                $wp_raffle_number = get_post_meta($post->ID, 'wp_raffle_number', true);

                //display
                $html = '';

                $html .= '<section class="meta-data">';

                //hook for outputting additional meta data (at the start of the form)
                do_action('wp_raffle_meta_data_output_start', $wp_raffle_id);

                $html .= '<p>';
                //name
                if (!empty($wp_raffle_name)) {
                    $html .= '<b>Name</b>' . $wp_raffle_name . '</br>';
                }
                //phone
                if (!empty($wp_raffle_phone)) {
                    $html .= '<b>Phone</b>' . $wp_raffle_phone . '</br>';
                }
                //email
                if (!empty($wp_raffle_email)) {
                    $html .= '<b>Email</b>' . $wp_raffle_email . '</br>';
                }
                //number
                if (!empty($wp_raffle_number)) {
                    $html .= '<b>Number</b>' . $wp_raffle_number . '</br>';
                }
                $html .= '</p>';

                //hook for outputting additional meta data (at the end of the form)
                do_action('wp_raffle_meta_data_output_end', $wp_raffle_id);

                $html .= '</section>';
                $html .= $content;

                return $html;
            } else {
                return $content;
            }
        }

        //main function for displaying entries (used for our shortcodes and widgets)
        public function get_entries_output($arguments = "")
        {

            //default args
            $default_args = array(
                'entry_id'    => '',
                'number_of_entries'    => -1
            );

            //update default args if we passed in new args
            if (!empty($arguments) && is_array($arguments)) {
                //go through each supplied argument
                foreach ($arguments as $arg_key => $arg_val) {
                    //if this argument exists in our default argument, update its value
                    if (array_key_exists($arg_key, $default_args)) {
                        $default_args[$arg_key] = $arg_val;
                    }
                }
            }

            //output
            $html = '';

            $entry_args = array(
                'post_type'        => 'wp_raffle',
                'posts_per_page' => $default_args['number_of_entries'],
                'post_status'    => 'private'
            );
            //if we passed in a single entry to display
            if (!empty($default_args['entry_id'])) {
                $entry_args['include'] = $default_args['entry_id'];
            }

            $entries = get_posts($entry_args);
            //if we have entries
            if ($entries) {
                $html .= '<article class="entry_list cf">';
                //foreach entry
                foreach ($entries as $entry) {
                    $html .= '<section class="entry">';
                    //collect entry data
                    $wp_raffle_id = $entry->ID;
                    $wp_raffle_title = get_the_title($wp_raffle_id);
                    $wp_raffle_thumbnail = get_the_post_thumbnail($wp_raffle_id, 'thumbnail');
                    $wp_raffle_content = apply_filters('the_content', $entry->post_content);
                    if (!empty($wp_raffle_content)) {
                        $wp_raffle_content = strip_shortcodes(wp_trim_words($wp_raffle_content, 40, '...'));
                    }
                    $wp_raffle_permalink = get_permalink($wp_raffle_id);
                    $wp_raffle_name = get_post_meta($wp_raffle_id, 'wp_raffle_name', true);
                    $wp_raffle_phone = get_post_meta($wp_raffle_id, 'wp_raffle_phone', true);
                    $wp_raffle_email = get_post_meta($wp_raffle_id, 'wp_raffle_email', true);
                    $wp_raffle_number = get_post_meta($wp_raffle_id, 'wp_raffle_number', true);

                    //title
                    $html .= '<h2 class="title">';
                    $html .= '<a href="' . $wp_raffle_permalink . '" title="view entry">';
                    $html .= $wp_raffle_title;
                    $html .= '</a>';
                    $html .= '</h2>';

                    //image & content
                    if (!empty($wp_raffle_thumbnail) || !empty($wp_raffle_content)) {
                        $html .= '<p class="image_content">';
                        if (!empty($wp_raffle_thumbnail)) {
                            $html .= $wp_raffle_thumbnail;
                        }
                        if (!empty($wp_raffle_content)) {
                            $html .=  $wp_raffle_content;
                        }

                        $html .= '</p>';
                    }

                    //phone & email output
                    if (!empty($wp_raffle_phone) || !empty($wp_raffle_email)) {
                        $html .= '<p class="phone_email">';
                        if (!empty($wp_raffle_name)) {
                            $html .= '<b>Name: </b>' . $wp_raffle_name . '</br>';
                        }
                        if (!empty($wp_raffle_phone)) {
                            $html .= '<b>Phone: </b>' . $wp_raffle_phone . '</br>';
                        }
                        if (!empty($wp_raffle_email)) {
                            $html .= '<b>Email: </b>' . $wp_raffle_email;
                        }
                        $html .= '</p>';
                    }

                    //readmore
                    $html .= '<a class="link" href="' . $wp_raffle_permalink . '" title="view entry">View entry</a>';
                    $html .= '</section>';
                }
                $html .= '</article>';
                $html .= '<div class="cf"></div>';
            }

            return $html;
        }

        //triggered when adding or editing a entry
        public function save_entry($post_id)
        {
            //check for nonce
            if (!isset($_POST['wp_raffle_nonce_field'])) {
                return $post_id;
            }
            //verify nonce
            if (!wp_verify_nonce($_POST['wp_raffle_nonce_field'], 'wp_raffle_nonce')) {
                return $post_id;
            }
            //check for autosave
            if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
                return $post_id;
            }

            //get our phone, email
            $wp_raffle_name = isset($_POST['wp_raffle_name']) ? sanitize_text_field($_POST['wp_raffle_name']) : '';
            $wp_raffle_phone = isset($_POST['wp_raffle_phone']) ? sanitize_text_field($_POST['wp_raffle_phone']) : '';
            $wp_raffle_email = isset($_POST['wp_raffle_email']) ? sanitize_text_field($_POST['wp_raffle_email']) : '';

            //update phone, email
            update_post_meta($post_id, 'wp_raffle_name', $wp_raffle_name);
            update_post_meta($post_id, 'wp_raffle_phone', $wp_raffle_phone);
            update_post_meta($post_id, 'wp_raffle_email', $wp_raffle_email);
            update_post_meta($post_id, 'wp_raffle_number', $post_id);

            //entry save hook
            //used so you can hook here and save additional post fields added via 'wp_raffle_meta_data_output_end' or 'wp_raffle_meta_data_output_end'
            do_action('wp_raffle_admin_save', $post_id);
        }

        //enqueus scripts and stles on the back end
        public function enqueue_admin_scripts_and_styles()
        {
            wp_enqueue_style('wp_raffle_admin_styles', plugin_dir_url(__FILE__) . '/css/wp_raffle_admin_styles.css');
        }

        //enqueues scripts and styled on the front end
        public function enqueue_public_scripts_and_styles()
        {
            wp_enqueue_style('wp_raffle_public_styles', plugin_dir_url(__FILE__) . '/css/wp_raffle_public_styles.css');
        }
    }
    $wp_simple_entries = new wp_simple_entry;

    //include shortcodes
    include(plugin_dir_path(__FILE__) . 'inc/wp_raffle_shortcode.php');
    //include widgets
    include(plugin_dir_path(__FILE__) . 'inc/wp_raffle_widget.php');



    ?>
