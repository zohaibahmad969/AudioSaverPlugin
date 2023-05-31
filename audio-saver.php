<?php
/*
Plugin Name: Audio Saver
Plugin URI: https://wordpress.org
Description: This plugin provides a shortcode [audio-saver] for frontend. User can record the audio and this audio will be saved to wordpress media and this audio will be addedd to a Custom Post Type AudioSaver
Version: 1.0.0
Author: Zohaib Ahmad
Author URI: https://www.zohaibahmad.com/
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: audio-saver
Domain Path: /languages
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

// Enqueue JS files
function audio_saver_enqueue_scripts()
{

    wp_enqueue_script('recorder-js', plugin_dir_url(__FILE__) . 'assets/recorder.js', array(), '1.0.0', true);
    wp_enqueue_script('main-js', plugin_dir_url(__FILE__) . 'assets/main.js', array(), '1.0.0', true);

    // Localize script with ajaxurl
    wp_localize_script('audiosaver-js', 'audiosaverdata', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
    ));

}
add_action('wp_enqueue_scripts', 'audio_saver_enqueue_scripts');

// Register custom post type
function create_custom_post_type_audios()
{
    $labels = array(
        'name' => 'Audios',
        'singular_name' => 'Audio',
        'menu_name' => 'Audios',
        'add_new' => 'Add New',
        'add_new_item' => 'Add New Audio',
        'edit' => 'Edit',
        'edit_item' => 'Edit Audio',
        'new_item' => 'New Audio',
        'view' => 'View',
        'view_item' => 'View Audio',
        'search_items' => 'Search Audios',
        'not_found' => 'No audios found',
        'not_found_in_trash' => 'No audios found in trash',
        'parent' => 'Parent Audio',
    );

    $args = array(
        'labels' => $labels,
        'public' => true,
        'has_archive' => true,
        'rewrite' => array('slug' => 'audios'),
        'supports' => array('title'),
    );

    register_post_type('audio', $args);
}
add_action('init', 'create_custom_post_type_audios');

// Add custom meta box for audio link
function audio_link_meta_box()
{
    add_meta_box(
        'audio_link_meta_box',
        'Audio Link',
        'render_audio_link_meta_box',
        'audio',
        'normal',
        'default'
    );
}
add_action('add_meta_boxes', 'audio_link_meta_box');

// Render the audio link meta box
function render_audio_link_meta_box($post)
{
    // Retrieve the current value of the audio link meta field
    $audio_link = get_post_meta($post->ID, 'audio_link', true);

    // Output the HTML for the meta box
    ?>
    <p>
        <label for="audio_link_field">Audio Link:</label>
        <input type="text" id="audio_link_field" name="audio_link_field" value="<?php echo esc_attr($audio_link); ?>" style="width: 100%;">
    </p>
    <?php
}

// Save the audio link meta field
function save_audio_link_meta_field($post_id)
{
    if (isset($_POST['audio_link_field'])) {
        $audio_link = sanitize_text_field($_POST['audio_link_field']);
        update_post_meta($post_id, 'audio_link', $audio_link);
    }
}
add_action('save_post_audio', 'save_audio_link_meta_field');

// Add custom column to All Audios page
function add_custom_column_to_audios($columns)
{
    $columns['audio_link'] = 'Audio Link';
    return $columns;
}
add_filter('manage_audio_posts_columns', 'add_custom_column_to_audios');

// Display custom column data on All Audios page
function display_custom_column_data($column, $post_id)
{
    if ($column === 'audio_link') {
        $audio_link = get_post_meta($post_id, 'audio_link', true);
        echo $audio_link;
    }
}
add_action('manage_audio_posts_custom_column', 'display_custom_column_data', 10, 2);

// Shortcode callback function
function audio_saver_shortcode_callback($atts)
{

    // Shortcode content
    $content = '
            <style>
                #controls button[disabled], html input[disabled] { opacity: 0.3; }
                #recordingsList li{list-style:none;display: flex;align-items: center;justify-content: center;gap: 20px;}
                .server-recordings{background-color: #b3d2f1;padding: 20px;border-radius: 7px;}
                .za-sec-header{font-size:30px;margin-bottom:20px;text-align:center}
                .server-recording h2{font-size:22px;}
                .server-recording{display: flex;gap: 20px;justify-content: center;align-items: center;border-bottom: 1px solid #939393;padding: 20px;}
            </style>
            <div style="text-align:center;">
                <div id="controls">
                    <button id="recordButton" class="button">Record</button>
                    <button id="pauseButton" class="button" disabled>Pause</button>
                    <button id="stopButton" class="button" disabled>Stop</button>
                </div>
                <div id="formats">Format: start recording to see sample rate</div>
                <h3 style="margin:20px auto;">Your Recordings</h3>
                <ul id="recordingsList"></ul>
  	        </div>';

    $args = array(
        'post_type' => 'audio',
        'posts_per_page' => -1, // Retrieve all audio posts
    );

    $audio_query = new WP_Query($args);

    $content .= '<div class="server-recordings"><h3 class="za-sec-header">Recordings Saved on Sever</h3>';
    if ($audio_query->have_posts()) {
        while ($audio_query->have_posts()) {
            $audio_query->the_post();

            // Get post ID and audio link meta field value
            $audio_post_id = get_the_ID();
            $audio_link = get_post_meta($audio_post_id, 'audio_link', true);

            // Display the audio post details
            $content .= '<div class="server-recording">';
            $content .= '<h2>' . get_the_title() . '</h2>';
            $content .= '<audio controls>';
            $content .= '<source src="' . $audio_link . '" type="audio/mpeg">';
            $content .= 'Your browser does not support the audio element.';
            $content .= '</audio>';
            $content .= '</div>';
        }
        $content .= '</div>';

        // Restore the global post data
        wp_reset_postdata();
    } else {
        echo 'No audio posts found.';
    }

    // Return the shortcode content
    return $content;
}
add_shortcode('audio_saver', 'audio_saver_shortcode_callback');

// Add AJAX endpoint for handling file upload
add_action('wp_ajax_my_audio_upload', 'my_audio_upload');
add_action('wp_ajax_nopriv_my_audio_upload', 'my_audio_upload'); // Allow non-logged in users to access the endpoint

function my_audio_upload()
{
    if (isset($_FILES['audio_data'])) {

        $file = $_FILES['audio_data'];

        // Check for errors in the uploaded file
        if ($file['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error('Error uploading file.');
        }

        $file_name = $file['name'];
        $file_tmp = $file['tmp_name'];

        // Move the uploaded file to the WordPress uploads directory
        $upload_dir = wp_upload_dir();
        $new_file_path = $upload_dir['path'] . '/' . $file_name . ".wav";

        if (!is_dir($upload_dir['path']) || !is_writable($upload_dir['path'])) {
            // Directory doesn't exist or is not writable
            wp_send_json_error('Upload directory is not writable.');
        }

        if (move_uploaded_file($file_tmp, $new_file_path)) {
            // File has been successfully moved
            // Create an attachment in the media library
            $attachment = array(
                'guid' => $upload_dir['url'] . '/' . $file_name,
                'post_mime_type' => $file['type'],
                'post_title' => sanitize_file_name($file_name),
                'post_content' => '',
                'post_status' => 'inherit',
            );

            $attach_id = wp_insert_attachment($attachment, $new_file_path);
            require_once ABSPATH . 'wp-admin/includes/image.php';
            $attach_data = wp_generate_attachment_metadata($attach_id, $new_file_path);
            wp_update_attachment_metadata($attach_id, $attach_data);

            // Create a new audio post
            $audio_post = array(
                'post_title' => 'Audio - ' . wp_generate_password(6, false),
                'post_type' => 'audio',
                'post_status' => 'publish',
            );
            // Insert the audio post
            $audio_post_id = wp_insert_post($audio_post);

            $audio_link = wp_get_attachment_url($attach_id);
            // Update the audio link meta field
            update_post_meta($audio_post_id, 'audio_link', $audio_link);

            $response = array(
                'message' => 'File uploaded successfully.',
                'audio_link' => $audio_link,
            );
            wp_send_json_success($response);

        } else {
            wp_send_json_error('Error moving file to upload directory.');
        }
    } else {
        wp_send_json_error('No file uploaded.');
    }
}
