<?php

if (!defined('ABSPATH')) {
    exit;
}

class acf_field_multidate_picker extends acf_field {
    
    public function __construct() {
        $this->name = 'multidate_picker';
        $this->label = __('Multi Date Picker', 'acf-multidate-picker');
        $this->category = 'choice';
        $this->defaults = array(
            'date_format' => 'Y-m-d',
            'display_format' => 'F j, Y',
            'first_day' => 0,
            'min_date' => '',
            'max_date' => '',
        );
        
        parent::__construct();
    }
    
    public function render_field_settings($field) {
        // Date format for storage
        acf_render_field_setting($field, array(
            'label' => __('Date Format (Storage)', 'acf-multidate-picker'),
            'instructions' => __('Format for storing dates in database. Uses PHP date() format.', 'acf-multidate-picker'),
            'type' => 'text',
            'name' => 'date_format',
            'placeholder' => 'Y-m-d',
            'append' => '<a href="https://www.php.net/manual/en/datetime.format.php" target="_blank">PHP date formats</a>',
        ));
        
        // Display format
        acf_render_field_setting($field, array(
            'label' => __('Display Format', 'acf-multidate-picker'),
            'instructions' => __('Format for displaying dates in the picker. Uses PHP date() format.', 'acf-multidate-picker'),
            'type' => 'text',
            'name' => 'display_format',
            'placeholder' => 'F j, Y',
        ));
        
        // First day of week
        acf_render_field_setting($field, array(
            'label' => __('First Day of Week', 'acf-multidate-picker'),
            'instructions' => __('Which day should the calendar start on?', 'acf-multidate-picker'),
            'type' => 'select',
            'name' => 'first_day',
            'choices' => array(
                0 => __('Sunday', 'acf-multidate-picker'),
                1 => __('Monday', 'acf-multidate-picker'),
            ),
        ));
        
        // Minimum date
        acf_render_field_setting($field, array(
            'label' => __('Minimum Date', 'acf-multidate-picker'),
            'instructions' => __('Prevent selection of dates before this date. Leave empty for no restriction.', 'acf-multidate-picker'),
            'type' => 'text',
            'name' => 'min_date',
            'placeholder' => 'today',
        ));
        
        // Maximum date
        acf_render_field_setting($field, array(
            'label' => __('Maximum Date', 'acf-multidate-picker'),
            'instructions' => __('Prevent selection of dates after this date. Leave empty for no restriction.', 'acf-multidate-picker'),
            'type' => 'text',
            'name' => 'max_date',
            'placeholder' => '+1 year',
        ));
    }
    
    public function render_field($field) {
        // Get field values
        $value = is_array($field['value']) ? $field['value'] : array();
        
        // Convert PHP date format to Flatpickr format
        $flatpickr_format = $this->convert_php_to_flatpickr_format($field['display_format']);
        
        // Prepare field attributes
        $atts = array(
            'id' => $field['id'],
            'class' => 'acf-multidate-picker-input',
            'name' => $field['name'],
            'value' => '',
            'data-date-format' => esc_attr($field['date_format']),
            'data-display-format' => esc_attr($flatpickr_format),
            'data-first-day' => esc_attr($field['first_day']),
            'data-min-date' => esc_attr($field['min_date']),
            'data-max-date' => esc_attr($field['max_date']),
            'data-dates' => esc_attr(json_encode($value)),
        );
        
        ?>
        <div class="acf-multidate-picker-wrapper">
            <input type="text" <?php echo acf_esc_atts($atts); ?> readonly />
            <input type="hidden" name="<?php echo esc_attr($field['name']); ?>" class="acf-multidate-picker-hidden" value="<?php echo esc_attr(json_encode($value)); ?>" />
            <div class="acf-multidate-picker-selected">
                <strong><?php _e('Selected Dates:', 'acf-multidate-picker'); ?></strong>
                <ul class="acf-multidate-picker-list">
                    <?php if (!empty($value)): ?>
                        <?php foreach ($value as $date): ?>
                            <li>
                                <span class="date-value"><?php echo esc_html($this->format_date_for_display($date, $field['date_format'], $field['display_format'])); ?></span>
                                <button type="button" class="acf-multidate-remove-date" data-date="<?php echo esc_attr($date); ?>">&times;</button>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li class="no-dates"><?php _e('No dates selected', 'acf-multidate-picker'); ?></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
        <?php
    }
    
    public function input_admin_enqueue_scripts() {
        $dir = TCC_GG_ENHANCED_URL;
        $version = TCC_GG_ENHANCED_VERSION;
        
        // Flatpickr CSS
        wp_enqueue_style(
            'flatpickr',
            $dir . 'assets/flatpickr/flatpickr.min.css',
            array(),
            '4.6.13'
        );
        
        // Flatpickr JS
        wp_enqueue_script(
            'flatpickr',
            $dir . 'assets/flatpickr/flatpickr.min.js',
            array(),
            '4.6.13',
            true
        );
        
        // Custom CSS
        wp_enqueue_style(
            'acf-multidate-picker',
            $dir . 'assets/css/multidate-picker.css',
            array('flatpickr'),
            $version
        );
        
        // Custom JS
        wp_enqueue_script(
            'acf-multidate-picker',
            $dir . 'assets/js/multidate-picker.js',
            array('jquery', 'flatpickr', 'acf-input'),
            $version,
            true
        );
    }
    
    public function format_value($value, $post_id, $field) {
        // Return empty array if no value
        if (empty($value) || !is_array($value)) {
            return array();
        }
        
        // Return array of dates
        return $value;
    }
    
    public function update_value($value, $post_id, $field) {
        // Decode JSON if it's a string
        if (is_string($value)) {
            $value = json_decode($value, true);
        }
        
        // Ensure it's an array
        if (!is_array($value)) {
            return array();
        }
        
        // Remove empty values and sort
        $value = array_filter($value);
        sort($value);
        
        return $value;
    }
    
    private function convert_php_to_flatpickr_format($php_format) {
        $replacements = array(
            // Day
            'd' => 'd',     // Day of month with leading zeros
            'j' => 'j',     // Day of month without leading zeros
            'D' => 'D',     // Short day name
            'l' => 'l',     // Full day name
            // Month
            'm' => 'm',     // Month with leading zeros
            'n' => 'n',     // Month without leading zeros
            'M' => 'M',     // Short month name
            'F' => 'F',     // Full month name
            // Year
            'Y' => 'Y',     // Four digit year
            'y' => 'y',     // Two digit year
        );
        
        return strtr($php_format, $replacements);
    }
    
    private function format_date_for_display($date, $storage_format, $display_format) {
        try {
            $date_obj = DateTime::createFromFormat($storage_format, $date);
            if ($date_obj) {
                return $date_obj->format($display_format);
            }
        } catch (Exception $e) {
            // Fallback to original date
        }
        
        return $date;
    }
}

// Register field type
add_action('acf/include_field_types', function() {
    new acf_field_multidate_picker();
});
