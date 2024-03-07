<?php
/*
Plugin Name: LINE Broadcast
Description: Broadcast new posts to LINE with audience recipients.
Version: 1.1
Author: YoH
*/

if (!defined('ABSPATH')) {
    exit;
}
function line_broadcast_admin_scripts() {

    wp_enqueue_script( 'line-broadcast-admin-js', plugins_url( '/js/line-broadcast-admin.js', __FILE__ ));
}
add_action('admin_enqueue_scripts', 'line_broadcast_admin_scripts');


function line_broadcast_add_meta_box() {
    add_meta_box(
        'line_broadcast_meta',
        __('LINE Broadcast Options', 'line-broadcast'),
        'line_broadcast_meta_box_callback',
        'post',
        'side',
        'high'
    );
}

add_action('add_meta_boxes', 'line_broadcast_add_meta_box');

function line_broadcast_meta_box_callback($post) {

    wp_nonce_field('line_broadcast_nonce', 'line_broadcast_nonce_field');
    $line_broadcast_enabled = get_post_meta($post->ID, '_line_broadcast_enabled', true);
    $selected_audience = get_post_meta($post->ID, '_line_broadcast_audience', true);
    $subscription_period = get_post_meta($post->ID, '_line_broadcast_subscription_period', true);

    $access_token = get_option('line_broadcast_access_token');
    if (empty($access_token)) {
        echo '<p>ยังไม่ได้ระบุ Token. <a href="options-general.php?page=line_broadcast_settings">คลิกเพื่อระบุ</a></p>';
    }
    else{
    echo '<p>';
    echo '<input type="checkbox" id="line_broadcast_enabled" name="line_broadcast_enabled" value="1" ' . checked(1, $line_broadcast_enabled, false) . '/>';
    echo '<label for="line_broadcast_enabled">' . __('Enable LINE Broadcast', 'line-broadcast') . '</label>';
    echo '</p>';

    $audience_groups = fetch_line_audience_groups();
    
    echo '<p>';
    echo '<label for="line_broadcast_audience">' . __('Audience Group:', 'line-broadcast') . '</label>';
    echo '<select id="line_broadcast_audience" name="line_broadcast_audience">';
    echo '<option value="">' . __('Select Audience Group', 'line-broadcast') . '</option>';
    foreach ($audience_groups as $audience_group) {
        echo '<option value="' . esc_attr($audience_group['audienceGroupId']) . '" ' . selected($selected_audience, $audience_group['audienceGroupId'], false) . '>' . esc_html($audience_group['description']) . '</option>';
    }
    echo '</select>';
    echo '</p>';

    echo '<p>';
    echo '<label for="line_broadcast_subscription_period">' . __('Subscription Period:', 'line-broadcast') . '</label>';
    echo '<select id="line_broadcast_subscription_period" name="line_broadcast_subscription_period">';
    echo '<option value="">' . __('Select Period', 'line-broadcast') . '</option>';
    echo '<option value="day_0_to_day_7"' . selected($subscription_period, 'day_0_to_day_7', false) . '>Last 7 days</option>';
    echo '<option value="day_7_to_day_30"' . selected($subscription_period, 'day_7_to_day_30', false) . '>Last 30 days</option>';
    echo '<option value="day_30_to_day_90"' . selected($subscription_period, 'day_30_to_day_90', false) . '>Last 90 days</option>';
    echo '<option value="day_90_to_day_180"' . selected($subscription_period, 'day_90_to_day_180', false) . '>Last 180 days</option>';
    echo '<option value="day_180_to_day_365"' . selected($subscription_period, 'day_180_to_day_365', false) . '>Last 365 days</option>';
    echo '</select>';
    echo '</p>';
    }

    
}
function line_broadcast_save_postdata($post_id) {

    if (!isset($_POST['line_broadcast_nonce_field']) || !wp_verify_nonce($_POST['line_broadcast_nonce_field'], 'line_broadcast_nonce')) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    if (isset($_POST['line_broadcast_enabled'])) {
        update_post_meta($post_id, '_line_broadcast_enabled', $_POST['line_broadcast_enabled']);
    } else {
        delete_post_meta($post_id, '_line_broadcast_enabled');
    }

    if (isset($_POST['line_broadcast_audience'])) {
        update_post_meta($post_id, '_line_broadcast_audience', $_POST['line_broadcast_audience']);
    } else {
        delete_post_meta($post_id, '_line_broadcast_audience');
    }

    if (isset($_POST['line_broadcast_subscription_period'])) {
        update_post_meta($post_id, '_line_broadcast_subscription_period', $_POST['line_broadcast_subscription_period']);
    } else {
        delete_post_meta($post_id, '_line_broadcast_subscription_period');
    }
}

add_action('save_post', 'line_broadcast_save_postdata');

function line_broadcast_section_callback() {
    echo '1. ทำการออก Messaging API ในไลน์ และนำมากรอกในช่อง Access Token <br>';
    echo '2. ทำการสร้างกลุ่ม Audience ที่ต้องการ และนำรหัสมาใส่ในหมวดหมู่ที่ต้องการ';
}


function line_broadcast_access_token_field_callback() {
    echo '<input type="text" id="line_broadcast_access_token" name="line_broadcast_access_token" value="' . esc_attr(get_option('line_broadcast_access_token')) . '" />';
    echo '<span style="font-size:12px;margin-left:10px">Access token จากการออก Messaging API</span>';
}



function fetch_line_audience_groups() {
    $access_token = get_option('line_broadcast_access_token');
    $line_api_endpoint = 'https://api.line.me/v2/bot/audienceGroup/list';

    $response = wp_remote_get($line_api_endpoint, array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $access_token,
        ),
    ));

    if (is_wp_error($response)) {
        return array();
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (isset($data['audienceGroups'])) {
        return $data['audienceGroups'];
    } else {
        return array(); 
    }
}

function add_line_broadcast_settings() {

    register_setting('line_broadcast_settings_group', 'line_broadcast_access_token');

    add_settings_section(
        'line_broadcast_settings_section',
        'LINE Broadcast Settings',
        'line_broadcast_section_callback',
        'line_broadcast_settings'
    );

    add_settings_field(
        'line_broadcast_access_token_field',
        'LINE Bot Access Token',
        'line_broadcast_access_token_field_callback',
        'line_broadcast_settings',
        'line_broadcast_settings_section'
    );
   
}

add_action('admin_init', 'add_line_broadcast_settings');
function add_line_broadcast_menu_page() {
    add_menu_page(
        'Line Broadcast Settings',   
        'Line Broadcast',           
        'manage_options',
        'line_broadcast_settings',
        'line_broadcast_settings_page_callback',
        'dashicons-email-alt',
        30 
    );
}

add_action('admin_menu', 'add_line_broadcast_menu_page');

function line_broadcast_settings_page_callback() {
    ?>
    <div class="wrap">
        <h1>LINE Broadcast Settings</h1>
        <form method="post" action="options.php">
            <?php settings_fields('line_broadcast_settings_group'); ?>
            <?php do_settings_sections('line_broadcast_settings'); ?>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}


add_action('transition_post_status', 'broadcast_new_post_to_line', 10, 3);

function broadcast_new_post_to_line($new_status, $old_status, $post) {
 
   $line_broadcast_enabled = get_post_meta($post->ID, '_line_broadcast_enabled', true);
   $selected_audience = get_post_meta($post->ID, '_line_broadcast_audience', true);
	$audience_count = get_audience_count($selected_audience);

   if ('1' !== $line_broadcast_enabled || empty($selected_audience)) {
       return;
   }

   $access_token = get_option('line_broadcast_access_token');

   if (empty($access_token)) {
       error_log('LINE Broadcast: Access token is not set.');
       return;
   }
            $title = get_the_title($post->ID);
            $permalink = get_permalink($post->ID);
            $excerpt = get_the_excerpt($post->ID);
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

            $json_message = json_encode($flex_message);
            $line_api_endpoint = 'https://api.line.me/v2/bot/message/narrowcast';
            $subscription_period_meta = get_post_meta($post->ID, '_line_broadcast_subscription_period', true);
            switch ($subscription_period_meta) {
                case 'day_0_to_day_7':
                    $gte = 'day_0';
                    $lt = 'day_7';
                    break;
                case 'day_7_to_day_30':
                    $gte = 'day_7';
                    $lt = 'day_30';
                    break;
                case 'day_30_to_day_90':
                    $gte = 'day_30';
                    $lt = 'day_90';
                    break;
                case 'day_90_to_day_180':
                    $gte = 'day_90';
                    $lt = 'day_180';
                    break;
                case 'day_180_to_day_365':
                    $gte = 'day_180';
                    $lt = 'day_365';
                    break;
                default:
                    $gte = '';
                    $lt = '';
                    break;
            }
            
            $broadcast_data = [
        'messages' => [
            [
                'type' => 'flex',
                'altText' => $title,
                'contents' => $flex_message,
            ],
        ],
        'recipient' => [
            'type' => 'audience',
            'audienceGroupId' => $selected_audience,
        ],
    ];

    if ($audience_count >= 100 && !empty($gte) && !empty($lt)) {
        $broadcast_data['filter'] = [
            'demographic' => [
                'type' => 'operator',
                'and' => [
                    [
                        'type' => 'subscriptionPeriod',
                        'gte' => $gte,
                        'lt' => $lt,
                    ],
                ],
            ],
        ];
    }

            $response = wp_remote_post($line_api_endpoint, array(
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $access_token,
                ),
                'body' => json_encode($broadcast_data),
            ));

            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();
                error_log("Failed to broadcast to LINE: $error_message");
            } else {

            }
            $response_code = wp_remote_retrieve_response_code($response);
                if ($response_code != 200) {
                    error_log("LINE Broadcast failed with response code: $response_code");
                }
        }
function get_audience_count($audienceGroupId) {
	 $selected_audience = get_post_meta($post->ID, '_line_broadcast_audience', true);
    $access_token = get_option('line_broadcast_access_token');
    $line_api_endpoint = "https://api.line.me/v2/bot/audienceGroup/{$selected_audience}";

    $response = wp_remote_get($line_api_endpoint, array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $access_token,
        ),
    ));

    if (is_wp_error($response)) {
        error_log('Error fetching audience count: ' . $response->get_error_message());
        return null;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (isset($data['audienceGroup']['audienceCount'])) {
        return $data['audienceGroup']['audienceCount'];
    } else {
        error_log('audienceCount not found in response.');
        return null;
    }
}

// ส่ง audienceCount กลับไปยัง JavaScript
function line_broadcast_check_audience_group() {
    
    $audienceGroupId = isset($_POST['audienceGroupId']) ? sanitize_text_field($_POST['audienceGroupId']) : '';
    $access_token = get_option('line_broadcast_access_token');
    $response = wp_remote_get("https://api.line.me/v2/bot/audienceGroup/{$audienceGroupId}", array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $access_token,
        ),
    ));

    if (!is_wp_error($response)) {
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        $audienceCount = isset($data['audienceGroup']['audienceCount']) ? $data['audienceGroup']['audienceCount'] : null;
        wp_send_json_success(array('audienceCount' => $audienceCount)); 
    } else {
        wp_send_json_error();
    }
}
add_action('wp_ajax_check_audience_group', 'line_broadcast_check_audience_group');
add_action('wp_ajax_nopriv_check_audience_group', 'line_broadcast_check_audience_group'); 
