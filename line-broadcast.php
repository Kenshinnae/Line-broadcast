<?php
/*
Plugin Name: LINE Broadcast
Description: Broadcast new posts to LINE with audience recipients.
Version: 1.0
Author: YoH
*/

function line_broadcast_section_callback() {
    echo '1. ทำการออก Messaging API ในไลน์ และนำมากรอกในช่อง Access Token <br>';
    echo '2. ทำการสร้างกลุ่ม Audience ที่ต้องการ และนำรหัสมาใส่ในหมวดหมู่ที่ต้องการ';
}
// Callback to print an input field for Audience Group ID for a specific category
function line_broadcast_category_audience_id_field_callback($args) {
    $category_id = $args['category_id'];
    $audience_id = get_option('line_broadcast_audience_id_' . $category_id);
    echo '<input type="text" id="line_broadcast_audience_id_' . $category_id . '" name="line_broadcast_audience_id_' . $category_id . '" value="' . esc_attr($audience_id) . '" />';
    echo '<span style="font-size:12px;margin-left:10px">กลุ่มลูกค้าเจาะจงตามหมวดหมู่</span>';
}
function line_broadcast_access_token_field_callback() {
    echo '<input type="text" id="line_broadcast_access_token" name="line_broadcast_access_token" value="' . esc_attr(get_option('line_broadcast_access_token')) . '" />';
    echo '<span style="font-size:12px;margin-left:10px">Access token จากการออก Messaging API</span>';
}
function line_broadcast_audience_group_id_field_callback() {
    echo '<input type="text" id="line_broadcast_audience_group_id" name="line_broadcast_audience_group_id" value="' . esc_attr(get_option('line_broadcast_audience_group_id')) . '" />';
    echo '<span style="font-size:12px;margin-left:10px">กลุ่มลูกค้าทั่วไปไม่เจาะจงตามหมวดหมู่</span>';
}

// Function to add custom section and fields to the LINE Broadcast settings page
function add_line_broadcast_settings() {
    // Register settings to save the data
    register_setting('line_broadcast_settings_group', 'line_broadcast_access_token');
    register_setting('line_broadcast_settings_group', 'line_broadcast_audience_group_id');

    // Add the section to the LINE Broadcast settings page in admin panel
    add_settings_section(
        'line_broadcast_settings_section',
        'LINE Broadcast Settings',
        'line_broadcast_section_callback',
        'line_broadcast_settings'
    );

    // Add fields to the section
    add_settings_field(
        'line_broadcast_access_token_field',
        'LINE Bot Access Token',
        'line_broadcast_access_token_field_callback',
        'line_broadcast_settings',
        'line_broadcast_settings_section'
    );
    add_settings_field(
        'line_broadcast_audience_group_id_field',
        'Audience Group ID',
        'line_broadcast_audience_group_id_field_callback',
        'line_broadcast_settings',
        'line_broadcast_settings_section'
    );
}
add_action('admin_init', 'add_line_broadcast_settings');
function add_line_broadcast_menu_page() {
    add_menu_page(
        'Line Broadcast Settings',   // Page title
        'Line Broadcast',            // Menu title
        'manage_options',            // Capability required to access menu
        'line_broadcast_settings',   // Menu slug
        'line_broadcast_settings_page_callback', // Callback function to display page content
        'dashicons-email-alt',       // Icon
        30                           // Position
    );
}
add_action('admin_menu', 'add_line_broadcast_menu_page');
// Function to add settings fields for categories and their associated audience IDs
function add_line_broadcast_category_settings() {
    // Get all categories
    $categories = get_categories();
    foreach ($categories as $category) {
        // Add a settings field for each category
        add_settings_field(
            'line_broadcast_audience_id_' . $category->term_id,
            'Audience ID for ' . $category->name,
            'line_broadcast_category_audience_id_field_callback',
            'line_broadcast_settings',
            'line_broadcast_settings_section',
            array(
                'category_id' => $category->term_id
            )
        );
    }
}
add_action('admin_init', 'add_line_broadcast_category_settings');

// Function to save audience IDs for each category
function save_line_broadcast_category_audience_ids() {
    // Get all categories
    $categories = get_categories();
    foreach ($categories as $category) {
        // Save the audience ID for each category
        if (isset($_POST['line_broadcast_audience_id_' . $category->term_id])) {
            update_option('line_broadcast_audience_id_' . $category->term_id, sanitize_text_field($_POST['line_broadcast_audience_id_' . $category->term_id]));
        }
    }
}
add_action('admin_init', 'save_line_broadcast_category_audience_ids');
// Callback function to display the LINE Broadcast settings page
function line_broadcast_settings_page_callback() {
    ?>
    <div class="wrap">
        <h1>Line Broadcast Settings</h1>
        <form method="post" action="options.php">
            <?php settings_fields('line_broadcast_settings_group'); ?>
            <?php do_settings_sections('line_broadcast_settings'); ?>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}


// Hook into post transition to broadcast new posts
add_action('transition_post_status', 'broadcast_new_post_to_line', 10, 3);

function broadcast_new_post_to_line($new_status, $old_status, $post) {
    // Check if the new status is 'publish' and the old status is not 'publish'
 
        // Get LINE Bot Access Token and Audience Group ID from settings
        $access_token = get_option('line_broadcast_access_token');

        // Check if both access token and audience group ID are available
        if (!empty($access_token)) {
                // Get categories associated with the post
                $categories = get_the_category($post->ID);
    
                // Initialize an array to store audience IDs
                $audience_ids = array();
    
                // Loop through categories and retrieve audience IDs
                foreach ($categories as $category) {
                    // Retrieve audience ID for the category from plugin settings
                    $audience_id = get_option('line_broadcast_audience_id_' . $category->term_id);
                    
                    // Add audience ID to the array if it's not empty
                    if (!empty($audience_id)) {
                        $audience_ids[] = $audience_id;
                    }
                }
            // Get the Yoast SEO meta description if available, otherwise use post excerpt
            $excerpt = get_post_meta($post->ID, '_yoast_wpseo_metadesc', true);
            if (empty($excerpt)) {
                $excerpt = get_the_excerpt($post);
            }

            // Initialize default values for title and permalink
            $title = isset($post->post_title) ? $post->post_title : 'Untitled';
            $permalink = isset($post->ID) ? get_permalink($post->ID) : '#';
          

            // Prepare data for Flex message
            $flex_message = array(
                "type" => "bubble",
                "hero" => array(
                    "type" => "image",
                    "url" => get_the_post_thumbnail_url($post->ID, 'full'),
                    "size" => "full",
                    "aspectRatio" => "20:10",
                    "aspectMode" => "cover"
                ),
                "body" => array(
                    "type" => "box",
                    "layout" => "vertical",
                    "contents" => array(
                        array(
                            "type" => "text",
                            "text" => $post->post_title,
                            "weight" => "bold",
                            "margin" => "sm",
                            "size" => "md"
                        ),
                        array(
                            "type" => "text",
                            "text" => $excerpt
                        )
                    )
                ),
                "footer" => array(
                    "type" => "box",
                    "layout" => "vertical",
                    "contents" => array(
                        array(
                            "type" => "button",
                            "style" => "primary",
                            "margin" => "sm",
                            "height" => "sm",
                            "action" => array(
                                "type" => "uri",
                                "label" => "อ่านเพิ่มเติม",
                                "uri" => get_permalink($post->ID) 
                            ),
                            "color" => "#041936"
                        )
                    )
                )
            );

            // Convert Flex message array to JSON
            $json_message = json_encode($flex_message);

            // LINE API endpoint
            $line_api_endpoint = 'https://api.line.me/v2/bot/message/narrowcast';

            // Data for broadcasting with audience recipient
            $broadcast_data = array(
                'messages' => array(
                    array(
                        'type' => 'flex',
                        'altText' => $title,
                        'contents' => $flex_message
                    )
                ),
                'recipient' => array(
                    'type' => 'operator',
                    'and' => array()
                )
            );
            foreach ($audience_ids as $audience_id) {
                $broadcast_data['recipient']['and'][] = array(
                    'type' => 'audience',
                    'audienceGroupId' => $audience_id
                );
            }
            // Send the JSON message to LINE API endpoint for broadcasting
            $response = wp_remote_post($line_api_endpoint, array(
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $access_token,
                ),
                'body' => json_encode($broadcast_data),
            ));

            // Check for errors
            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();
                error_log("Failed to broadcast to LINE: $error_message");
            } else {
                // Broadcast successful
            }
        } else {
            // Log error if access token or audience group ID is empty
            error_log("Failed to broadcast to LINE: Access token or audience group ID is empty.");
        }
    
}
