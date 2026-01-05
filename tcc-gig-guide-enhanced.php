<?php
/**
 * Plugin Name:       TCC - Gig Guide Enhanced
 * Description:       Renders a grid of gig cards with flexible date entry - choose between RRULE recurring patterns or manual multi-date selection. Includes venue filters, list/grid views, and card flip functionality.
 * Version:           2.0.0
 * Author:            Mojo Collective
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       tcc-gig-guide-enhanced
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
if (!defined('TCC_GG_ENHANCED_PATH')) {
    define('TCC_GG_ENHANCED_PATH', plugin_dir_path(__FILE__));
}
if (!defined('TCC_GG_ENHANCED_URL')) {
    define('TCC_GG_ENHANCED_URL', plugin_dir_url(__FILE__));
}
if (!defined('TCC_GG_ENHANCED_VERSION')) {
    define('TCC_GG_ENHANCED_VERSION', '2.0.0');
}

// Includes
require_once TCC_GG_ENHANCED_PATH . 'includes/class-rrule.php';
require_once TCC_GG_ENHANCED_PATH . 'includes/class-multidate-handler.php';
require_once TCC_GG_ENHANCED_PATH . 'includes/class-renderer.php';
require_once TCC_GG_ENHANCED_PATH . 'includes/class-whats-on-renderer.php';

// Include ACF field type
add_action('acf/include_field_types', 'tcc_gg_enhanced_include_field_types');
function tcc_gg_enhanced_include_field_types() {
    if (!class_exists('ACF')) {
        return;
    }
    require_once TCC_GG_ENHANCED_PATH . 'includes/acf-fields/class-acf-field-multidate-picker.php';
}

// Check if ACF is active
add_action('admin_init', 'tcc_gg_enhanced_check_acf');
function tcc_gg_enhanced_check_acf() {
    if (!class_exists('ACF')) {
        add_action('admin_notices', 'tcc_gg_enhanced_acf_missing_notice');
    }
}

function tcc_gg_enhanced_acf_missing_notice() {
    ?>
    <div class="notice notice-warning">
        <p><?php _e('TCC Gig Guide Enhanced requires Advanced Custom Fields to be installed and active.', 'tcc-gig-guide-enhanced'); ?></p>
    </div>
    <?php
}

// Shortcode registration
add_shortcode('tcc_gig_guide', function ($atts = []) {
    $renderer = new TCC_Gig_Guide_Enhanced_Renderer();
    return $renderer->render($atts);
});

// What's On shortcode registration
add_shortcode('tcc_whats_on', function ($atts = []) {
    $renderer = new TCC_Whats_On_Enhanced_Renderer();
    return $renderer->render($atts);
});

// Debug shortcode - temporary for troubleshooting
add_shortcode('tcc_debug_enhanced', function() {
    $args = [
        'post_type' => 'gig',
        'post_status' => 'publish',
        'posts_per_page' => 3,
    ];
    
    $gigs = new WP_Query($args);
    
    if (!$gigs->have_posts()) {
        return '<p>No gigs found.</p>';
    }
    
    $output = '<div style="background: #f8f9fa; padding: 20px; margin: 20px 0; border-radius: 5px; font-family: monospace; font-size: 12px;">';
    $output .= '<h3>TCC Gig Guide Enhanced Debug (First 3 Posts)</h3>';
    
    while ($gigs->have_posts()) {
        $gigs->the_post();
        $post_id = get_the_ID();
        
        $output .= '<hr><strong>Post ID: ' . $post_id . ' - ' . get_the_title() . '</strong><br>';
        
        if (!function_exists('get_field')) {
            $output .= 'ERROR: ACF get_field() function not available!<br>';
            continue;
        }
        
        $alternate_title = get_field('alternate_title', $post_id);
        $date_mode = get_field('date_entry_mode', $post_id);
        $rrule_dates = get_field('dates_rrule', $post_id);
        $manual_dates = get_field('dates_manual', $post_id);
        
        $output .= 'alternate_title: ' . ($alternate_title ? $alternate_title : 'EMPTY') . '<br>';
        $output .= 'date_entry_mode: ' . ($date_mode ? $date_mode : 'NOT SET') . '<br>';
        $output .= 'dates_rrule type: ' . gettype($rrule_dates) . '<br>';
        $output .= 'dates_manual type: ' . gettype($manual_dates) . '<br>';
        
        if (is_array($manual_dates) && !empty($manual_dates)) {
            $output .= 'manual dates count: ' . count($manual_dates) . '<br>';
            $output .= 'manual dates: ' . implode(', ', array_slice($manual_dates, 0, 5)) . '<br>';
        }
    }
    
    wp_reset_postdata();
    
    $output .= '</div>';
    return $output;
});

// Enqueue assets only when shortcode is present
add_action('wp_enqueue_scripts', function () {
    if (!is_singular()) {
        return;
    }
    global $post;
    if (!$post) {
        return;
    }
    if (has_shortcode($post->post_content, 'tcc_gig_guide') || has_shortcode($post->post_content, 'tcc_whats_on')) {
        // Enqueue GSAP from CDN
        wp_register_script('gsap', 'https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js', [], '3.12.2', true);
        wp_register_script('gsap-scrolltrigger', 'https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/ScrollTrigger.min.js', ['gsap'], '3.12.2', true);
        
        // Register and enqueue plugin assets
        wp_register_style('tcc-gig-guide-enhanced', TCC_GG_ENHANCED_URL . 'assets/css/style.css', [], TCC_GG_ENHANCED_VERSION);
        wp_register_script('tcc-gig-guide-enhanced', TCC_GG_ENHANCED_URL . 'assets/js/script.js', ['gsap', 'gsap-scrolltrigger'], TCC_GG_ENHANCED_VERSION, true);
        
        wp_enqueue_style('tcc-gig-guide-enhanced');
        wp_enqueue_script('gsap');
        wp_enqueue_script('gsap-scrolltrigger');
        wp_enqueue_script('tcc-gig-guide-enhanced');
    }
});

// Enqueue ACF field assets in admin
add_action('acf/input/admin_enqueue_scripts', function() {
    $dir = TCC_GG_ENHANCED_URL;
    $version = TCC_GG_ENHANCED_VERSION;
    
    // Flatpickr CSS
    wp_enqueue_style(
        'flatpickr',
        $dir . 'assets/flatpickr/flatpickr.min.css',
        [],
        '4.6.13'
    );
    
    // Flatpickr JS
    wp_enqueue_script(
        'flatpickr',
        $dir . 'assets/flatpickr/flatpickr.min.js',
        [],
        '4.6.13',
        true
    );
    
    // Multi-date picker CSS
    wp_enqueue_style(
        'tcc-multidate-picker',
        $dir . 'assets/css/multidate-picker.css',
        ['flatpickr'],
        $version
    );
    
    // Multi-date picker JS
    wp_enqueue_script(
        'tcc-multidate-picker',
        $dir . 'assets/js/multidate-picker.js',
        ['jquery', 'flatpickr', 'acf-input'],
        $version,
        true
    );
});
