$(document).ready(function() {
    // Load user info for non-profile pages
    if (!isProfilePage()) {
        loadNavUserInfo();
    }
});

/**
 * Check if current page is profile page
 */
function isProfilePage() {
    return window.location.href.indexOf('profile.php') > -1 || 
           window.location.href.indexOf('/profile/') > -1;
}

/**
 * Load user information for navigation bar
 */
function loadNavUserInfo() {
    $.ajax({
        url: 'api/profile_api.php',
        type: 'GET',
        data: { action: 'get_profile' },
        dataType: 'json',
        success: function(data) {
            if (data && data.success && data.user) {
                const user = data.user;
                $('.navbar .username').text(user.username);
                $('.navbar .user-type').text(user.user_type);
            }
        },
        error: function() {
        }
    });
}