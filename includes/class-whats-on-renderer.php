<?php

if (!defined('ABSPATH')) {
    exit;
}

class TCC_Whats_On_Enhanced_Renderer {
    
    /**
     * Render the whats on guide
     * 
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function render($atts = []) {
        $atts = shortcode_atts([
            'posts_per_page' => -1,
            'meta_key' => '',
            'meta_value' => '',
            'months_ahead' => 3  // How many months ahead to show recurring events
        ], $atts);

        // Query whats-on posts
        $args = [
            'post_type' => 'whats-on',
            'post_status' => 'publish',
            'posts_per_page' => (int)$atts['posts_per_page'],
            'orderby' => 'title',
            'order' => 'ASC'
        ];

        if (!empty($atts['meta_key']) && !empty($atts['meta_value'])) {
            $args['meta_key'] = $atts['meta_key'];
            $args['meta_value'] = $atts['meta_value'];
        }

        $whats_on = new WP_Query($args);
        
        if (!$whats_on->have_posts()) {
            return '<p>No events found.</p>';
        }

        $cards_html = [];
        
        while ($whats_on->have_posts()) {
            $whats_on->the_post();
            $post_id = get_the_ID();
            
            // Get ACF fields
            $alternate_title = get_field('alternate_title', $post_id);
            $dates_data = get_field('dates', $post_id);
            $start_time = get_field('start_time', $post_id);
            $end_time = get_field('end_time', $post_id);
            $alt_end_time_label = get_field('alternate_end_time_label', $post_id);
            
            // Get post content for card flip
            $post_content = get_post_field('post_content', $post_id);
            $post_content = apply_filters('the_content', $post_content); // Apply WordPress content filters
            
            // Get venue area terms
            $venue_areas = wp_get_post_terms($post_id, 'venue-area', ['fields' => 'names']);
            $venue_classes = [];
            
            if (!is_wp_error($venue_areas) && !empty($venue_areas)) {
                foreach ($venue_areas as $venue) {
                    $venue_classes[] = 'venue-' . sanitize_html_class(strtolower($venue));
                }
            }
            
            // Get featured image - use original size for animated WebP support
            $featured_image_url = get_the_post_thumbnail_url($post_id, 'full');
            
            // Expand dates using RRULE with configurable time window
            $months_ahead = max(1, min(12, (int)$atts['months_ahead'])); // Limit between 1-12 months
            $expanded_dates = TCC_Gig_Guide_RRule::expand_dates($dates_data, 50, $months_ahead);
            
            // Safety check: If we get too many dates, something might be wrong
            if (count($expanded_dates) > 50) {
                error_log('TCC Whats On Warning: Post ID ' . $post_id . ' generated ' . count($expanded_dates) . ' dates');
                // Limit to first 50 dates as a safety measure
                $expanded_dates = array_slice($expanded_dates, 0, 50);
            }
            
            // Create cards for each date
            foreach ($expanded_dates as $date) {
                if ($date instanceof DateTime) {
                    $card_html = $this->render_card(
                        $alternate_title ?: get_the_title(),
                        $date,
                        $start_time,
                        $end_time,
                        $alt_end_time_label,
                        $featured_image_url,
                        $venue_classes,
                        $post_content
                    );
                    $cards_html[] = $card_html;
                }
            }
        }
        
        wp_reset_postdata();
        
        // Create array with sorting data for better performance and accuracy
        $cards_with_sort_data = [];
        foreach ($cards_html as $card_html) {
            // Extract data attributes for sorting
            preg_match('/data-date="([^"]*)"/', $card_html, $date_matches);
            preg_match('/data-start-time="([^"]*)"/', $card_html, $time_matches);
            
            $date = isset($date_matches[1]) ? $date_matches[1] : '1900-01-01';
            $time = isset($time_matches[1]) ? $time_matches[1] : '00:00';
            
            // Convert time to 24-hour format for proper sorting
            $sort_time = $this->convertTo24Hour($time);
            
            $cards_with_sort_data[] = [
                'html' => $card_html,
                'date' => $date,
                'time' => $sort_time,
                'datetime' => $date . ' ' . $sort_time // Combined for easy sorting
            ];
        }
        
        // Sort by combined datetime
        usort($cards_with_sort_data, function($a, $b) {
            return strcmp($a['datetime'], $b['datetime']);
        });
        
        // Extract just the HTML in sorted order
        $cards_html = array_map(function($item) {
            return $item['html'];
        }, $cards_with_sort_data);
        
        // Build output HTML
        $output = '<div class="tcc-gig-guide">';
        
        // Filter buttons
        $output .= '<div class="tcc-gig-filters">';
        
        // Venue filters
        $output .= '<div class="tcc-filter-group tcc-venue-filters">';
        $output .= '<button class="tcc-filter-btn active" data-filter="all" data-filter-type="venue">Show All</button>';
        $output .= '<button class="tcc-filter-btn" data-filter="venue-flicks" data-filter-type="venue">Flick\'s</button>';
        $output .= '<button class="tcc-filter-btn" data-filter="venue-sweethearts" data-filter-type="venue">Sweethearts</button>';
        $output .= '</div>';
        
        // Date filters
        $output .= '<div class="tcc-filter-group tcc-date-filters">';
        $output .= '<button class="tcc-filter-btn active" data-filter="all-dates" data-filter-type="date">All Dates</button>';
        $output .= '<button class="tcc-filter-btn" data-filter="this-week" data-filter-type="date">This Week</button>';
        $output .= '<button class="tcc-filter-btn" data-filter="next-week" data-filter-type="date">Next Week</button>';
        $output .= '<button class="tcc-filter-btn" data-filter="this-month" data-filter-type="date">This Month</button>';
        $output .= '</div>';
        
        // View toggle
        $output .= '<div class="tcc-filter-group tcc-view-toggle">';
        $output .= '<button class="tcc-view-btn active" data-view="grid">Grid View</button>';
        $output .= '<button class="tcc-view-btn" data-view="list">List View</button>';
        $output .= '</div>';
        
        $output .= '</div>';
        
        // Cards grid
        $output .= '<div class="tcc-gig-grid">';
        if (empty($cards_html)) {
            $output .= '<p>No upcoming events found.</p>';
        } else {
            $output .= implode('', $cards_html);
        }
        $output .= '</div>';
        
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Render individual card with flip functionality
     */
    private function render_card($title, $date, $start_time, $end_time, $alt_end_time_label, $featured_image_url, $venue_classes, $post_content = '') {
        $venue_class_string = !empty($venue_classes) ? ' ' . implode(' ', $venue_classes) : '';
        $date_string = $date->format('Y-m-d');
        $display_date = $date->format('D, M j, Y');
        
        // Determine venue labels for overlay and list
        $venue_labels = [];
        $venue_label = ''; // For backward compatibility with overlay
        
        if (!empty($venue_classes)) {
            // Collect all venue labels
            if (in_array('venue-flicks', $venue_classes)) {
                $venue_labels[] = "Flick's";
            }
            if (in_array('venue-sweethearts', $venue_classes)) {
                $venue_labels[] = 'Sweethearts';
            }
            
            // For grid view overlay, show combined label or prioritize
            if (count($venue_labels) > 1) {
                $venue_label = implode(' & ', $venue_labels); // "Flick's & Sweethearts"
            } else if (!empty($venue_labels)) {
                $venue_label = $venue_labels[0]; // Single venue
            }
        }
        
        // Determine end time display
        $end_time_display = '';
        if (!empty($end_time)) {
            $end_time_display = $end_time;
        } elseif (!empty($alt_end_time_label)) {
            $end_time_display = $alt_end_time_label;
        }
        
        // Check if card has content for flipping
        $has_content = !empty(trim(strip_tags($post_content)));
        $flip_class = $has_content ? ' tcc-card-flippable' : '';
        
        // Prepare start time for sorting (convert to 24-hour format for proper sorting)
        $start_time_sort = !empty($start_time) ? $start_time : '00:00';
        
        $card_html = '<div class="tcc-gig-card' . $venue_class_string . $flip_class . '" data-date="' . esc_attr($date_string) . '" data-start-time="' . esc_attr($start_time_sort) . '">';
        
        // Flip container
        $card_html .= '<div class="tcc-card-flip-container">';
        
        // FRONT SIDE
        $card_html .= '<div class="tcc-card-front">';
        
        // Featured image with venue label overlay
        if ($featured_image_url) {
            $card_html .= '<div class="tcc-card-image">';
            $card_html .= '<img src="' . esc_url($featured_image_url) . '" alt="' . esc_attr($title) . '" class="tcc-card-img" />';
            if (!empty($venue_label)) {
                $card_html .= '<div class="tcc-venue-label">' . esc_html($venue_label) . '</div>';
            }
            $card_html .= '</div>';
        } else {
            $card_html .= '<div class="tcc-card-image tcc-card-no-image">';
            if (!empty($venue_label)) {
                $card_html .= '<div class="tcc-venue-label">' . esc_html($venue_label) . '</div>';
            }
            $card_html .= '</div>';
        }
        
        // Front card content
        $card_html .= '<div class="tcc-card-content">';
        
        // Header row for list view (title + date/time)
        $card_html .= '<div class="tcc-card-header">';
        $card_html .= '<h2 class="tcc-card-title">' . esc_html($title) . '</h2>';
        
        // Meta information (date and time)
        $card_html .= '<div class="tcc-card-meta">';
        
        // Add venue labels for list view (show all venues)
        if (!empty($venue_labels)) {
            foreach ($venue_labels as $label) {
                $venue_class = '';
                if ($label === "Flick's") {
                    $venue_class = ' tcc-flicks-label';
                } elseif ($label === 'Sweethearts') {
                    $venue_class = ' tcc-sweethearts-label';
                }
                $card_html .= '<div class="tcc-venue-label-list' . $venue_class . '">' . esc_html($label) . '</div>';
            }
        }
        
        // Add info icon next to date if card has content
        $info_icon = '';
        if ($has_content) {
            $info_icon = '<span class="tcc-info-icon" title="Click to read more">' .
                '<svg width="24" height="24" viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg">' .
                '<circle cx="256" cy="256" r="256" fill="currentColor" class="tcc-icon-circle"/>' .
                '<path d="M256,214.33c-11.046,0-20,8.954-20,20v128.793c0,11.046,8.954,20,20,20s20-8.955,20-20.001V234.33C276,223.284,267.046,214.33,256,214.33z" fill="white"/>' .
                '<circle cx="256" cy="162.84" r="27" fill="white"/>' .
                '</svg>' .
                '</span>';
        }
        $card_html .= '<div class="tcc-card-date">' . esc_html($display_date) . $info_icon . '</div>';
        
        if (!empty($start_time) || !empty($end_time_display)) {
            $card_html .= '<div class="tcc-card-time">';
            
            if (!empty($start_time)) {
                $card_html .= '<span class="tcc-start-time">' . esc_html($start_time) . '</span>';
                
                if (!empty($end_time_display)) {
                    $card_html .= ' - <span class="tcc-end-time">' . esc_html($end_time_display) . '</span>';
                }
            } else if (!empty($end_time_display)) {
                // Show only end time/label when no start time
                $card_html .= '<span class="tcc-end-time">' . esc_html($end_time_display) . '</span>';
            }
            
            $card_html .= '</div>';
        }
        
        $card_html .= '</div>'; // .tcc-card-meta
        $card_html .= '</div>'; // .tcc-card-header
        
        // Content area for list view
        if ($has_content) {
            $card_html .= '<div class="tcc-card-list-content">' . $post_content . '</div>';
        }
        
        
        $card_html .= '</div>'; // .tcc-card-content
        $card_html .= '</div>'; // .tcc-card-front
        
        // BACK SIDE (only if there's content)
        if ($has_content) {
            $card_html .= '<div class="tcc-card-back">';
            
            // Back content header
            $card_html .= '<div class="tcc-card-back-header">';
            $card_html .= '<div>';
            $card_html .= '<h3 class="tcc-card-back-title">' . esc_html($title) . '</h3>';
            $card_html .= '<div class="tcc-card-back-date">' . esc_html($display_date);
            if (!empty($start_time)) {
                $card_html .= ' • ' . esc_html($start_time);
                if (!empty($end_time_display)) {
                    $card_html .= ' - ' . esc_html($end_time_display);
                }
            } else if (!empty($end_time_display)) {
                // Show only end time/label when no start time on back side
                $card_html .= ' • ' . esc_html($end_time_display);
            }
            $card_html .= '</div>';
            $card_html .= '</div>';
            $card_html .= '<button class="tcc-back-btn" title="Go back">' .
                '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">' .
                '<path d="M10 4L6 8L10 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>' .
                '</svg>' .
                '</button>';
            $card_html .= '</div>';
            
            // Main content area with proper padding
            $card_html .= '<div class="tcc-card-back-content">';
            $card_html .= '<div class="tcc-card-back-body" style="padding: 20px;">' . $post_content . '</div>';
            $card_html .= '</div>';
            
            $card_html .= '</div>'; // .tcc-card-back
        }
        
        $card_html .= '</div>'; // .tcc-card-flip-container
        $card_html .= '</div>'; // .tcc-gig-card
        
        return $card_html;
    }
    
    /**
     * Convert time to 24-hour format for sorting
     * 
     * @param string $time Time string (e.g., '8:30 PM', '13:45', '8:30pm')
     * @return string Time in 24-hour format (HH:MM)
     */
    private function convertTo24Hour($time) {
        if (empty($time)) {
            return '00:00';
        }
        
        // Clean up the time string
        $time = trim($time);
        
        // Check if it's already in 24-hour format (no AM/PM)
        if (!preg_match('/[ap]m/i', $time)) {
            // Already 24-hour format or just numbers
            // Ensure it has colon and is properly formatted
            if (preg_match('/^\d{1,2}:\d{2}$/', $time)) {
                return str_pad($time, 5, '0', STR_PAD_LEFT);
            }
            
            // Handle cases like '8' or '14' (assume these are hours)
            if (preg_match('/^\d{1,2}$/', $time)) {
                return str_pad($time, 2, '0', STR_PAD_LEFT) . ':00';
            }
            
            // Return as-is if it looks like 24-hour format
            return $time;
        }
        
        // Handle AM/PM format
        $time = strtolower($time);
        $time = str_replace(' ', '', $time); // Remove spaces
        
        // Extract hour, minute, and am/pm
        if (preg_match('/^(\d{1,2})(?::(\d{2}))?\s*([ap]m)$/', $time, $matches)) {
            $hour = (int)$matches[1];
            $minute = isset($matches[2]) ? (int)$matches[2] : 0;
            $period = $matches[3];
            
            // Convert to 24-hour format
            if ($period === 'pm' && $hour !== 12) {
                $hour += 12;
            } elseif ($period === 'am' && $hour === 12) {
                $hour = 0;
            }
            
            return sprintf('%02d:%02d', $hour, $minute);
        }
        
        // Fallback: return original time
        return $time;
    }
}
