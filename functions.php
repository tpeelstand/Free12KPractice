<?php
add_action( 'wp_enqueue_scripts', 'education_business_chld_thm_parent_css' );
function education_business_chld_thm_parent_css() {

    $education_business_theme = wp_get_theme();
    $theme_version = $education_business_theme->get( 'Version' );

    wp_enqueue_style( 
    	'education_business_chld_css', 
    	trailingslashit( get_template_directory_uri() ) . 'style.css', 
    	array( 
    		'bootstrap',
    		'font-awesome-5',
    		'bizberg-main',
    		'bizberg-component',
    		'bizberg-style2',
    		'bizberg-responsive' 
    	),
        $theme_version
    );
    // Enqueue app.css
    wp_enqueue_style(
        'app_css',
        get_stylesheet_directory_uri() . '/css/app.css',
        array(),
        $theme_version
    );
    // Enqueue math-script.js
    if(!is_page('english')) {
    wp_enqueue_script(
        'math_scripts',
        get_stylesheet_directory_uri() . '/js/math-script.js',
        array('jquery'),
        $theme_version,
        true
    );

    wp_localize_script('math_scripts', 'mathSkillAjax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('your_nonce_name')
    ));
}

    // Enqueue english-script.js
    if(!is_page('math')) {
        wp_enqueue_script(
        'english_scripts',
        get_stylesheet_directory_uri() . '/js/english-script.js',
        array('jquery'),
        $theme_version,
        true
    );

    wp_localize_script('english_scripts', 'englishSkillAjax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('your_nonce_name')
    ));
    }
    
}

/**
* Change the theme color
*/
add_filter( 'bizberg_theme_color', 'education_business_change_theme_color' );
add_filter( 'bizberg_header_menu_color_hover_sticky_menu', 'education_business_change_theme_color' );
add_filter( 'bizberg_header_button_color_sticky_menu', 'education_business_change_theme_color' );
add_filter( 'bizberg_header_button_color_hover_sticky_menu', 'education_business_change_theme_color' );
function education_business_change_theme_color(){
    return '#ffb606';
}

/**
* Change the header menu color hover
*/
add_filter( 'bizberg_header_menu_color_hover', 'education_business_header_menu_color_hover' );
function education_business_header_menu_color_hover(){
    return '#ffb606';
}

/**
* Change the button color of header
*/
add_filter( 'bizberg_header_button_color', 'education_business_header_button_color' );
function education_business_header_button_color(){
    return '#ffb606';
}

/**
* Change the button hover color of header
*/
add_filter( 'bizberg_header_button_color_hover', 'education_business_header_button_color_hover' );
function education_business_header_button_color_hover(){
    return '#ffb606';
}

/**
* Changed to slider
*/
add_filter( 'bizberg_slider_banner_settings', 'education_business_slider_banner_settings' );
function education_business_slider_banner_settings(){
    return 'slider';
}

add_filter( 'bizberg_slider_title_box_highlight_color', function(){
    return '#ffb606';
});

add_filter( 'bizberg_slider_arrow_background_color', function(){
    return '#ffb606';
});

add_filter( 'bizberg_slider_dot_active_color', function(){
    return '#ffb606';
});

add_filter( 'bizberg_slider_gradient_primary_color', function(){
    return 'rgba(255,182,6,0.65)';
});

add_filter( 'bizberg_read_more_background_color', function(){
    return '#ffb606';
});

add_filter( 'bizberg_read_more_background_color_2', function(){
    return '#ffb606';
});

add_filter( 'bizberg_link_color', function(){
    return '#64686d';
});

add_filter( 'bizberg_link_color_hover', function(){
    return '#ffb606';
});

add_filter( 'bizberg_blog_listing_pagination_active_hover_color', function(){
    return '#ffb606';
});

add_filter( 'bizberg_sidebar_widget_link_color_hover', function(){
    return '#ffb606';
});

add_filter( 'bizberg_sidebar_widget_title_color', function(){
    return '#ffb606';
});

add_filter( 'bizberg_footer_social_icon_background', function(){
    return '#ffb606';
});

add_filter( 'bizberg_footer_social_icon_color', function(){
    return '#fff';
});

add_filter( 'bizberg_getting_started_screenshot', function(){
    return true;
});

add_filter( 'bizberg_banner_title', 'education_business_banner_title' );
function education_business_banner_title(){
    return current_user_can( 'edit_theme_options' ) ? esc_html__( 'Martin Peterson' , 'education-business' ) : '';
}

add_action( 'after_setup_theme', 'education_business_setup_theme' );
function education_business_setup_theme() {
    add_theme_support( 'title-tag' );
    add_theme_support( 'automatic-feed-links' );
}
// BEGIN ENQUEUE PARENT ACTION
// AUTO GENERATED - Do not modify or remove comment markers above or below:

if ( !function_exists( 'chld_thm_cfg_locale_css' ) ):
    function chld_thm_cfg_locale_css( $uri ){
        if ( empty( $uri ) && is_rtl() && file_exists( get_template_directory() . '/rtl.css' ) )
            $uri = get_template_directory_uri() . '/rtl.css';
        return $uri;
    }
endif;
add_filter( 'locale_stylesheet_uri', 'chld_thm_cfg_locale_css' );

// END ENQUEUE PARENT ACTION


// Required files for theme the functionality

// MATH POST TYPE
require_once get_stylesheet_directory() . '/inc/math-post-type.php';
// ENGLISH POST TYPE
require_once get_stylesheet_directory() . '/inc/english-post-type.php';
// SCIENCE POST TYPE
require_once get_stylesheet_directory() . '/inc/science-post-type.php';

// One-time function to assign grade levels to all existing english_skill posts
// Run this once, then remove it or comment it out
function bulk_assign_english_grade_levels() {
    $args = array(
        'post_type' => 'english_skill',
        'posts_per_page' => -1,
        'post_status' => 'any'
    );
    
    $posts = get_posts($args);
    
    foreach ($posts as $post) {
        auto_assign_english_grade_level_from_title($post->ID);
    }
    
    echo 'Processed ' . count($posts) . ' posts';
}

// Uncomment the line below, load any admin page once, then comment it back out
//add_action('admin_init', 'bulk_assign_english_grade_levels');