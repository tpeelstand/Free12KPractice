<?php
// For CSV imports specifically - clean content during import
add_action('wp_insert_post_data', function($data, $postarr) {
    if (isset($data['post_type']) && $data['post_type'] === 'english_skill') {
        // Remove any p tags that might have been added during import
        $data['post_content'] = preg_replace('/<p[^>]*>/', '', $data['post_content']);
        $data['post_content'] = str_replace('</p>', '', $data['post_content']);
        // Also clean up empty paragraphs and double line breaks
        $data['post_content'] = preg_replace('/^\s*<p[^>]*>\s*<\/p>\s*/', '', $data['post_content']);
        $data['post_content'] = trim($data['post_content']);
    }
    return $data;
}, 10, 2);

// Register CSV Source taxonomy for English Skills
register_taxonomy(
    'english_csv_source',
    'english_skill',
    array(
        'label'        => 'CSV Source',
        'rewrite'      => array( 'slug' => 'english-csv-source' ),
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
function get_english_csv_sources_for_grade($grade_term_id) {
    // Get all posts for this grade (including child grades)
    $child_grades = get_terms(array(
        'taxonomy'   => 'english_grade',
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
        'post_type'      => 'english_skill',
        'posts_per_page' => -1,
        'tax_query'      => array(
            array(
                'taxonomy' => 'english_grade',
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
        $post_csv_sources = get_the_terms($post_id, 'english_csv_source');
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

// Adding the English Skills Custom Post Type
function register_english_skills_post_type() {
    $labels = array(
        'name'               => 'English Skills',
        'singular_name'      => 'English Skill',
        'add_new'            => 'Add New',
        'add_new_item'       => 'Add New English Skill',
        'edit_item'          => 'Edit English Skill',
        'new_item'           => 'New English Skill',
        'all_items'          => 'All English Skills',
        'view_item'          => 'View English Skill',
        'search_items'       => 'Search English Skills',
        'not_found'          => 'No English Skills found',
        'not_found_in_trash' => 'No English Skills found in Trash',
        'menu_name'          => 'English Skills'
    );

    $args = array(
        'labels'             => $labels,
        'public'             => true,
        'has_archive'        => true,
        'menu_position'      => 5,
        'menu_icon'          => 'dashicons-book-alt',
        'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
        'show_in_rest'       => true,
    );

    register_post_type( 'english_skill', $args );
}
add_action( 'init', 'register_english_skills_post_type' );

// Nav Menu for Grade Levels - UPDATED WITH CSV INFO
add_action('wp_ajax_get_english_grade_children', 'get_english_grade_children_callback');
add_action('wp_ajax_nopriv_get_english_grade_children', 'get_english_grade_children_callback');
function get_english_grade_children_callback() {
    $parent_id = isset($_POST['parent_id']) ? intval($_POST['parent_id']) : 0;
    
    // Get children terms using WordPress parent/child taxonomy structure
    $children = get_terms(array(
        'taxonomy'   => 'english_grade',
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
            $csv_sources = get_english_csv_sources_for_grade($child->term_id);
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
add_action('wp_ajax_get_english_domains_with_grades', 'get_english_domains_with_grades_callback');
add_action('wp_ajax_nopriv_get_english_domains_with_grades', 'get_english_domains_with_grades_callback');
function get_english_domains_with_grades_callback() {
    $parent_id = isset($_POST['parent_id']) ? intval($_POST['parent_id']) : 0;
    
    // Get the child terms using WordPress parent/child taxonomy structure
    $child_grades = get_terms(array(
        'taxonomy'   => 'english_grade',
        'hide_empty' => false,
        'parent'     => $parent_id,
    ));
    
    if (empty($child_grades) || is_wp_error($child_grades)) {
        $parent_term = get_term($parent_id, 'english_grade');
        $parent_name = $parent_term ? $parent_term->name : 'this grade';
        echo '<p>No sub-levels found for Grade ' . esc_html($parent_name) . '.</p>';
        wp_die();
    }
    
    // Get child grade IDs
    $child_grade_ids = wp_list_pluck($child_grades, 'term_id');
    
    // Get all domains
    $domains = get_terms(array(
        'taxonomy'   => 'english_domain',
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
            'post_type'      => 'english_skill',
            'posts_per_page' => -1,
            'tax_query'      => array(
                'relation' => 'AND',
                array(
                    'taxonomy' => 'english_domain',
                    'field'    => 'term_id',
                    'terms'    => $domain->term_id,
                ),
                array(
                    'taxonomy' => 'english_grade',
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
                $post_grades = get_the_terms($post_id, 'english_grade');
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
        $parent_term = get_term($parent_id, 'english_grade');
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
                $video_link = get_field('video_link', 'english_grade_' . $grade_level->term_id);
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
            $csv_sources = get_english_csv_sources_for_grade($grade_level->term_id);
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
function english_skill_get_image_url($path) {
    if (filter_var($path, FILTER_VALIDATE_URL)) {
        return $path;
    }
    if (strpos($path, '/wp-content/') === 0) {
        return home_url($path);
    }
    return $path;
}

// Helper function to check if content is an image path or contains image blocks
function english_skill_is_image_content($content) {
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
function english_skill_get_image_display($content) {
    if (strpos($content, '<!-- wp:') !== false) {
        $rendered = do_blocks($content);
        $rendered = str_replace('<img', '<img style="max-width:100px !important;height:auto !important;object-fit:contain !important;display:block !important;margin:0 !important;float:none !important;text-align:left !important;"', $rendered);
        $rendered = str_replace('<figure', '<figure style="text-align:left !important;margin:0 !important;display:block !important;"', $rendered);
        $rendered = str_replace('<div', '<div style="text-align:left !important;margin:0 !important;"', $rendered);
        return '<div style="text-align:left !important;display:block !important;width:100% !important;">' . $rendered . '</div>';
    }
    if (english_skill_is_image_content($content)) {
        $image_url = english_skill_get_image_url($content);
        return '<div style="text-align:left;"><img src="' . esc_url($image_url) . '" alt="Question Image" style="max-width:100px;height:auto;object-fit:contain;" /></div>';
    }
    return esc_html(substr($content, 0, 100)) . (strlen($content) > 100 ? '...' : '');
}

// English Skills filters - UPDATED WITH CSV COLUMN
add_filter('manage_english_skill_posts_columns', function($columns) {
    return array(
        'cb'             => '<input type="checkbox" />',
        'title'          => 'Title',
        'question'       => 'Question',
        'answer'         => 'Answer',
        'english_grade'     => 'Grade Level',
        'english_difficulty'=> 'Difficulty',
        'domain'         => 'Domain',
        'csv_source'     => 'CSV Source',
        'english_explanation'    => 'Explanation',
        'date'           => 'Date',
    );
});

// Custom columns for English Skills - UPDATED WITH CSV TAXONOMY
add_action('manage_english_skill_posts_custom_column', function($column, $post_id) {
    switch ($column) {
        case 'question':
            $question = get_post_field('post_content', $post_id);
            if ($question) {
                echo english_skill_get_image_display($question);
            } else {
                echo '—';
            }
            break;
        case 'answer':
            $terms = get_the_terms($post_id, 'english_answer');
            if (!empty($terms) && !is_wp_error($terms)) {
                $answer = wp_list_pluck($terms, 'name');
                echo esc_html(implode(', ', $answer));
            } else {
                echo '—';
            }
            break;
        case 'english_grade':
            $terms = get_the_terms($post_id, 'english_grade');
            if (!empty($terms) && !is_wp_error($terms)) {
                $grades = [];
                foreach ($terms as $term) {
                    $grade_name = esc_html($term->name);
                    $video_link = get_field('video_link', 'english_grade_' . $term->term_id);
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
        case 'english_difficulty':
            $terms = get_the_terms($post_id, 'english_difficulty');
            if (!empty($terms) && !is_wp_error($terms)) {
                $difficulties = wp_list_pluck($terms, 'name');
                echo esc_html(implode(', ', $difficulties));
            } else {
                echo '—';
            }
            break;
        case 'domain':
            $terms = get_the_terms($post_id, 'english_domain');
            if (!empty($terms) && !is_wp_error($terms)) {
                $domains = wp_list_pluck($terms, 'name');
                echo esc_html(implode(', ', $domains));
            } else {
                echo '—';
            }
            break;
        case 'csv_source':
            $terms = get_the_terms($post_id, 'english_csv_source');
            if (!empty($terms) && !is_wp_error($terms)) {
                $csv_names = wp_list_pluck($terms, 'name');
                foreach ($csv_names as $csv_name) {
                    echo '<span style="font-size:11px;color:#666;background:#f0f0f0;padding:2px 6px;border-radius:3px;margin-right:5px;">' . esc_html($csv_name) . '</span>';
                }
            } else {
                echo '<span style="font-size:11px;color:#999;">—</span>';
            }
            break;
        case 'english_explanation':
            $english_explanation = get_post_field('post_excerpt', $post_id);
            echo esc_html($english_explanation ? $english_explanation : '—');
            break;
    }
}, 10, 2);
    
// Grade Level taxonomy columns: Only show Name, Video Link, Description - UPDATED WITH CSV INFO
add_filter('manage_edit-english_grade_columns', function($columns) {
    return array(
        'cb'          => '<input type="checkbox" />',
        'name'        => 'Name',
        'video_link'  => 'Video Link',
        'description' => 'Description',
        'csv_sources' => 'CSV Sources',
    );
});

add_action('manage_english_grade_custom_column', function($content, $column, $term_id) {
    switch ($column) {
        case 'video_link':
            $video_link = get_field('video_link', 'english_grade_' . $term_id);
            if ($video_link) {
                $content = '<a href="' . esc_url($video_link) . '" target="_blank">Watch Video</a>';
            } else {
                $content = '—';
            }
            break;
        case 'description':
            $term = get_term($term_id, 'english_grade');
            $content = esc_html($term->description);
            break;
        case 'csv_sources':
            $csv_sources = get_english_csv_sources_for_grade($term_id);
            if (!empty($csv_sources)) {
                $content = implode('<br>', array_map('esc_html', $csv_sources));
            } else {
                $content = '—';
            }
            break;
    }
    return $content;
}, 10, 3);

// Frontend display function for English Skills (for use in templates)
function display_english_skill_question($post_id = null) {
    if (!$post_id) {
        global $post;
        $post_id = $post->ID;
    }
    $question = get_post_field('post_content', $post_id);
    if ($question) {
        if (strpos($question, '<!-- wp:') !== false) {
            return do_blocks($question);
        }
        if (english_skill_is_image_content($question)) {
            $image_url = english_skill_get_image_url($question);
            return '<img src="' . esc_url($image_url) . '" alt="English Question" class="english-question-image" style="max-width:100%;height:auto;" />';
        } else {
            return '<div class="english-question-text">' . wp_kses_post($question) . '</div>';
        }
    }
    return '';
}

// Function to get CSV source for a post (updated to use taxonomy)
function get_english_skill_csv_source($post_id = null) {
    if (!$post_id) {
        global $post;
        $post_id = $post->ID;
    }
    
    $terms = get_the_terms($post_id, 'english_csv_source');
    if (!empty($terms) && !is_wp_error($terms)) {
        $csv_names = wp_list_pluck($terms, 'name');
        return implode(', ', $csv_names);
    }
    return '';
}

// English Answer Taxonomy
register_taxonomy(
    'english_answer',
    'english_skill',
    array(
        'label'        => 'Answer',
        'rewrite'      => array( 'slug' => 'english-answer' ),
        'hierarchical' => true,
        'show_ui'      => true,
        'show_in_menu' => true,
        'show_in_rest' => true,
        'meta_box_cb'  => null,
    )
);

// English Grade Level Taxonomy
register_taxonomy(
    'english_grade',
    'english_skill',
    array(
        'label'        => 'Grade Level',
        'rewrite'      => array( 'slug' => 'english-grade' ),
        'hierarchical' => true,
        'show_ui'      => true,
        'show_in_menu' => true,
        'show_in_rest' => true,
        'meta_box_cb'  => null,
    )
);

// English Skill Difficulty Level
register_taxonomy(
    'english_difficulty',
    'english_skill',
    array(
        'label'        => 'Difficulty Level',
        'rewrite'      => array( 'slug' => 'english-difficulty' ),
        'hierarchical' => true,
        'show_ui'      => true,
        'show_in_menu' => true,
        'show_in_rest' => true,
        'meta_box_cb'  => null,
    )
);

// Register Domain taxonomy for English Skills
register_taxonomy(
    'english_domain',
    'english_skill',
    array(
        'label'        => 'Domain',
        'rewrite'      => array( 'slug' => 'english-domain' ),
        'hierarchical' => true,
        'show_ui'      => true,
        'show_in_menu' => true,
        'show_in_rest' => true,
        'meta_box_cb'  => null,
    )
);


// Auto-assign Grade Level based on title format (e.g., "RL.3.1.1" or "RL.3.1.1-1234" -> creates parent "3" and child "3.1")
function auto_assign_english_grade_level_from_title($post_id) {
    // Check if this is a english_skill post type
    if (get_post_type($post_id) !== 'english_skill') {
        return;
    }
    
    // Avoid infinite loops and autosave
    if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
        return;
    }
    
    // Get the post title
    $post_title = get_the_title($post_id);
    
    // Extract the part before the dash (if there is one), otherwise use the whole title
    $dash_position = strpos($post_title, '-');
    if ($dash_position !== false) {
        $before_dash = trim(substr($post_title, 0, $dash_position));
    } else {
        $before_dash = trim($post_title);
    }
    
    // Pattern to match formats like "RL.3.1.1" or "RL.3.13.4" and extract "3.1" or "3.13"
    // This captures: letters, then first number.second number
    if (preg_match('/[A-Z]+\.(\d+\.\d+)/i', $before_dash, $matches)) {
        $grade_level = $matches[1]; // This will be "3.1" or "3.13"
        
        // Parse the grade value (e.g., "3.1" becomes parent "3" and child "3.1")
        $parts = explode('.', $grade_level);
        $parent_grade = $parts[0]; // This is the whole number (e.g., "3")
        
        // Only proceed if we have a valid parent grade
        if (!is_numeric($parent_grade)) {
            return;
        }
        
        // Create or get parent grade term (whole number only)
        $parent_term = term_exists($parent_grade, 'english_grade');
        if (!$parent_term) {
            $parent_term = wp_insert_term($parent_grade, 'english_grade', array(
                'description' => 'Grade ' . $parent_grade,
                'parent' => 0 // Ensure this is a top-level term
            ));
        }
        
        if (is_wp_error($parent_term)) {
            return;
        }
        
        $parent_term_id = is_array($parent_term) ? $parent_term['term_id'] : $parent_term;
        
        // Create or get the sub-grade term (this will always have a decimal for this format)
        $sub_grade_term = term_exists($grade_level, 'english_grade');
        if (!$sub_grade_term) {
            $sub_grade_term = wp_insert_term($grade_level, 'english_grade', array(
                'parent' => $parent_term_id,
                //'description' => 'Sub-level ' . $grade_level
            ));
        } else {
            // If term exists but doesn't have the right parent, update it
            $sub_grade_term_id = is_array($sub_grade_term) ? $sub_grade_term['term_id'] : $sub_grade_term;
            $existing_term = get_term($sub_grade_term_id, 'english_grade');
            
            // Check if term was retrieved successfully and has wrong parent
            if ($existing_term && !is_wp_error($existing_term) && $existing_term->parent != $parent_term_id) {
                wp_update_term($sub_grade_term_id, 'english_grade', array(
                    'parent' => $parent_term_id
                ));
            }
        }
        
        if (!is_wp_error($sub_grade_term)) {
            $sub_grade_term_id = is_array($sub_grade_term) ? $sub_grade_term['term_id'] : $sub_grade_term;
            // Assign the sub-grade to the post (this is what gets displayed in Grade Level column)
            wp_set_post_terms($post_id, array($sub_grade_term_id), 'english_grade', false);
        }
    }
}
add_action('save_post', 'auto_assign_english_grade_level_from_title');

// Function to display domains with their associated grade levels - UPDATED WITH CSV INFO
function display_english_domains_with_grade_levels() {
    // Get all domains
    $domains = get_terms(array(
        'taxonomy'   => 'english_domain',
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
            'post_type'      => 'english_skill',
            'posts_per_page' => -1,
            'tax_query'      => array(
                array(
                    'taxonomy' => 'english_domain',
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
                $post_grades = get_the_terms($post_id, 'english_grade');
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
                    $video_link = get_field('video_link', 'english_grade_' . $grade_level->term_id);
                    
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
                    $csv_sources = get_english_csv_sources_for_grade($grade_level->term_id);
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
function english_domains_grade_levels_shortcode($atts) {
    ob_start();
    display_english_domains_with_grade_levels();
    return ob_get_clean();
}
add_shortcode('english_domains_grade_levels', 'english_domains_grade_levels_shortcode');

function get_english_parent_grades_for_navigation() {
    $parent_grades = get_terms(array(
        'taxonomy'   => 'english_grade',
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
add_action('wp_ajax_load_english_subject_questions', 'load_english_subject_questions_callback');
add_action('wp_ajax_nopriv_load_english_subject_questions', 'load_english_subject_questions_callback');
function load_english_subject_questions_callback() {
    // For now, just return a simple response
    echo 'Subject questions functionality not yet implemented.';
    wp_die();
}

// Optional: Add CSV Source filter to English Skills admin list
add_action('restrict_manage_posts', function() {
    global $typenow;
    if ($typenow == 'english_skill') {
        $csv_sources = get_terms(array(
            'taxonomy' => 'english_csv_source',
            'hide_empty' => false,
        ));
        
        if (!empty($csv_sources)) {
            $selected = isset($_GET['english_csv_source']) ? $_GET['english_csv_source'] : '';
            echo '<select name="english_csv_source">';
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
    if ($pagenow == 'edit.php' && $typenow == 'english_skill' && isset($_GET['english_csv_source']) && $_GET['english_csv_source'] != '') {
        $query->query_vars['tax_query'] = array(
            array(
                'taxonomy' => 'english_csv_source',
                'field'    => 'slug',
                'terms'    => $_GET['english_csv_source']
            )
        );
    }
});

?>