<?php

if (!defined('ABSPATH')) {
    exit;
}

class TCC_Gig_Guide_RRule {
    
    /**
     * Expand an RRULE string to individual dates
     * 
     * @param string $rrule_data The RRULE data from ACF
     * @param int $limit Maximum number of dates to return (default 50)
     * @param int $months_ahead Maximum months to look ahead (default 3)
     * @return array Array of DateTime objects
     */
    public static function expand_dates($rrule_data, $limit = 50, $months_ahead = 3) {
        if (empty($rrule_data)) {
            return [];
        }

        // ACF RRULE field returns an array with multiple keys
        if (is_array($rrule_data)) {
            // Extract data directly from ACF RRULE array structure
            $start_date_str = isset($rrule_data['start_date']) ? $rrule_data['start_date'] : '';
            $frequency = isset($rrule_data['frequency']) ? $rrule_data['frequency'] : '';
            $interval = isset($rrule_data['interval']) ? (int)$rrule_data['interval'] : 1;
            $weekdays = isset($rrule_data['weekdays']) ? $rrule_data['weekdays'] : [];
            $end_type = isset($rrule_data['end_type']) ? $rrule_data['end_type'] : '';
            $end_date = isset($rrule_data['end_date']) ? $rrule_data['end_date'] : '';
            $occurrence_count = isset($rrule_data['occurrence_count']) ? (int)$rrule_data['occurrence_count'] : null;
            
            // Debug logging removed for production
        } else {
            // Fallback to string parsing
            return self::parse_traditional_rrule($rrule_data, $limit, $months_ahead);
        }

        if (empty($start_date_str)) {
            return [];
        }

        $dates = [];
        
        try {
            $start_date = new DateTime($start_date_str);
            
            // Create a time window limit (default 3 months from today)
            $today = new DateTime();
            $today->setTime(0, 0, 0); // Start of today for fair comparison
            $time_window_limit = clone $today;
            $time_window_limit->modify("+{$months_ahead} months");
            
            // Only include the start date if it's within our time window and not in the past
            $start_date_normalized = clone $start_date;
            $start_date_normalized->setTime(0, 0, 0);
            
            if ($start_date_normalized >= $today && $start_date_normalized <= $time_window_limit) {
                $dates[] = clone $start_date;
            }
            
            // If no frequency is specified, return just the start date (if it's valid)
            if (empty($frequency) || $frequency === 'once') {
                return $start_date_normalized >= $today ? $dates : [];
            }

            // Handle end conditions
            $until = null;
            $count = null;
            
            if ($end_type === 'date' && !empty($end_date)) {
                $until = new DateTime($end_date);
            } elseif ($end_type === 'count' && !empty($occurrence_count)) {
                $count = $occurrence_count;
            }
            
            // Use the most restrictive end date
            $effective_until = $until;
            if (!$effective_until || $effective_until > $time_window_limit) {
                $effective_until = $time_window_limit;
            }

            $current_date = clone $start_date;
            $iterations = 0;
            $max_iterations = $count ? min($count - 1, $limit - 1) : $limit - 1;

            while ($iterations < $max_iterations) {
                $iterations++;
                
                switch (strtoupper($frequency)) {
                    case 'DAILY':
                        $current_date->modify("+{$interval} days");
                        break;
                    case 'WEEKLY':
                        if (!empty($weekdays)) {
                            // Handle specific weekdays
                            $current_date = self::find_next_weekday_from_array($current_date, $weekdays, $interval);
                        } else {
                            $current_date->modify("+{$interval} weeks");
                        }
                        break;
                    case 'MONTHLY':
                        $current_date->modify("+{$interval} months");
                        break;
                    case 'YEARLY':
                        $current_date->modify("+{$interval} years");
                        break;
                    default:
                        break 2; // Break out of while loop
                }

                // Check both original UNTIL constraint and time window limit
                if (($until && $current_date > $until) || $current_date > $effective_until) {
                    break;
                }
                
                // Only add dates that are in the future (or today) and within time window
                $current_date_normalized = clone $current_date;
                $current_date_normalized->setTime(0, 0, 0);
                
                if ($current_date_normalized >= $today) {
                    $dates[] = clone $current_date;
                }
            }

        } catch (Exception $e) {
            error_log('TCC Gig Guide RRULE Error: ' . $e->getMessage());
        }

        return $dates;
    }

    /**
     * Parse RRULE string into components
     */
    private static function parse_rrule($rrule_string) {
        $parts = [];
        $lines = explode("\n", $rrule_string);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (strpos($line, 'DTSTART:') === 0) {
                $parts['DTSTART'] = substr($line, 8);
            } elseif (strpos($line, 'RRULE:') === 0) {
                $rrule_content = substr($line, 6);
                $rrule_pairs = explode(';', $rrule_content);
                
                foreach ($rrule_pairs as $pair) {
                    if (strpos($pair, '=') !== false) {
                        list($key, $value) = explode('=', $pair, 2);
                        $parts[trim($key)] = trim($value);
                    }
                }
            }
        }
        
        return $parts;
    }

    /**
     * Parse BYDAY parameter
     */
    private static function parse_byday($byday) {
        $day_map = [
            'MO' => 1, 'TU' => 2, 'WE' => 3, 'TH' => 4, 
            'FR' => 5, 'SA' => 6, 'SU' => 0
        ];
        
        // Handle simple cases like "MO", "TU", etc.
        $byday = strtoupper(trim($byday));
        return isset($day_map[$byday]) ? $day_map[$byday] : null;
    }

    /**
     * Find next occurrence of a specific weekday
     */
    private static function find_next_weekday($start_date, $target_day) {
        $current = clone $start_date;
        $current_day = (int)$current->format('w'); // 0 = Sunday, 6 = Saturday
        
        if ($current_day === $target_day) {
            return $current;
        }
        
        $days_ahead = $target_day - $current_day;
        if ($days_ahead < 0) {
            $days_ahead += 7;
        }
        
        $current->modify("+{$days_ahead} days");
        return $current;
    }
    
    /**
     * Find next weekday from ACF weekdays array
     */
    private static function find_next_weekday_from_array($current_date, $weekdays, $interval) {
        if (empty($weekdays)) {
            // Fallback to weekly interval
            $new_date = clone $current_date;
            $new_date->modify("+{$interval} weeks");
            return $new_date;
        }
        
        // Convert ACF weekday format to PHP weekday numbers
        $target_days = [];
        foreach ($weekdays as $day) {
            $day_number = self::acf_weekday_to_number($day);
            if ($day_number !== null) {
                $target_days[] = $day_number;
            }
        }
        
        if (empty($target_days)) {
            // Fallback if no valid weekdays
            $new_date = clone $current_date;
            $new_date->modify("+{$interval} weeks");
            return $new_date;
        }
        
        // Find next occurrence
        $new_date = clone $current_date;
        $new_date->modify('+1 day'); // Start from next day
        
        // Look for next occurrence within reasonable range
        for ($i = 0; $i < 14; $i++) { // Look ahead 2 weeks max
            $current_weekday = (int)$new_date->format('w');
            if (in_array($current_weekday, $target_days)) {
                return $new_date;
            }
            $new_date->modify('+1 day');
        }
        
        // Fallback
        $fallback_date = clone $current_date;
        $fallback_date->modify("+{$interval} weeks");
        return $fallback_date;
    }
    
    /**
     * Convert ACF weekday to PHP weekday number
     */
    private static function acf_weekday_to_number($acf_day) {
        // ACF might use different formats, handle common ones
        $day_map = [
            'sunday' => 0, 'sun' => 0, '0' => 0,
            'monday' => 1, 'mon' => 1, '1' => 1,
            'tuesday' => 2, 'tue' => 2, '2' => 2,
            'wednesday' => 3, 'wed' => 3, '3' => 3,
            'thursday' => 4, 'thu' => 4, '4' => 4,
            'friday' => 5, 'fri' => 5, '5' => 5,
            'saturday' => 6, 'sat' => 6, '6' => 6,
        ];
        
        $acf_day_lower = strtolower($acf_day);
        return isset($day_map[$acf_day_lower]) ? $day_map[$acf_day_lower] : null;
    }
    
    /**
     * Parse traditional RRULE string (fallback method)
     */
    private static function parse_traditional_rrule($rrule_data, $limit = 50, $months_ahead = 3) {
        // This is the old parsing logic for traditional RRULE strings
        // For now, return empty array as fallback
        error_log('TCC Gig Guide: Using traditional RRULE parsing fallback');
        return [];
    }
}
