<?php
/*
Plugin Name: Post Cloner
Description: Allows administrators to clone posts with a single click.
Version: 1.0
Author: Mahendra Ribadiya
*/

// Hook into the admin interface
add_action('admin_footer', 'post_cloner_button_on_single_post');
add_action('manage_posts_columns', 'post_cloner_add_column');
add_action('manage_posts_custom_column', 'post_cloner_display_clone_button', 10, 2);

// Add a button on the single post editing screen
function post_cloner_button_on_single_post() {
    if (is_singular('post') && current_user_can('edit_posts')) {
        global $post;
        $url = admin_url('admin-post.php?action=clone_post&id=' . $post->ID);
        echo '<a href="' . esc_url($url) . '" class="button button-primary">Clone this Post</a>';
    }
}

// Add the "Clone" button column in the post list
function post_cloner_add_column($columns) {
    $columns['clone'] = 'Clone';
    return $columns;
}

// Display the clone button in the post list
function post_cloner_display_clone_button($column_name, $post_id) {
    if ($column_name == 'clone') {
        $url = admin_url('admin-post.php?action=clone_post&id=' . $post_id);
        echo '<a href="' . esc_url($url) . '" class="button button-primary">Clone</a>';
    }
}

// Handle the cloning action
add_action('admin_post_clone_post', 'post_cloner_clone_post');

function post_cloner_clone_post() {
    if (!isset($_GET['id']) || !current_user_can('edit_posts')) {
        wp_die('You do not have permission to clone this post.');
    }

    $original_post_id = (int) $_GET['id'];
    $original_post = get_post($original_post_id);

    if (!$original_post) {
        wp_die('Post not found.');
    }

    // Clone the post
    $post_data = array(
        'post_title'   => $original_post->post_title,
        'post_content' => $original_post->post_content,
        'post_status'  => 'draft',
        'post_author'  => $original_post->post_author,
        'post_type'    => $original_post->post_type,
        'post_category'=> $original_post->post_category,
    );

    $cloned_post_id = wp_insert_post($post_data);

    // Clone taxonomies
    $taxonomies = get_object_taxonomies($original_post);
    foreach ($taxonomies as $taxonomy) {
        $terms = wp_get_object_terms($original_post_id, $taxonomy);
        wp_set_object_terms($cloned_post_id, $terms, $taxonomy);
    }

    // Clone custom fields (meta data)
    $post_meta = get_post_meta($original_post_id);
    foreach ($post_meta as $meta_key => $meta_value) {
        update_post_meta($cloned_post_id, $meta_key, $meta_value[0]);
    }

    // Clone attachments (media)
    $attachments = get_attached_media('', $original_post_id);
    foreach ($attachments as $attachment) {
        $new_attachment = array(
            'post_title' => $attachment->post_title,
            'post_content' => $attachment->post_content,
            'post_status' => 'inherit',
            'post_parent' => $cloned_post_id,
        );
        wp_insert_attachment($new_attachment, get_attached_file($attachment->ID), $cloned_post_id);
    }

    // Redirect to the newly created post
    wp_redirect(get_edit_post_link($cloned_post_id));
    exit;
}

