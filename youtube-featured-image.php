<?php
/*
Plugin Name: YouTube Featured Image
Description: Set the featured image of a post using a YouTube video thumbnail.
Version: 1.1
Author: James Flores
Author URI: https://jamesflores.net
*/

// Enqueue JavaScript for handling the YouTube thumbnail prompt
function yfi_enqueue_scripts($hook) {
    if ($hook === 'post.php' || $hook === 'post-new.php') {
        wp_enqueue_script('yfi-script', plugin_dir_url(__FILE__) . 'script.js', ['jquery'], '1.1', true);
    }
}
add_action('admin_enqueue_scripts', 'yfi_enqueue_scripts');

// Add a checkbox and YouTube URL field to the Featured Image meta box
function yfi_add_youtube_checkbox() {
    global $post;
    $youtube_url = get_post_meta($post->ID, '_yfi_youtube_url', true);
    $checked = !empty($youtube_url) ? 'checked' : '';

    echo '<div id="yfi-meta-box" style="margin-top: 10px; padding: 12px; background: #f6f6f6; border: 1px solid #ddd; border-radius: 4px;">
        <p style="margin: 0 0 8px; font-weight: 600;">Use YouTube Thumbnail as Featured Image</p>
        <label for="yfi-use-youtube" style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
            <input type="checkbox" id="yfi-use-youtube" name="yfi-use-youtube" ' . $checked . ' style="margin: 0;">
            <span style="font-size: 14px;">Enable</span>
        </label>
        <input 
            type="text" 
            id="yfi-youtube-url" 
            name="yfi-youtube-url" 
            placeholder="Enter YouTube URL" 
            value="' . esc_attr($youtube_url) . '" 
            style="width: 100%; padding: 8px; font-size: 14px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;"
        >
    </div>';
}
add_action('post_submitbox_misc_actions', 'yfi_add_youtube_checkbox');

// Save the YouTube thumbnail as the featured image
function yfi_save_youtube_thumbnail($post_id) {
    // Check if we're saving the post via the correct process
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Check user permissions
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Handle the "Enable" checkbox and YouTube URL
    if (isset($_POST['yfi-use-youtube']) && !empty($_POST['yfi-youtube-url'])) {
        $youtube_url = sanitize_text_field($_POST['yfi-youtube-url']);
        update_post_meta($post_id, '_yfi_youtube_url', $youtube_url);

        // Extract YouTube video ID
        preg_match('/(?:https?:\/\/)?(?:www\.)?youtube\.com\/watch\?v=([a-zA-Z0-9_-]+)/', $youtube_url, $matches);
        if (!isset($matches[1])) {
            preg_match('/(?:https?:\/\/)?(?:www\.)?youtu\.be\/([a-zA-Z0-9_-]+)/', $youtube_url, $matches);
        }

        if (!isset($matches[1])) {
            return; // Invalid YouTube URL
        }

        $video_id = $matches[1];
        $thumbnail_url = "https://img.youtube.com/vi/$video_id/maxresdefault.jpg";

        // Download and process the thumbnail
        $upload_dir = wp_upload_dir();
        $image_data = file_get_contents($thumbnail_url);
        if ($image_data === false) {
            return; // Failed to download thumbnail
        }

        $filename = $video_id . '.jpg';
        $file_path = $upload_dir['path'] . '/' . $filename;
        file_put_contents($file_path, $image_data);

        // Insert the image into the media library
        $file_type = wp_check_filetype($filename, null);
        $attachment = [
            'post_mime_type' => $file_type['type'],
            'post_title'     => sanitize_file_name($filename),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ];

        $attach_id = wp_insert_attachment($attachment, $file_path, $post_id);

        // Generate image metadata
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attach_data = wp_generate_attachment_metadata($attach_id, $file_path);
        wp_update_attachment_metadata($attach_id, $attach_data);

        // Set as the featured image
        set_post_thumbnail($post_id, $attach_id);
    } else {
        // If the checkbox is not checked, remove the metadata and featured image
        delete_post_meta($post_id, '_yfi_youtube_url');
        delete_post_thumbnail($post_id);
    }
}
add_action('save_post', 'yfi_save_youtube_thumbnail');
