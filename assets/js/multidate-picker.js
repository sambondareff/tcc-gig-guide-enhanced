(function($) {
    
    if (typeof acf === 'undefined') {
        return;
    }
    
    let fieldInstances = {};
    
    function initializeField($field) {
        // Ensure $field is a jQuery object
        $field = $($field);
        
        let $input = $field.find('.acf-multidate-picker-input');
        let $hidden = $field.find('.acf-multidate-picker-hidden');
        let $list = $field.find('.acf-multidate-picker-list');
        
        // Try searching in the entire document as a fallback
        if (!$input.length) {
            $input = $('.acf-multidate-picker-input');
            $hidden = $('.acf-multidate-picker-hidden');
            $list = $('.acf-multidate-picker-list');
        }
        
        if (!$input.length) {
            return;
        }
        
        let fieldId = $input.attr('id');
        
        // Destroy existing instance if it exists
        if (fieldInstances[fieldId]) {
            fieldInstances[fieldId].destroy();
            delete fieldInstances[fieldId];
        }
        
        // Get configuration from data attributes
        let dateFormat = $input.data('date-format') || 'Y-m-d';
        let displayFormat = $input.data('display-format') || 'F j, Y';
        let firstDay = parseInt($input.data('first-day')) || 0;
        let minDate = $input.data('min-date') || null;
        let maxDate = $input.data('max-date') || null;
        let selectedDates = $input.data('dates') || [];
        
        // Ensure selectedDates is an array
        if (typeof selectedDates === 'string') {
            try {
                selectedDates = JSON.parse(selectedDates);
            } catch (e) {
                selectedDates = [];
            }
        }
        
        if (!Array.isArray(selectedDates)) {
            selectedDates = [];
        }
        
        // Convert stored dates to Date objects for Flatpickr
        let selectedDateObjects = selectedDates.map(function(dateStr) {
            return parseDateString(dateStr, dateFormat);
        }).filter(function(date) {
            return date !== null;
        });
        
        // Initialize Flatpickr
        let flatpickrInstance = flatpickr($input[0], {
            mode: 'multiple',
            dateFormat: displayFormat,
            locale: {
                firstDayOfWeek: firstDay
            },
            minDate: minDate || null,
            maxDate: maxDate || null,
            defaultDate: selectedDateObjects,
            onChange: function(selectedDatesObj, dateStr, instance) {
                updateSelectedDates(selectedDatesObj, dateFormat, displayFormat, $hidden, $list);
            },
            onReady: function(selectedDatesObj, dateStr, instance) {
                // Style the calendar
                instance.calendarContainer.classList.add('acf-multidate-picker-calendar');
            }
        });
        
        // Re-enable the input in case ACF disabled it
        $input.prop('disabled', false);
        
        fieldInstances[fieldId] = flatpickrInstance;
        
        // Handle remove date button clicks - use event delegation from the wrapper
        let $wrapper = $input.closest('.acf-multidate-picker-wrapper');
        if (!$wrapper.length) {
            $wrapper = $('.acf-multidate-picker-wrapper');
        }
        
        // Remove any existing handlers to avoid duplicates
        $wrapper.off('click.multidate');
        
        $wrapper.on('click.multidate', '.acf-multidate-remove-date', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            let dateToRemove = $(this).data('date');
            let currentDates = JSON.parse($hidden.val() || '[]');
            let newDates = currentDates.filter(function(date) {
                return date !== dateToRemove;
            });
            
            // Update hidden field
            $hidden.val(JSON.stringify(newDates));
            
            // Convert to Date objects and update Flatpickr
            let dateObjects = newDates.map(function(dateStr) {
                return parseDateString(dateStr, dateFormat);
            }).filter(function(date) {
                return date !== null;
            });
            
            flatpickrInstance.setDate(dateObjects, false);
            
            // Update display
            updateSelectedDates(dateObjects, dateFormat, displayFormat, $hidden, $list);
        });
    }
    
    function updateSelectedDates(selectedDatesObj, dateFormat, displayFormat, $hidden, $list) {
        // Convert Date objects to formatted strings
        let formattedDates = selectedDatesObj.map(function(date) {
            return formatDate(date, dateFormat);
        });
        
        // Sort dates
        formattedDates.sort();
        
        // Update hidden field
        $hidden.val(JSON.stringify(formattedDates));
        
        // Update display list
        $list.empty();
        
        if (formattedDates.length === 0) {
            $list.append('<li class="no-dates">No dates selected</li>');
        } else {
            formattedDates.forEach(function(dateStr) {
                let displayDate = formatDateForDisplay(dateStr, dateFormat, displayFormat);
                let $li = $('<li></li>');
                let $span = $('<span class="date-value"></span>').text(displayDate);
                let $btn = $('<button type="button" class="acf-multidate-remove-date">&times;</button>').data('date', dateStr);
                $li.append($span).append($btn);
                $list.append($li);
            });
        }
    }
    
    function formatDate(date, format) {
        let d = new Date(date);
        let year = d.getFullYear();
        let month = d.getMonth() + 1;
        let day = d.getDate();
        
        let result = format;
        result = result.replace('Y', year);
        result = result.replace('y', year.toString().substr(-2));
        result = result.replace('m', ('0' + month).slice(-2));
        result = result.replace('n', month);
        result = result.replace('d', ('0' + day).slice(-2));
        result = result.replace('j', day);
        
        return result;
    }
    
    function formatDateForDisplay(dateStr, storageFormat, displayFormat) {
        let date = parseDateString(dateStr, storageFormat);
        if (!date) {
            return dateStr;
        }
        
        let year = date.getFullYear();
        let month = date.getMonth() + 1;
        let day = date.getDate();
        
        let monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                         'July', 'August', 'September', 'October', 'November', 'December'];
        let monthNamesShort = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
                              'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        let dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        let dayNamesShort = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        
        // Build replacement map
        let replacements = {
            'F': monthNames[month - 1],
            'M': monthNamesShort[month - 1],
            'l': dayNames[date.getDay()],
            'D': dayNamesShort[date.getDay()],
            'Y': year.toString(),
            'y': year.toString().substr(-2),
            'm': ('0' + month).slice(-2),
            'n': month.toString(),
            'd': ('0' + day).slice(-2),
            'j': day.toString()
        };
        
        // Replace format characters one at a time, in a single pass
        let result = '';
        for (let i = 0; i < displayFormat.length; i++) {
            let char = displayFormat[i];
            if (replacements.hasOwnProperty(char)) {
                result += replacements[char];
            } else {
                result += char;
            }
        }
        
        return result;
    }
    
    function parseDateString(dateStr, format) {
        if (!dateStr) {
            return null;
        }
        
        let year, month, day;
        
        // Simple parser for common formats
        if (format === 'Y-m-d') {
            let parts = dateStr.split('-');
            if (parts.length === 3) {
                year = parseInt(parts[0]);
                month = parseInt(parts[1]) - 1;
                day = parseInt(parts[2]);
            }
        } else if (format === 'm/d/Y') {
            let parts = dateStr.split('/');
            if (parts.length === 3) {
                month = parseInt(parts[0]) - 1;
                day = parseInt(parts[1]);
                year = parseInt(parts[2]);
            }
        } else if (format === 'd/m/Y') {
            let parts = dateStr.split('/');
            if (parts.length === 3) {
                day = parseInt(parts[0]);
                month = parseInt(parts[1]) - 1;
                year = parseInt(parts[2]);
            }
        } else {
            // Fallback: try to parse as ISO date
            let date = new Date(dateStr);
            if (!isNaN(date.getTime())) {
                return date;
            }
            return null;
        }
        
        if (year && month !== undefined && day) {
            return new Date(year, month, day);
        }
        
        return null;
    }
    
    // ACF field initialization
    if (typeof acf.addAction === 'function') {
        acf.addAction('ready_field/type=multidate_picker', function($field) {
            initializeField($field);
        });
        
        acf.addAction('append_field/type=multidate_picker', function($field) {
            initializeField($field);
        });
        
        // Handle conditional logic showing the field
        acf.addAction('show_field/type=multidate_picker', function($field) {
            initializeField($field);
        });
    }
    
})(jQuery);
