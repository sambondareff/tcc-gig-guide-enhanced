<?php

if (!defined('ABSPATH')) {
    exit;
}

class TCC_Gig_Guide_Multidate_Handler {
    
    /**
     * Convert manual date array to DateTime objects
     * 
     * @param array $dates_data Array of date strings from ACF multidate picker
     * @param int $months_ahead Maximum months to look ahead (for consistency with RRULE)
     * @return array Array of DateTime objects
     */
    public static function expand_dates($dates_data, $months_ahead = 3) {
        if (empty($dates_data) || !is_array($dates_data)) {
            return [];
        }
        
        $date_objects = [];
        $today = new DateTime();
        $today->setTime(0, 0, 0);
        
        // Create time window limit
        $time_window_limit = clone $today;
        $time_window_limit->modify("+{$months_ahead} months");
        
        foreach ($dates_data as $date_string) {
            if (empty($date_string)) {
                continue;
            }
            
            try {
                // Try to parse the date string
                $date = self::parse_date($date_string);
                
                if ($date) {
                    $date_normalized = clone $date;
                    $date_normalized->setTime(0, 0, 0);
                    
                    // Only include dates that are:
                    // 1. Today or in the future
                    // 2. Within the time window
                    if ($date_normalized >= $today && $date_normalized <= $time_window_limit) {
                        $date_objects[] = $date;
                    }
                }
            } catch (Exception $e) {
                error_log('TCC Gig Guide Enhanced: Error parsing date - ' . $e->getMessage());
            }
        }
        
        // Sort dates chronologically
        usort($date_objects, function($a, $b) {
            return $a <=> $b;
        });
        
        return $date_objects;
    }
    
    /**
     * Parse a date string in various formats
     * 
     * @param string $date_string The date string to parse
     * @return DateTime|null DateTime object or null if parsing fails
     */
    private static function parse_date($date_string) {
        // Common date formats to try
        $formats = [
            'Y-m-d',         // 2025-12-31
            'm/d/Y',         // 12/31/2025
            'd/m/Y',         // 31/12/2025
            'Y/m/d',         // 2025/12/31
            'd-m-Y',         // 31-12-2025
            'M j, Y',        // Dec 31, 2025
            'F j, Y',        // December 31, 2025
        ];
        
        foreach ($formats as $format) {
            $date = DateTime::createFromFormat($format, $date_string);
            if ($date !== false) {
                // Validate the date is actually valid (catches cases like Feb 30)
                $errors = DateTime::getLastErrors();
                if ($errors['warning_count'] === 0 && $errors['error_count'] === 0) {
                    return $date;
                }
            }
        }
        
        // Fallback: try strtotime
        $timestamp = strtotime($date_string);
        if ($timestamp !== false) {
            $date = new DateTime();
            $date->setTimestamp($timestamp);
            return $date;
        }
        
        return null;
    }
    
    /**
     * Validate date array
     * 
     * @param array $dates_data Array of date strings
     * @return bool True if valid, false otherwise
     */
    public static function validate_dates($dates_data) {
        if (!is_array($dates_data)) {
            return false;
        }
        
        if (empty($dates_data)) {
            return true; // Empty array is valid
        }
        
        // Check if at least one date can be parsed
        foreach ($dates_data as $date_string) {
            if (self::parse_date($date_string) !== null) {
                return true;
            }
        }
        
        return false;
    }
}
