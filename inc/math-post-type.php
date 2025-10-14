<?php
// For CSV imports specifically - clean content during import
add_action('wp_insert_post_data', function($data, $postarr) {
    if (isset($data['post_type']) && $data['post_type'] === 'math_skill') {
        // Remove any p tags that might have been added during import
        $data['post_content'] = preg_replace('/<p[^>]*>/', '', $data['post_content']);
        $data['post_content'] = str_replace('</p>', '', $data['post_content']);
        // Also clean up empty paragraphs and double line breaks
        $data['post_content'] = preg_replace('/^\s*<p[^>]*>\s*<\/p>\s*/', '', $data['post_content']);
        $data['post_content'] = trim($data['post_content']);
    }
    return $data;
}, 10, 2);

// Register CSV Source taxonomy for Math Skills
register_taxonomy(
    'csv_source',
    'math_skill',
    array(
        'label'        => 'CSV Source',
        'rewrite'      => array( 'slug' => 'csv-source' ),
        'hierarchical' => false, // Tags-style (non-hierarchical) for easier management
        'show_ui'      => true,
        'show_in_menu' => true,
        'show_in_rest' => true,
        'meta_box_cb'  => null,
        'show_admin_column' => false, // We'll handle this with our custom column
        'labels' => array(
            'name'              => 'CSV Sources',
            'singular_name'     => 'CSV Source',
            'search_items'      => 'Search CSV Sources',
            'all_items'         => 'All CSV Sources',
            'edit_item'         => 'Edit CSV Source',
            'update_item'       => 'Update CSV Source',
            'add_new_item'      => 'Add New CSV Source',
            'new_item_name'     => 'New CSV Source Name',
            'menu_name'         => 'CSV Sources',
        ),
    )
);

// Helper function to get CSV sources for a specific grade (TAXONOMY VERSION)
function get_csv_sources_for_grade($grade_term_id) {
    // Get all posts for this grade (including child grades)
    $child_grades = get_terms(array(
        'taxonomy'   => 'math_grade',
        'hide_empty' => false,
        'parent'     => $grade_term_id,
    ));
    
    // Include the parent grade and all child grades
    $grade_ids = array($grade_term_id);
    if (!empty($child_grades)) {
        $grade_ids = array_merge($grade_ids, wp_list_pluck($child_grades, 'term_id'));
    }
    
    // Get all posts for these grades
    $posts = get_posts(array(
        'post_type'      => 'math_skill',
        'posts_per_page' => -1,
        'tax_query'      => array(
            array(
                'taxonomy' => 'math_grade',
                'field'    => 'term_id',
                'terms'    => $grade_ids,
            ),
        ),
        'fields' => 'ids'
    ));
    
    if (empty($posts)) {
        return array();
    }
    
    // Get unique CSV sources from these posts using the taxonomy
    $csv_sources = array();
    foreach ($posts as $post_id) {
        $post_csv_sources = get_the_terms($post_id, 'csv_source');
        if (!empty($post_csv_sources) && !is_wp_error($post_csv_sources)) {
            foreach ($post_csv_sources as $csv_term) {
                // Add to array if not already present
                if (!in_array($csv_term->name, $csv_sources)) {
                    $csv_sources[] = $csv_term->name;
                }
            }
        }
    }
    
    // Sort the CSV sources alphabetically
    sort($csv_sources);
    
    return $csv_sources;
}

// Adding the Math Skills Custom Post Type
function register_math_skills_post_type() {
    $labels = array(
        'name'               => 'Math Skills',
        'singular_name'      => 'Math Skill',
        'add_new'            => 'Add New',
        'add_new_item'       => 'Add New Math Skill',
        'edit_item'          => 'Edit Math Skill',
        'new_item'           => 'New Math Skill',
        'all_items'          => 'All Math Skills',
        'view_item'          => 'View Math Skill',
        'search_items'       => 'Search Math Skills',
        'not_found'          => 'No Math Skills found',
        'not_found_in_trash' => 'No Math Skills found in Trash',
        'menu_name'          => 'Math Skills'
    );

    $args = array(
        'labels'             => $labels,
        'public'             => true,
        'has_archive'        => true,
        'menu_position'      => 5,
        'menu_icon'          => 'dashicons-welcome-learn-more',
        'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
        'show_in_rest'       => true,
    );

    register_post_type( 'math_skill', $args );
}
add_action( 'init', 'register_math_skills_post_type' );

// Nav Menu for Grade Levels - UPDATED WITH CSV INFO
add_action('wp_ajax_get_grade_children', 'get_grade_children_callback');
add_action('wp_ajax_nopriv_get_grade_children', 'get_grade_children_callback');
function get_grade_children_callback() {
    $parent_id = isset($_POST['parent_id']) ? intval($_POST['parent_id']) : 0;
    
    // Get children terms using WordPress parent/child taxonomy structure
    $children = get_terms(array(
        'taxonomy'   => 'math_grade',
        'hide_empty' => false,
        'parent'     => $parent_id,
    ));

    if ($children && !is_wp_error($children)) {
        echo '<ul class="grade-children-list">';
        foreach ($children as $child) {
            $term_link = get_term_link($child);
            echo '<li><a href="' . esc_url($term_link) . '"><span style="font-weight:bold;">' . esc_html($child->name) . '</span>';
            
            // Build description with CSV sources
            $description_parts = array();
            
            // Add original description if it exists
            if (!empty($child->description)) {
                $description_parts[] = esc_html($child->description);
            }
            
            // Add CSV sources
            $csv_sources = get_csv_sources_for_grade($child->term_id);
            if (!empty($csv_sources)) {
                if (count($csv_sources) === 1) {
                    $description_parts[] = $csv_sources[0];
                } else {
                    $description_parts[] = implode(', ', $csv_sources);
                }
            }
            
            // Display the combined description
            if (!empty($description_parts)) {
                echo '  - <span class="child-desc">' . implode(' | ', $description_parts) . '</span>';
            }
            
            echo '</a></li>';
        }
        echo '</ul>';
    } else {
        echo '<p>No sub-levels found for this grade. Check back later.</p>';
    }
    wp_die();
}

// Domains with Grade Levels AJAX handler - UPDATED WITH CSV INFO
add_action('wp_ajax_get_domains_with_grades', 'get_domains_with_grades_callback');
add_action('wp_ajax_nopriv_get_domains_with_grades', 'get_domains_with_grades_callback');
function get_domains_with_grades_callback() {
    $parent_id = isset($_POST['parent_id']) ? intval($_POST['parent_id']) : 0;
    
    // Get the child terms using WordPress parent/child taxonomy structure
    $child_grades = get_terms(array(
        'taxonomy'   => 'math_grade',
        'hide_empty' => false,
        'parent'     => $parent_id,
    ));
    
    if (empty($child_grades) || is_wp_error($child_grades)) {
        $parent_term = get_term($parent_id, 'math_grade');
        $parent_name = $parent_term ? $parent_term->name : 'this grade';
        echo '<p>No sub-levels found for Grade ' . esc_html($parent_name) . '.</p>';
        wp_die();
    }
    
    // Get child grade IDs
    $child_grade_ids = wp_list_pluck($child_grades, 'term_id');
    
    // Get all domains
    $domains = get_terms(array(
        'taxonomy'   => 'math_domain',
        'hide_empty' => false,
        'orderby'    => 'name',
        'order'      => 'ASC'
    ));

    if (empty($domains) || is_wp_error($domains)) {
        echo '<p>No domains found.</p>';
        wp_die();
    }

    // Build array of domains with their sub-grades
    $domains_with_grades = array();
    
    foreach ($domains as $domain) {
        // Get posts that have this domain AND one of our child grades
        $posts_with_domain_and_grade = get_posts(array(
            'post_type'      => 'math_skill',
            'posts_per_page' => -1,
            'tax_query'      => array(
                'relation' => 'AND',
                array(
                    'taxonomy' => 'math_domain',
                    'field'    => 'term_id',
                    'terms'    => $domain->term_id,
                ),
                array(
                    'taxonomy' => 'math_grade',
                    'field'    => 'term_id',
                    'terms'    => $child_grade_ids,
                ),
            ),
            'fields' => 'ids'
        ));

        if (!empty($posts_with_domain_and_grade)) {
            // Get all grade levels from posts in this domain (only the child grades)
            $domain_grade_levels = array();
            foreach ($posts_with_domain_and_grade as $post_id) {
                $post_grades = get_the_terms($post_id, 'math_grade');
                if (!empty($post_grades) && !is_wp_error($post_grades)) {
                    foreach ($post_grades as $grade) {
                        // Only include if this grade is one of our child grades
                        if (in_array($grade->term_id, $child_grade_ids)) {
                            $domain_grade_levels[$grade->term_id] = $grade;
                        }
                    }
                }
            }

            if (!empty($domain_grade_levels)) {
                // Sort grade levels by name (numerically)
                uasort($domain_grade_levels, function($a, $b) {
                    return version_compare($a->name, $b->name);
                });

                // Store domain with its sorted grades and the lowest grade value for sorting
                $grade_names = array_map(function($grade) { return $grade->name; }, $domain_grade_levels);
                $lowest_grade = min($grade_names);
                
                $domains_with_grades[] = array(
                    'domain' => $domain,
                    'grades' => $domain_grade_levels,
                    'lowest_grade' => $lowest_grade,
                    'post_count' => count($posts_with_domain_and_grade)
                );
            }
        }
    }
    
    if (empty($domains_with_grades)) {
        $parent_term = get_term($parent_id, 'math_grade');
        $parent_name = $parent_term ? $parent_term->name : 'this grade';
        echo '<p>No content found for Grade ' . esc_html($parent_name) . '.</p>';
        wp_die();
    }
    
    // Sort domains by their lowest sub-grade
    usort($domains_with_grades, function($a, $b) {
        return version_compare($a['lowest_grade'], $b['lowest_grade']);
    });

    echo '<div class="domains-grade-levels-list">';
    
    // Display domains in order of their lowest sub-grade
    foreach ($domains_with_grades as $domain_data) {
        $domain = $domain_data['domain'];
        $domain_grade_levels = $domain_data['grades'];
        $post_count = $domain_data['post_count'];
        
        // Display domain heading
        echo '<h3 style="color: #333; border-bottom: 2px solid #0073aa; padding-bottom: 5px; margin: 25px 0 15px 0;">' . esc_html($domain->name) . '</h3>';
        
        // Display grade levels (already sorted)
        echo '<ul class="grade-children-list" style="margin-left: 20px;">';
        foreach ($domain_grade_levels as $grade_level) {
            $term_link = get_term_link($grade_level);
            echo '<li><a href="' . esc_url($term_link) . '"><span style="font-weight:bold;">' . esc_html($grade_level->name) . '</span>';
            
            // Add video link if it exists
            if (function_exists('get_field')) {
                $video_link = get_field('video_link', 'math_grade_' . $grade_level->term_id);
                if ($video_link) {
                    echo ' <a href="' . esc_url($video_link) . '" target="_blank" style="color:#0073aa;">[Video]</a>';
                }
            }
            
            // Build description with CSV sources
            $description_parts = array();
            
            // Add original description if it exists
            if (!empty($grade_level->description)) {
                $description_parts[] = esc_html($grade_level->description);
            }
            
            // Add CSV sources
            $csv_sources = get_csv_sources_for_grade($grade_level->term_id);
            if (!empty($csv_sources)) {
                if (count($csv_sources) === 1) {
                    $description_parts[] = $csv_sources[0];
                } else {
                    $description_parts[] = implode(', ', $csv_sources);
                }
            }
            
            // Display the combined description
            if (!empty($description_parts)) {
                echo '  - <span class="child-desc">' . implode(' | ', $description_parts) . '</span>';
            }
            
            echo '</a></li>';
        }
        echo '</ul>';
    }
    
    echo '</div>';
    wp_die();
}

// Helper function to get proper image URL (for simple paths)
function math_skill_get_image_url($path) {
    if (filter_var($path, FILTER_VALIDATE_URL)) {
        return $path;
    }
    if (strpos($path, '/wp-content/') === 0) {
        return home_url($path);
    }
    return $path;
}

// Helper function to check if content is an image path or contains image blocks
function math_skill_is_image_content($content) {
    $content = trim($content);
    if (strpos($content, '<!-- wp:image') !== false) {
        return true;
    }
    $image_extensions = array('jpg', 'jpeg', 'png', 'gif', 'webp', 'svg');
    $extension = strtolower(pathinfo($content, PATHINFO_EXTENSION));
    return in_array($extension, $image_extensions) && 
           (strpos($content, '/wp-content/') === 0 || filter_var($content, FILTER_VALIDATE_URL));
}

// Helper function to get proper image display
function math_skill_get_image_display($content) {
    if (strpos($content, '<!-- wp:') !== false) {
        $rendered = do_blocks($content);
        $rendered = str_replace('<img', '<img style="max-width:100px !important;height:auto !important;object-fit:contain !important;display:block !important;margin:0 !important;float:none !important;text-align:left !important;"', $rendered);
        $rendered = str_replace('<figure', '<figure style="text-align:left !important;margin:0 !important;display:block !important;"', $rendered);
        $rendered = str_replace('<div', '<div style="text-align:left !important;margin:0 !important;"', $rendered);
        return '<div style="text-align:left !important;display:block !important;width:100% !important;">' . $rendered . '</div>';
    }
    if (math_skill_is_image_content($content)) {
        $image_url = math_skill_get_image_url($content);
        return '<div style="text-align:left;"><img src="' . esc_url($image_url) . '" alt="Question Image" style="max-width:100px;height:auto;object-fit:contain;" /></div>';
    }
    return esc_html(substr($content, 0, 100)) . (strlen($content) > 100 ? '...' : '');
}

// Math Skills filters - UPDATED WITH CSV COLUMN
add_filter('manage_math_skill_posts_columns', function($columns) {
    return array(
        'cb'             => '<input type="checkbox" />',
        'title'          => 'Title',
        'question'       => 'Question',
        'answer'         => 'Answer',
        'math_grade'     => 'Grade Level',
        'math_difficulty'=> 'Difficulty',
        'domain'         => 'Domain',
        'csv_source'     => 'CSV Source',
        'math_explanation'    => 'Explanation',
        'date'           => 'Date',
    );
});

// Custom columns for Math Skills - UPDATED WITH CSV TAXONOMY
add_action('manage_math_skill_posts_custom_column', function($column, $post_id) {
    switch ($column) {
        case 'question':
            $question = get_post_field('post_content', $post_id);
            if ($question) {
                echo math_skill_get_image_display($question);
            } else {
                echo '—';
            }
            break;
        case 'answer':
            $terms = get_the_terms($post_id, 'answer');
            if (!empty($terms) && !is_wp_error($terms)) {
                $answer = wp_list_pluck($terms, 'name');
                echo esc_html(implode(', ', $answer));
            } else {
                echo '—';
            }
            break;
        case 'math_grade':
            $terms = get_the_terms($post_id, 'math_grade');
            if (!empty($terms) && !is_wp_error($terms)) {
                $grades = [];
                foreach ($terms as $term) {
                    $grade_name = esc_html($term->name);
                    $video_link = get_field('video_link', 'math_grade_' . $term->term_id);
                    if ($video_link) {
                        $grade_name .= ' <a href="' . esc_url($video_link) . '" target="_blank" style="color:#0073aa;">[Video]</a>';
                    }
                    $grades[] = $grade_name;
                }
                echo implode(', ', $grades);
            } else {
                echo '—';
            }
            break;
        case 'math_difficulty':
            $terms = get_the_terms($post_id, 'math_difficulty');
            if (!empty($terms) && !is_wp_error($terms)) {
                $difficulties = wp_list_pluck($terms, 'name');
                echo esc_html(implode(', ', $difficulties));
            } else {
                echo '—';
            }
            break;
        case 'domain':
            $terms = get_the_terms($post_id, 'math_domain');
            if (!empty($terms) && !is_wp_error($terms)) {
                $domains = wp_list_pluck($terms, 'name');
                echo esc_html(implode(', ', $domains));
            } else {
                echo '—';
            }
            break;
        case 'csv_source':
            $terms = get_the_terms($post_id, 'csv_source');
            if (!empty($terms) && !is_wp_error($terms)) {
                $csv_names = wp_list_pluck($terms, 'name');
                foreach ($csv_names as $csv_name) {
                    echo '<span style="font-size:11px;color:#666;background:#f0f0f0;padding:2px 6px;border-radius:3px;margin-right:5px;">' . esc_html($csv_name) . '</span>';
                }
            } else {
                echo '<span style="font-size:11px;color:#999;">—</span>';
            }
            break;
        case 'math_explanation':
            $math_explanation = get_post_field('post_excerpt', $post_id);
            echo esc_html($math_explanation ? $math_explanation : '—');
            break;
    }
}, 10, 2);
    
// Grade Level taxonomy columns: Only show Name, Video Link, Description - UPDATED WITH CSV INFO
add_filter('manage_edit-math_grade_columns', function($columns) {
    return array(
        'cb'          => '<input type="checkbox" />',
        'name'        => 'Name',
        'video_link'  => 'Video Link',
        'description' => 'Description',
        'csv_sources' => 'CSV Sources',
    );
});

add_action('manage_math_grade_custom_column', function($content, $column, $term_id) {
    switch ($column) {
        case 'video_link':
            $video_link = get_field('video_link', 'math_grade_' . $term_id);
            if ($video_link) {
                $content = '<a href="' . esc_url($video_link) . '" target="_blank">Watch Video</a>';
            } else {
                $content = '—';
            }
            break;
        case 'description':
            $term = get_term($term_id, 'math_grade');
            $content = esc_html($term->description);
            break;
        case 'csv_sources':
            $csv_sources = get_csv_sources_for_grade($term_id);
            if (!empty($csv_sources)) {
                $content = implode('<br>', array_map('esc_html', $csv_sources));
            } else {
                $content = '—';
            }
            break;
    }
    return $content;
}, 10, 3);

// Frontend display function for Math Skills (for use in templates)
function display_math_skill_question($post_id = null) {
    if (!$post_id) {
        global $post;
        $post_id = $post->ID;
    }
    $question = get_post_field('post_content', $post_id);
    if ($question) {
        if (strpos($question, '<!-- wp:') !== false) {
            return do_blocks($question);
        }
        if (math_skill_is_image_content($question)) {
            $image_url = math_skill_get_image_url($question);
            return '<img src="' . esc_url($image_url) . '" alt="Math Question" class="math-question-image" style="max-width:100%;height:auto;" />';
        } else {
            return '<div class="math-question-text">' . wp_kses_post($question) . '</div>';
        }
    }
    return '';
}

// Function to get CSV source for a post (updated to use taxonomy)
function get_math_skill_csv_source($post_id = null) {
    if (!$post_id) {
        global $post;
        $post_id = $post->ID;
    }
    
    $terms = get_the_terms($post_id, 'csv_source');
    if (!empty($terms) && !is_wp_error($terms)) {
        $csv_names = wp_list_pluck($terms, 'name');
        return implode(', ', $csv_names);
    }
    return '';
}

// Math Grade Level Taxonomy
register_taxonomy(
    'answer',
    'math_skill',
    array(
        'label'        => 'Answer',
        'rewrite'      => array( 'slug' => 'math-grade' ),
        'hierarchical' => true,
        'show_ui'      => true,
        'show_in_menu' => true,
        'show_in_rest' => true,
        'meta_box_cb'  => null,
    )
);

register_taxonomy(
    'math_grade',
    'math_skill',
    array(
        'label'        => 'Grade Level',
        'rewrite'      => array( 'slug' => 'math-grade' ),
        'hierarchical' => true,
        'show_ui'      => true,
        'show_in_menu' => true,
        'show_in_rest' => true,
        'meta_box_cb'  => null,
    )
);

// Math Skill Difficulty Level
register_taxonomy(
    'math_difficulty',
    'math_skill',
    array(
        'label'        => 'Difficulty Level',
        'rewrite'      => array( 'slug' => 'math-difficulty' ),
        'hierarchical' => true,
        'show_ui'      => true,
        'show_in_menu' => true,
        'show_in_rest' => true,
        'meta_box_cb'  => null,
    )
);

// Register Domain taxonomy for Math Skills
register_taxonomy(
    'math_domain',
    'math_skill',
    array(
        'label'        => 'Domain',
        'rewrite'      => array( 'slug' => 'math-domain' ),
        'hierarchical' => true,
        'show_ui'      => true,
        'show_in_menu' => true,
        'show_in_rest' => true,
        'meta_box_cb'  => null,
    )
);

// Auto-assign Grade Level based on title format (e.g., "3.13-121" -> "3.13")
// Auto-assign Grade Level based on title format (e.g., "3.13-121" -> creates parent "3" and child "3.13")
function auto_assign_grade_level_from_title($post_id) {
    // Check if this is a math_skill post type
    if (get_post_type($post_id) !== 'math_skill') {
        return;
    }
    
    // Avoid infinite loops and autosave
    if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
        return;
    }
    
    // Get the post title
    $post_title = get_the_title($post_id);
    
    // Extract the part before the dash
    $dash_position = strpos($post_title, '-');
    if ($dash_position !== false) {
        $grade_level = trim(substr($post_title, 0, $dash_position));
        
        // Only proceed if we actually extracted something
        if (!empty($grade_level)) {
            
            // Parse the grade value (e.g., "3.13" becomes parent "3" and child "3.13")
            $parts = explode('.', $grade_level);
            $parent_grade = $parts[0]; // This is the whole number (e.g., "3")
            
            // Only proceed if we have a valid parent grade
            if (!is_numeric($parent_grade)) {
                return;
            }
            
            // Create or get parent grade term (whole number only)
            $parent_term = term_exists($parent_grade, 'math_grade');
            if (!$parent_term) {
                $parent_term = wp_insert_term($parent_grade, 'math_grade', array(
                    'description' => 'Grade ' . $parent_grade,
                    'parent' => 0 // Ensure this is a top-level term
                ));
            }
            
            if (is_wp_error($parent_term)) {
                return;
            }
            
            $parent_term_id = is_array($parent_term) ? $parent_term['term_id'] : $parent_term;
            
            // If this is a sub-grade (has decimal), create the sub-grade term under parent
            if (strpos($grade_level, '.') !== false) {
                // Create or get the sub-grade term
                $sub_grade_term = term_exists($grade_level, 'math_grade');
                if (!$sub_grade_term) {
                    $sub_grade_term = wp_insert_term($grade_level, 'math_grade', array(
                        'parent' => $parent_term_id,
                        //'description' => 'Sub-level ' . $grade_level
                    ));
                } else {
                    // If term exists but doesn't have the right parent, update it
                    $existing_term = get_term($sub_grade_term, 'math_grade');
                    if ($existing_term->parent != $parent_term_id) {
                        wp_update_term($sub_grade_term, 'math_grade', array(
                            'parent' => $parent_term_id
                        ));
                    }
                }
                
                if (!is_wp_error($sub_grade_term)) {
                    $sub_grade_term_id = is_array($sub_grade_term) ? $sub_grade_term['term_id'] : $sub_grade_term;
                    // Assign the sub-grade to the post (this is what gets checked/assigned)
                    wp_set_post_terms($post_id, array($sub_grade_term_id), 'math_grade', false);
                }
            } else {
                // This is a whole number grade, assign the parent term
                wp_set_post_terms($post_id, array($parent_term_id), 'math_grade', false);
            }
        }
    }
}
add_action('save_post', 'auto_assign_grade_level_from_title');

// Function to display domains with their associated grade levels - UPDATED WITH CSV INFO
function display_domains_with_grade_levels() {
    // Get all domains
    $domains = get_terms(array(
        'taxonomy'   => 'math_domain',
        'hide_empty' => false,
        'orderby'    => 'name',
        'order'      => 'ASC'
    ));

    if (empty($domains) || is_wp_error($domains)) {
        echo '<p>No domains found.</p>';
        return;
    }

    echo '<div class="domains-grade-levels-list">';
    
    foreach ($domains as $domain) {
        // Get posts that have this domain
        $posts_with_domain = get_posts(array(
            'post_type'      => 'math_skill',
            'posts_per_page' => -1,
            'tax_query'      => array(
                array(
                    'taxonomy' => 'math_domain',
                    'field'    => 'term_id',
                    'terms'    => $domain->term_id,
                ),
            ),
            'fields' => 'ids' // Only get post IDs for efficiency
        ));

        if (!empty($posts_with_domain)) {
            // Display domain heading
            echo '<h3 class="domain-heading">' . esc_html($domain->name) . '</h3>';
            
            // Get all grade levels from posts in this domain
            $grade_levels = array();
            foreach ($posts_with_domain as $post_id) {
                $post_grades = get_the_terms($post_id, 'math_grade');
                if (!empty($post_grades) && !is_wp_error($post_grades)) {
                    foreach ($post_grades as $grade) {
                        $grade_levels[$grade->term_id] = $grade;
                    }
                }
            }

            // Sort grade levels by name
            uasort($grade_levels, function($a, $b) {
                return strnatcmp($a->name, $b->name);
            });

            // Display grade levels
            if (!empty($grade_levels)) {
                echo '<ul class="grade-levels-list">';
                foreach ($grade_levels as $grade_level) {
                    $term_link = get_term_link($grade_level);
                    $video_link = get_field('video_link', 'math_grade_' . $grade_level->term_id);
                    
                    echo '<li>';
                    echo '<a href="' . esc_url($term_link) . '">' . esc_html($grade_level->name) . '</a>';
                    
                    if ($video_link) {
                        echo ' <a href="' . esc_url($video_link) . '" target="_blank" class="video-link" style="color:#0073aa;">[Video]</a>';
                    }
                    
                    // Build description with CSV sources
                    $description_parts = array();
                    
                    // Add original description if it exists
                    if (!empty($grade_level->description)) {
                        $description_parts[] = esc_html($grade_level->description);
                    }
                    
                    // Add CSV sources
                    $csv_sources = get_csv_sources_for_grade($grade_level->term_id);
                    if (!empty($csv_sources)) {
                        if (count($csv_sources) === 1) {
                            $description_parts[] =  $csv_sources[0];
                        } else {
                            $description_parts[] = implode(', ', $csv_sources);
                        }
                    }
                    
                    // Display the combined description
                    if (!empty($description_parts)) {
                        echo ' - <span class="grade-description">' . implode(' | ', $description_parts) . '</span>';
                    }
                    
                    echo '</li>';
                }
                echo '</ul>';
            } else {
                echo '<p class="no-grades">No grade levels found for this domain.</p>';
            }
        }
    }
    
    echo '</div>';
}

// Shortcode version for easy use in posts/pages
function domains_grade_levels_shortcode($atts) {
    ob_start();
    display_domains_with_grade_levels();
    return ob_get_clean();
}
add_shortcode('domains_grade_levels', 'domains_grade_levels_shortcode');

function get_parent_grades_for_navigation() {
    $parent_grades = get_terms(array(
        'taxonomy'   => 'math_grade',
        'hide_empty' => false,
        'parent'     => 0, // Only get top-level terms (no parent)
    ));
    
    if (empty($parent_grades) || is_wp_error($parent_grades)) {
        return array();
    }
    
    // Sort numerically
    usort($parent_grades, function($a, $b) {
        return intval($a->name) - intval($b->name);
    });
    
    return $parent_grades;
}

// Handle load_subject_questions AJAX call
add_action('wp_ajax_load_subject_questions', 'load_subject_questions_callback');
add_action('wp_ajax_nopriv_load_subject_questions', 'load_subject_questions_callback');
function load_subject_questions_callback() {
    // For now, just return a simple response
    echo 'Subject questions functionality not yet implemented.';
    wp_die();
}

// Optional: Add CSV Source filter to Math Skills admin list
add_action('restrict_manage_posts', function() {
    global $typenow;
    if ($typenow == 'math_skill') {
        $csv_sources = get_terms(array(
            'taxonomy' => 'csv_source',
            'hide_empty' => false,
        ));
        
        if (!empty($csv_sources)) {
            $selected = isset($_GET['csv_source']) ? $_GET['csv_source'] : '';
            echo '<select name="csv_source">';
            echo '<option value="">All CSV Sources</option>';
            foreach ($csv_sources as $source) {
                $selected_attr = selected($selected, $source->slug, false);
                echo '<option value="' . esc_attr($source->slug) . '"' . $selected_attr . '>' . esc_html($source->name) . '</option>';
            }
            echo '</select>';
        }
    }
});

// Handle the filter
add_filter('parse_query', function($query) {
    global $pagenow, $typenow;
    if ($pagenow == 'edit.php' && $typenow == 'math_skill' && isset($_GET['csv_source']) && $_GET['csv_source'] != '') {
        $query->query_vars['tax_query'] = array(
            array(
                'taxonomy' => 'csv_source',
                'field'    => 'slug',
                'terms'    => $_GET['csv_source']
            )
        );
    }
});

?>