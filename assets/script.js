document.addEventListener('DOMContentLoaded', function() {
    // Wait for GSAP to be available
    if (typeof gsap === 'undefined') {
        console.warn('GSAP not loaded, falling back to basic animations');
        initTCCGigGuideBasic();
        return;
    }
    
    // Register ScrollTrigger plugin if available
    if (typeof ScrollTrigger !== 'undefined') {
        gsap.registerPlugin(ScrollTrigger);
    }
    
    // Initialize the gig guide with GSAP animations
    initTCCGigGuide();
});

// Utility function to get URL parameters
function getURLParameter(name) {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get(name);
}

// Utility function to set initial filter from URL
function getInitialVenueFilterFromURL() {
    const venueParam = getURLParameter('venue');
    if (venueParam) {
        const normalizedVenue = venueParam.toLowerCase();
        if (normalizedVenue === 'flicks') {
            return 'venue-flicks';
        } else if (normalizedVenue === 'sweethearts') {
            return 'venue-sweethearts';
        }
    }
    return 'all';
}

function initTCCGigGuide() {
    const gigGuides = document.querySelectorAll('.tcc-gig-guide');
    
    gigGuides.forEach(function(guide) {
        const filterButtons = guide.querySelectorAll('.tcc-filter-btn');
        const viewButtons = guide.querySelectorAll('.tcc-view-btn');
        const cards = guide.querySelectorAll('.tcc-gig-card');
        
        // State tracking - check URL parameters for initial venue filter
        let currentVenueFilter = getInitialVenueFilterFromURL();
        let currentDateFilter = 'all-dates';
        
        // Add click event listeners to filter buttons
        filterButtons.forEach(function(button) {
            button.addEventListener('click', function() {
                const filter = this.getAttribute('data-filter');
                const filterType = this.getAttribute('data-filter-type');
                
                // Update active state within the same filter group
                const filterGroup = this.closest('.tcc-filter-group');
                filterGroup.querySelectorAll('.tcc-filter-btn').forEach(btn => btn.classList.remove('active'));
                this.classList.add('active');
                
                // Update current filters
                if (filterType === 'venue') {
                    currentVenueFilter = filter;
                } else if (filterType === 'date') {
                    currentDateFilter = filter;
                }
                
                // Apply combined filters
                filterCardsWithGSAP(cards, currentVenueFilter, currentDateFilter);
            });
        });
        
        // Add click event listeners to view toggle buttons
        viewButtons.forEach(function(button) {
            button.addEventListener('click', function() {
                const view = this.getAttribute('data-view');
                
                // Update active state
                viewButtons.forEach(btn => btn.classList.remove('active'));
                this.classList.add('active');
                
                // Toggle view
                if (view === 'list') {
                    guide.classList.add('list-view');
                } else {
                    guide.classList.remove('list-view');
                }
            });
        });
        
        // Set initial filter button states based on URL parameter
        if (currentVenueFilter !== 'all') {
            // Remove active from "Show All" button
            const showAllBtn = guide.querySelector('.tcc-filter-btn[data-filter="all"]');
            if (showAllBtn) {
                showAllBtn.classList.remove('active');
            }
            
            // Set the correct venue button as active
            const venueBtn = guide.querySelector(`[data-filter="${currentVenueFilter}"]`);
            if (venueBtn) {
                venueBtn.classList.add('active');
            }
        }
        
        // Initialize with staggered loading animation
        initializeCardsWithGSAP(cards);
        
        // Apply initial filter if URL parameter was set
        if (currentVenueFilter !== 'all') {
            // Small delay to let cards initialize first
            setTimeout(() => {
                filterCardsWithGSAP(cards, currentVenueFilter, currentDateFilter);
            }, 100);
        }
        
        // Initialize card flip functionality
        initializeCardFlips(guide);
        
        // Set random animation delays for WebP images
        setRandomWebPDelays(guide);
    });
}

// Initialize card flip functionality
function initializeCardFlips(guide) {
    // Handle Info Icon clicks
    const infoIcons = guide.querySelectorAll('.tcc-info-icon');
    infoIcons.forEach(icon => {
        icon.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const card = this.closest('.tcc-gig-card');
            if (card) {
                card.classList.add('flipped');
                console.log('Card flipped to back via info icon:', card);
            }
        });
    });

    // Handle Back button clicks
    const backButtons = guide.querySelectorAll('.tcc-back-btn');
    backButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const card = this.closest('.tcc-gig-card');
            if (card) {
                card.classList.remove('flipped');
                console.log('Card flipped to front:', card);
            }
        });
    });

    console.log(`Initialized flip functionality for ${infoIcons.length} info icons and ${backButtons.length} back buttons`);
}

// Initialize cards with scroll-triggered stagger animations
function initializeCardsWithGSAP(cards) {
    // Set initial state for all cards
    gsap.set(cards, {
        opacity: 0,
        y: 40,
        scale: 0.95
    });
    
    // Use ScrollTrigger if available, otherwise fallback to immediate animation
    if (typeof ScrollTrigger !== 'undefined') {
        console.log('ScrollTrigger available, setting up scroll animations for', cards.length, 'cards');
        
        // Create scroll-triggered animation for each card individually
        cards.forEach((card, index) => {
            gsap.to(card, {
                duration: 0.5, // Slightly faster for snappier feel
                opacity: 1,
                y: 0,
                scale: 1,
                ease: "power2.out",
                delay: (index % 4) * 0.1, // Small delay based on position in row (assuming ~4 cards per row)
                clearProps: "transform",
                scrollTrigger: {
                    trigger: card,
                    start: "top 85%", // Trigger slightly earlier for smoother effect
                    toggleActions: "play none none reverse",
                    // markers: true, // Remove debug markers for clean experience
                    onStart: () => console.log(`Card ${index + 1} animating in`)
                }
            });
        });
    } else {
        console.warn('ScrollTrigger not available, using immediate animation');
        // Fallback to immediate animation if ScrollTrigger not available
        gsap.to(cards, {
            duration: 0.6,
            opacity: 1,
            y: 0,
            scale: 1,
            ease: "power2.out",
            stagger: 0.1,
            clearProps: "transform"
        });
    }
}

// Date utility functions
function getWeekStart(date) {
    const d = new Date(date);
    const day = d.getDay();
    const diff = d.getDate() - day; // First day is Sunday (0)
    return new Date(d.setDate(diff));
}

function getWeekEnd(date) {
    const d = new Date(date);
    const day = d.getDay();
    const diff = d.getDate() - day + 6; // Last day is Saturday (6)
    return new Date(d.setDate(diff));
}

function getMonthStart(date) {
    return new Date(date.getFullYear(), date.getMonth(), 1);
}

function getMonthEnd(date) {
    return new Date(date.getFullYear(), date.getMonth() + 1, 0);
}

function isDateInRange(cardDate, rangeStart, rangeEnd) {
    const date = new Date(cardDate);
    return date >= rangeStart && date <= rangeEnd;
}

// Convert time to 24-hour format for sorting
function convertTo24Hour(time) {
    if (!time || time === '') {
        return '00:00';
    }
    
    // Clean up the time string
    time = time.trim();
    
    // Check if it's already in 24-hour format (no AM/PM)
    if (!/[ap]m/i.test(time)) {
        // Already 24-hour format or just numbers
        // Ensure it has colon and is properly formatted
        if (/^\d{1,2}:\d{2}$/.test(time)) {
            return time.padStart(5, '0');
        }
        
        // Handle cases like '8' or '14' (assume these are hours)
        if (/^\d{1,2}$/.test(time)) {
            return time.padStart(2, '0') + ':00';
        }
        
        // Return as-is if it looks like 24-hour format
        return time;
    }
    
    // Handle AM/PM format
    time = time.toLowerCase();
    time = time.replace(' ', ''); // Remove spaces
    
    // Extract hour, minute, and am/pm
    const match = time.match(/^(\d{1,2})(?::(\d{2}))?\s*([ap]m)$/);
    if (match) {
        let hour = parseInt(match[1], 10);
        const minute = match[2] ? parseInt(match[2], 10) : 0;
        const period = match[3];
        
        // Convert to 24-hour format
        if (period === 'pm' && hour !== 12) {
            hour += 12;
        } else if (period === 'am' && hour === 12) {
            hour = 0;
        }
        
        return hour.toString().padStart(2, '0') + ':' + minute.toString().padStart(2, '0');
    }
    
    // Fallback: return original time
    return time;
}

// Filter cards with venue and date filters
function filterCardsWithGSAP(cards, venueFilter, dateFilter) {
    return new Promise((resolve) => {
        // Kill any existing animations first
        gsap.killTweensOf(cards);
        
        // Date ranges for filtering
        const today = new Date();
        const thisWeekStart = getWeekStart(today);
        const thisWeekEnd = getWeekEnd(today);
        const nextWeekStart = new Date(thisWeekStart.getTime() + 7 * 24 * 60 * 60 * 1000);
        const nextWeekEnd = new Date(thisWeekEnd.getTime() + 7 * 24 * 60 * 60 * 1000);
        const thisMonthStart = getMonthStart(today);
        const thisMonthEnd = getMonthEnd(today);
        
        // Filter logic
        const visibleCards = [];
        const hiddenCards = [];
        
        cards.forEach(function(card) {
            let shouldShow = true;
            
            // Venue filtering
            if (venueFilter !== 'all') {
                shouldShow = shouldShow && card.classList.contains(venueFilter);
            }
            
            // Date filtering
            if (dateFilter !== 'all-dates') {
                const cardDateStr = card.getAttribute('data-date');
                if (cardDateStr) {
                    const cardDate = new Date(cardDateStr);
                    
                    switch (dateFilter) {
                        case 'this-week':
                            shouldShow = shouldShow && isDateInRange(cardDate, thisWeekStart, thisWeekEnd);
                            break;
                        case 'next-week':
                            shouldShow = shouldShow && isDateInRange(cardDate, nextWeekStart, nextWeekEnd);
                            break;
                        case 'this-month':
                            shouldShow = shouldShow && isDateInRange(cardDate, thisMonthStart, thisMonthEnd);
                            break;
                    }
                }
            }
            
            if (shouldShow) {
                card.classList.remove('hidden');
                visibleCards.push(card);
            } else {
                card.classList.add('hidden');
                hiddenCards.push(card);
            }
        });
        
        // Instantly hide unwanted cards
        if (hiddenCards.length > 0) {
            gsap.set(hiddenCards, {
                opacity: 0,
                display: 'none'
            });
        }
        
        // Sort visible cards by date and time before animating
        if (visibleCards.length > 0) {
            // Sort visible cards by datetime
            visibleCards.sort(function(a, b) {
                const dateA = a.getAttribute('data-date') || '1900-01-01';
                const timeA = convertTo24Hour(a.getAttribute('data-start-time') || '00:00');
                const dateB = b.getAttribute('data-date') || '1900-01-01';
                const timeB = convertTo24Hour(b.getAttribute('data-start-time') || '00:00');
                
                const datetimeA = dateA + ' ' + timeA;
                const datetimeB = dateB + ' ' + timeB;
                
                return datetimeA.localeCompare(datetimeB);
            });
            
            // Reorder DOM elements based on sorted order
            const container = visibleCards[0].parentNode;
            visibleCards.forEach(function(card) {
                container.appendChild(card);
            });
            
            // Animate sorted visible cards
            gsap.fromTo(visibleCards, 
                {
                    opacity: 0,
                    y: 20,
                    scale: 0.95,
                    display: 'block'
                },
                {
                    duration: 0.4,
                    opacity: 1,
                    y: 0,
                    scale: 1,
                    ease: "power2.out",
                    stagger: 0.06,
                    clearProps: "transform",
                    onComplete: resolve
                }
            );
        } else {
            resolve();
        }
    });
}

// Fallback for when GSAP is not available
function initTCCGigGuideBasic() {
    const gigGuides = document.querySelectorAll('.tcc-gig-guide');
    
    gigGuides.forEach(function(guide) {
        const filterButtons = guide.querySelectorAll('.tcc-filter-btn');
        const viewButtons = guide.querySelectorAll('.tcc-view-btn');
        const cards = guide.querySelectorAll('.tcc-gig-card');
        
        // State tracking - check URL parameters for initial venue filter
        let currentVenueFilter = getInitialVenueFilterFromURL();
        let currentDateFilter = 'all-dates';
        
        filterButtons.forEach(function(button) {
            button.addEventListener('click', function() {
                const filter = this.getAttribute('data-filter');
                const filterType = this.getAttribute('data-filter-type');
                
                // Update active state within the same filter group
                const filterGroup = this.closest('.tcc-filter-group');
                filterGroup.querySelectorAll('.tcc-filter-btn').forEach(btn => btn.classList.remove('active'));
                this.classList.add('active');
                
                // Update current filters
                if (filterType === 'venue') {
                    currentVenueFilter = filter;
                } else if (filterType === 'date') {
                    currentDateFilter = filter;
                }
                
                // Apply combined filters (basic version)
                filterCardsBasic(cards, currentVenueFilter, currentDateFilter);
            });
        });
        
        // Add click event listeners to view toggle buttons
        viewButtons.forEach(function(button) {
            button.addEventListener('click', function() {
                const view = this.getAttribute('data-view');
                
                // Update active state
                viewButtons.forEach(btn => btn.classList.remove('active'));
                this.classList.add('active');
                
                // Toggle view
                if (view === 'list') {
                    guide.classList.add('list-view');
                } else {
                    guide.classList.remove('list-view');
                }
            });
        });
        
        // Set initial filter button states based on URL parameter
        if (currentVenueFilter !== 'all') {
            // Remove active from "Show All" button
            const showAllBtn = guide.querySelector('.tcc-filter-btn[data-filter="all"]');
            if (showAllBtn) {
                showAllBtn.classList.remove('active');
            }
            
            // Set the correct venue button as active
            const venueBtn = guide.querySelector(`[data-filter="${currentVenueFilter}"]`);
            if (venueBtn) {
                venueBtn.classList.add('active');
            }
            
            // Apply initial filter if URL parameter was set
            filterCardsBasic(cards, currentVenueFilter, currentDateFilter);
        }
        
        // Initialize card flip functionality for basic version
        initializeCardFlips(guide);
        
        // Set random animation delays for WebP images (basic version)
        setRandomWebPDelays(guide);
    });
}

// Basic filtering function for fallback
function filterCardsBasic(cards, venueFilter, dateFilter) {
    // Date ranges for filtering
    const today = new Date();
    const thisWeekStart = getWeekStart(today);
    const thisWeekEnd = getWeekEnd(today);
    const nextWeekStart = new Date(thisWeekStart.getTime() + 7 * 24 * 60 * 60 * 1000);
    const nextWeekEnd = new Date(thisWeekEnd.getTime() + 7 * 24 * 60 * 60 * 1000);
    const thisMonthStart = getMonthStart(today);
    const thisMonthEnd = getMonthEnd(today);
    
    const visibleCards = [];
    
    cards.forEach(function(card) {
        let shouldShow = true;
        
        // Venue filtering
        if (venueFilter !== 'all') {
            shouldShow = shouldShow && card.classList.contains(venueFilter);
        }
        
        // Date filtering
        if (dateFilter !== 'all-dates') {
            const cardDateStr = card.getAttribute('data-date');
            if (cardDateStr) {
                const cardDate = new Date(cardDateStr);
                
                switch (dateFilter) {
                    case 'this-week':
                        shouldShow = shouldShow && isDateInRange(cardDate, thisWeekStart, thisWeekEnd);
                        break;
                    case 'next-week':
                        shouldShow = shouldShow && isDateInRange(cardDate, nextWeekStart, nextWeekEnd);
                        break;
                    case 'this-month':
                        shouldShow = shouldShow && isDateInRange(cardDate, thisMonthStart, thisMonthEnd);
                        break;
                }
            }
        }
        
        if (shouldShow) {
            card.classList.remove('hidden');
            visibleCards.push(card);
        } else {
            card.classList.add('hidden');
        }
    });
    
    // Sort visible cards by date and time
    if (visibleCards.length > 0) {
        visibleCards.sort(function(a, b) {
            const dateA = a.getAttribute('data-date') || '1900-01-01';
            const timeA = convertTo24Hour(a.getAttribute('data-start-time') || '00:00');
            const dateB = b.getAttribute('data-date') || '1900-01-01';
            const timeB = convertTo24Hour(b.getAttribute('data-start-time') || '00:00');
            
            const datetimeA = dateA + ' ' + timeA;
            const datetimeB = dateB + ' ' + timeB;
            
            return datetimeA.localeCompare(datetimeB);
        });
        
        // Reorder DOM elements based on sorted order
        const container = visibleCards[0].parentNode;
        visibleCards.forEach(function(card) {
            container.appendChild(card);
        });
    }
}

// WebP images now display as standard images without special controls
function setRandomWebPDelays(guide) {
    // Function kept for compatibility but no longer processes WebP images
    console.log('WebP images are now displayed as standard images');
}

// Legacy function for backwards compatibility
function filterCards(cards, filter) {
    if (typeof gsap !== 'undefined') {
        return filterCardsWithGSAP(cards, filter);
    } else {
        // Basic fallback
        cards.forEach(function(card) {
            let shouldShow = filter === 'all' || card.classList.contains(filter);
            
            if (shouldShow) {
                card.classList.remove('hidden');
            } else {
                card.classList.add('hidden');
            }
        });
        return Promise.resolve();
    }
}
