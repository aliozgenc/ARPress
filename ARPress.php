<?php
/*
Plugin Name: ARPress WooCommerce 3D Plugin
Description: Add and display .glb files to WooCommerce products.
Version: 1.0
Author: Ali Ozgenc
*/

if (!defined('ABSPATH')) {
    exit;
}

// Extend file types
add_filter('wp_check_filetype_and_ext', 'arpress_file_and_ext_glb', 10, 4);
function arpress_file_and_ext_glb($types, $file, $filename, $mimes)
{
    if (false !== strpos($filename, '.glb')) {
        $types['ext']  = 'glb';
        $types['type'] = 'model/gltf-binary';
    }

    return $types;
}

function arpress_mime_types($mimes)
{
    $mimes['glb']  = 'model/gltf-binary';
    return $mimes;
}

add_filter('upload_mimes', 'arpress_mime_types');

// Add 3D file upload field to product page
add_action('woocommerce_product_options_general_product_data', 'arpress_product_3d_file_field');

// Save the value of the 3D file field on the product page
add_action('woocommerce_admin_process_product_object', 'arpress_save_product_3d_file');

// Show 3D model on the product page
add_action('woocommerce_before_single_product', 'arpress_show_3d_model');

// JavaScript that opens the media library
add_action('admin_footer', 'arpress_admin_scripts');

// Enqueue Three.js and Model Viewer
add_action('wp_enqueue_scripts', 'arpress_enqueue_scripts');

// Add type="module" to script tags
add_filter('script_loader_tag', 'arpress_add_attributes_to_script', 10, 3);

function arpress_add_attributes_to_script($tag, $handle, $src)
{
    if ('three-js' === $handle || 'model-viewer' === $handle) {
        $tag = '<script type="module" src="' . esc_url($src) . '"></script>';
    }

    return $tag;
}

// Function to show 3D model
function arpress_show_3d_model()
{
    global $product;

    // Get the path of the .glb file for the product
    $glb_file = get_post_meta($product->get_id(), '_arpress_3d_file', true);

    // If there is a .glb file, show the 3D model
    if ($glb_file) {
        echo '<div id="arpress_3d_model_container" style="width: 100%; height: 400px;">';
        echo '<h2>Product 3D Model</h2>';
        echo '<model-viewer src="' . esc_url($glb_file) . '" alt="3D Model"></model-viewer>';
        echo '</div>';
    }
}

// Function to add 3D file upload field to product page
function arpress_product_3d_file_field()
{
    global $post;

    // Add a file upload field visible only to administrators
    if (current_user_can('administrator')) {
        echo '<div class="options_group">';
        $file_url = get_post_meta($post->ID, '_arpress_3d_file', true);

        woocommerce_wp_text_input(
            array(
                'id'          => '_arpress_3d_file',
                'label'       => __('3D File', 'arpress-woocommerce-3d-plugin'),
                'description' => __('Path to the 3D file for your product.', 'arpress-woocommerce-3d-plugin'),
                'value'       => $file_url,
                'custom_attributes' => array(
                    'readonly' => 'readonly', // Read-only
                ),
            )
        );

        echo '<button class="button" id="upload_3d_file_button">Select File</button>';
        echo '</div>';
    }
}

// Function to save the value of the 3D file field on the product page
function arpress_save_product_3d_file($product)
{
    if (isset($_POST['_arpress_3d_file']) && current_user_can('administrator')) {
        $product->update_meta_data('_arpress_3d_file', esc_attr($_POST['_arpress_3d_file']));
    }
}

// Function to include JavaScript for the media library
function arpress_admin_scripts()
{
    if (current_user_can('administrator')) {
?>
        <script type="module">
            console.log('JavaScript file loaded.');

            var file_frame;
            var wp_media_post_id = wp.media.model.settings.post.id; // WP 3.5+

            function show3DModel(glbFile) {
                jQuery('#arpress_3d_model_container').html('<h2>Product 3D Model</h2><model-viewer src="' + glbFile + '" alt="3D Model"></model-viewer>');
            }

            // Show a text for debugging purposes
            jQuery('#arpress_3d_model_container').text('If you see this text, the JavaScript file is working.');

            // Make sure jQuery is loaded
            if (typeof jQuery == 'undefined') {
                var script = document.createElement('script');
                script.src = 'https://code.jquery.com/jquery-3.6.4.min.js';
                script.type = 'text/javascript';
                script.onload = function() {
                    jQuery(document).ready(function($) {
                        // Show another text for debugging purposes
                        jQuery('#arpress_3d_model_container').text(jQuery('#arpress_3d_model_container').text() + ' This text is also added with jQuery.');

                        jQuery('#upload_3d_file_button').on('click', function(event) {
                            event.preventDefault();

                            // If the media frame exists, reopen it
                            if (file_frame) {
                                file_frame.uploader.uploader.param('post_id', wp_media_post_id);
                                file_frame.open();
                                return;
                            } else {
                                wp.media.model.settings.post.id = wp_media_post_id;
                            }

                            // Create the media frame
                            file_frame = wp.media.frames.file_frame = wp.media({
                                title: 'Select 3D File',
                                button: {
                                    text: 'Add 3D File'
                                },
                                multiple: false,
                                library: {
                                    type: ['model/gltf+json', 'model/gltf-binary']
                                }
                            });

                            // When a file is selected
                            file_frame.on('select', function() {
                                attachment = file_frame.state().get('selection').first().toJSON();
                                jQuery('#_arpress_3d_file').val(attachment.url);

                                // Show the 3D model on the product page
                                show3DModel(attachment.url);

                                wp.media.model.settings.post.id = wp_media_post_id;
                            });

                            // Open the media frame
                            file_frame.open();
                        });
                    });
                };
                document.head.appendChild(script);
            } else {
                jQuery(document).ready(function($) {
                    // Show another text for debugging purposes
                    jQuery('#arpress_3d_model_container').text(jQuery('#arpress_3d_model_container').text() + ' This text is also added with jQuery.');

                    jQuery('#upload_3d_file_button').on('click', function(event) {
                        event.preventDefault();

                        // If the media frame exists, reopen it
                        if (file_frame) {
                            file_frame.uploader.uploader.param('post_id', wp_media_post_id);
                            file_frame.open();
                            return;
                        } else {
                            wp.media.model.settings.post.id = wp_media_post_id;
                        }

                        // Create the media frame
                        file_frame = wp.media.frames.file_frame = wp.media({
                            title: 'Select 3D File',
                            button: {
                                text: 'Add 3D File'
                            },
                            multiple: false,
                            library: {
                                type: ['model/gltf+json', 'model/gltf-binary']
                            }
                        });

                        // When a file is selected
                        file_frame.on('select', function() {
                            attachment = file_frame.state().get('selection').first().toJSON();
                            jQuery('#_arpress_3d_file').val(attachment.url);

                            // Show the 3D model on the product page
                            show3DModel(attachment.url);

                            wp.media.model.settings.post.id = wp_media_post_id;
                        });

                        // Open the media frame
                        file_frame.open();
                    });
                });
            }
        </script>
<?php
    }
}

// Function to enqueue scripts
function arpress_enqueue_scripts()
{
    // Model Viewer (including Three.js)
    wp_enqueue_script('model-viewer', 'https://ajax.googleapis.com/ajax/libs/model-viewer/3.3.0/model-viewer.min.js', array(), '3.3.0', true);
}

add_action('wp_enqueue_scripts', 'arpress_enqueue_scripts');
?>