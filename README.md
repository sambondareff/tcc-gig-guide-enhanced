# TCC - Gig Guide Enhanced

A WordPress plugin that displays gig events from a custom post type with **flexible date entry options** - choose between RRULE recurring patterns or manual multi-date selection for each gig. Features responsive card grids, venue filters, list/grid views, and card flip functionality.

## 🆕 What's New in Enhanced Version

**Dual Date Entry Modes** - Choose the best option for each gig:
- **RRULE Mode**: Recurring patterns (every Tuesday, first Friday of month, etc.)
- **Manual Mode**: Click and select specific dates across multiple months

Both modes render through the same shortcode with consistent styling and filtering.

## Features

- **Flexible Date Entry**: Choose between recurring patterns or manual selection per gig
- **RRULE Support**: Recurring patterns (daily, weekly, monthly, yearly)
- **Multi-Date Picker**: Interactive Flatpickr calendar for selecting multiple specific dates
- **Responsive Grid Layout**: 1:1 aspect ratio cards with featured images
- **Advanced Filtering**:
  - Venue filters (Flick's, Sweethearts, or custom venues)
  - Date range filters (This Week, Next Week, This Month)
  - View toggle (Grid/List)
- **Card Flip**: Click cards to reveal additional content
- **Time Window Protection**: Configurable months-ahead limit
- **Animated Transitions**: GSAP-powered smooth animations

## Requirements

- WordPress 5.0 or higher
- **Advanced Custom Fields (ACF)** 5.0 or higher (Free or Pro)
- PHP 7.0 or higher
- A custom post type called "gig"
- A taxonomy called "venue-area"

## Installation

1. Upload the `tcc-gig-guide-enhanced` folder to `/wp-content/plugins/`
2. Activate the plugin through the WordPress admin
3. Ensure ACF is installed and active
4. Configure your ACF fields (see setup below)

## ACF Field Setup

Create a field group for the "gig" post type with these fields:

### Required Fields

| Field Name | Field Type | Description | Notes |
|------------|-----------|-------------|-------|
| `date_entry_mode` | Radio Button | Choose date entry method | Choices: `rrule` (Recurring Pattern), `manual` (Select Specific Dates) |
| `dates_rrule` | RRULE | Recurring date pattern | Conditional: show if `date_entry_mode` = `rrule` |
| `dates_manual` | Multi Date Picker | Manual date selection | Conditional: show if `date_entry_mode` = `manual` |
| `alternate_title` | Text | Display title (fallback to post title) | Optional |
| `start_time` | Time Picker | Event start time | Display format: `g:i a` |
| `end_time` | Time Picker | Event end time | Display format: `g:i a` |
| `alternate_end_time_label` | Text | Label when no end time (e.g., "Late") | Optional |

### Conditional Logic Setup

**dates_rrule field:**
- Show if: `date_entry_mode` equals `rrule`

**dates_manual field:**
- Show if: `date_entry_mode` equals `manual`

### Multi Date Picker Field Settings

When configuring the `dates_manual` field:
- **Date Format (Storage)**: `Y-m-d` (recommended)
- **Display Format**: `F j, Y` or your preference
- **First Day of Week**: Sunday or Monday
- **Minimum Date**: `today` (prevents past dates)
- **Maximum Date**: `+1 year` (optional limit)

## Usage

### Basic Shortcode

```
[tcc_gig_guide]
```

Shows all upcoming gigs for the next 3 months.

### Shortcode Attributes

| Attribute | Description | Default | Example Values |
|-----------|-------------|---------|----------------|
| `posts_per_page` | Number of gig posts to query | -1 (all) | `20`, `-1` |
| `months_ahead` | Months ahead to show events | 3 | `1-12` |
| `meta_key` | Filter by meta key | '' | `featured` |
| `meta_value` | Filter by meta value | '' | `yes` |

### Usage Examples

```
[tcc_gig_guide months_ahead="6"]
```

```
[tcc_gig_guide posts_per_page="20" months_ahead="4"]
```

```
[tcc_gig_guide meta_key="featured" meta_value="yes"]
```

## Date Entry Modes

### Mode 1: Recurring Pattern (RRULE)

**Best for:**
- Regular weekly events (e.g., "Every Thursday")
- Monthly events (e.g., "First Friday of each month")
- Daily events during a specific period

**How it works:**
1. Set `date_entry_mode` to "Recurring Pattern"
2. Configure the RRULE field:
   - Start date
   - Frequency (Daily, Weekly, Monthly, Yearly)
   - Interval (e.g., every 2 weeks)
   - End condition (date, count, or ongoing)
   - Days of week (for weekly)

**Example use cases:**
- Open mic every Tuesday at 7pm
- Jazz night on the 1st and 3rd Saturday
- Daily happy hour for the summer season

### Mode 2: Manual Selection

**Best for:**
- Irregular specific dates
- One-time events on multiple dates
- Special events without a pattern

**How it works:**
1. Set `date_entry_mode` to "Select Specific Dates"
2. Click dates in the calendar picker
3. Remove unwanted dates by clicking the ×  button
4. Dates are stored as an array

**Example use cases:**
- Band performing Jan 15, Feb 22, Mar 8, Apr 12
- Holiday events (Dec 24, 25, 31, Jan 1)
- Festival dates: May 10, 11, 17, 18

## Front-End Display

Both date modes render identically in the front-end. Cards show:
- Featured image (1:1 aspect ratio)
- Event title
- Date (formatted)
- Start/end time
- Venue label
- Click to flip for additional content

## Filtering

### Venue Filtering
- **Show All**: All venues
- **Flick's**: Only events at Flick's
- **Sweethearts**: Only events at Sweethearts

### Date Filtering
- **All Dates**: All upcoming dates
- **This Week**: Events in current week
- **Next Week**: Events in next 7 days
- **This Month**: Events in current month

### View Toggle
- **Grid View**: Responsive card grid
- **List View**: Compact list with expand

## Taxonomy Setup

Create a taxonomy called `venue-area` with terms:
- Flick's
- Sweethearts
- (Add custom venues as needed)

Assign venues to gigs using the WordPress taxonomy interface.

## Styling

The plugin includes comprehensive CSS. Customize by targeting:

- `.tcc-gig-guide` - Main container
- `.tcc-gig-filters` - Filter buttons
- `.tcc-gig-grid` - Card grid
- `.tcc-gig-card` - Individual cards
- `.tcc-card-image` - Image area
- `.tcc-card-content` - Card text content
- `.tcc-card-flip-container` - Flip animation container

## Debug Shortcode

Test your setup:

```
[tcc_debug_enhanced]
```

Shows diagnostic information for the first 3 gig posts.

## Migrating from Original Plugin

If upgrading from the original TCC Gig Guide:

1. **Backup your database**
2. Install and activate enhanced version
3. Your existing RRULE dates will continue to work
4. Add `date_entry_mode` field to your ACF group
5. Rename `dates` field to `dates_rrule`
6. Add `dates_manual` field
7. Existing gigs default to RRULE mode (backward compatible)

## Advanced Configuration

### Time Window Protection

The `months_ahead` attribute prevents infinite date generation:
- Default: 3 months
- Range: 1-12 months
- Applies to both RRULE and manual modes

### Date Sorting

All cards are automatically sorted by:
1. Date (chronological)
2. Start time (24-hour format)

## Troubleshooting

### No cards showing
- Verify gig posts are published
- Check ACF fields are properly configured
- Ensure dates are within the time window
- Use debug shortcode to diagnose

### Wrong dates displaying
- Check `date_entry_mode` field value
- Verify RRULE configuration or manual dates
- Check time window limit (`months_ahead`)

### Calendar picker not working
- Ensure Flatpickr assets are loading
- Check browser console for errors
- Verify ACF is active and updated

### Cards not filtering
- Check gigs have `venue-area` taxonomy assigned
- Verify JavaScript is loading
- Check browser console for errors

## Browser Support

- Modern browsers (Chrome, Firefox, Safari, Edge)
- Mobile browsers (iOS Safari, Chrome Mobile)
- Requires CSS Grid support

## Performance

- Cards are rendered server-side
- Filters use client-side JavaScript
- GSAP provides hardware-accelerated animations
- Conditional asset loading (only when shortcode present)

## Support

For issues or questions, contact Mojo Collective.

## License

GPL-2.0-or-later

## Credits

- Built on [Flatpickr](https://flatpickr.js.org/) by Gregory Petrosyan
- Designed for [Advanced Custom Fields](https://www.advancedcustomfields.com/) by Elliot Condon
- Animations powered by [GSAP](https://greensock.com/gsap/)
- Developed by Mojo Collective

## Changelog

### Version 2.0.0
- Added dual date entry mode support (RRULE + Manual)
- Integrated Flatpickr multi-date picker
- Enhanced ACF field configuration
- Maintained backward compatibility with original plugin
- Improved date handling and validation
- Updated documentation

### Version 1.0.0
- Initial release with RRULE support
