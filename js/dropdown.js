/**
 * Dropdown menu functionality for navigation bar
 */

// Self-executing function to ensure immediate initialization
(function() {
    // Check if DOM is still loading
    if (document.readyState === 'loading') {
        // If DOM is still loading, add event listener
        document.addEventListener('DOMContentLoaded', initializeDropdowns);
    } else {
        // If DOM is already loaded, initialize directly
        initializeDropdowns();
    }
    
    // Also add window load event to ensure initialization after all resources are loaded
    window.addEventListener('load', function() {
        initializeDropdowns();
    });
})();

/**
 * Initialize dropdown menus with Bootstrap or fallback to jQuery
 */
function initializeDropdowns() {
    // Prevent multiple initializations
    if (window.dropdownsInitialized) {
        return;
    }
    
    // First, check if we're on the home page and ensure dropdown-toggle class is present
    var isHomePage = window.location.pathname.endsWith('/home.php') || 
                     window.location.pathname.endsWith('/') ||
                     window.location.pathname.endsWith('/index.php');
                     
    if (isHomePage) {
        var userDropdown = document.getElementById('userDropdown');
        if (userDropdown && !userDropdown.classList.contains('dropdown-toggle')) {
            userDropdown.classList.add('dropdown-toggle');
        }
    }
    
    // Mark as initialized
    window.dropdownsInitialized = true;
    
    // Try to use Bootstrap dropdown
    if (typeof bootstrap !== 'undefined') {
        try {
            var dropdownElementList = document.querySelectorAll('.dropdown-toggle');
            
            // Only proceed if we found elements
            if (dropdownElementList && dropdownElementList.length > 0) {
                dropdownElementList.forEach(function(dropdownToggleEl) {
                    new bootstrap.Dropdown(dropdownToggleEl);
                });
            }
        } catch (error) {
            initializeJQueryDropdowns();
        }
    } else {
        // Bootstrap not available, use jQuery fallback
        initializeJQueryDropdowns();
    }
}

/**
 * Fallback dropdown initialization using jQuery
 */
function initializeJQueryDropdowns() {
    // Ensure jQuery is available
    if (typeof jQuery === 'undefined') {
        return;
    }
    
    // Use jQuery to toggle dropdown menu
    $('.dropdown-toggle').off('click.dropdown').on('click.dropdown', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var $menu = $(this).next('.dropdown-menu');
        var isVisible = $menu.is(':visible');
        $('.dropdown-menu').hide();
        if (!isVisible) $menu.show();
    });
    
    // Close dropdown when clicking outside
    $(document).off('click.dropdownOutside').on('click.dropdownOutside', function(e) {
        if (!$(e.target).closest('.dropdown').length) {
            $('.dropdown-menu').hide();
        }
    });
}