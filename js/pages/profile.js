$(document).ready(function() {
    // Check if apiPaths variable exists
    if (typeof apiPaths === 'undefined') {
        // Fall back to default paths
        apiPaths = {
            profile: '../api/profile_api.php',
            icon: '../api/icon_handler.php',
            password: '../api/change_password.php'
        };
    }

    // Load initial data
    refreshIcons();
    loadUserProfile();
    autoCleanIcons();

    // Edit link click event
    $('.edit-link').click(function() {
        const field = $(this).data('field');
        openEditModal(field);
    });

    // Password change form submission handler
    $('#submitPasswordChange').click(function() {
        submitPasswordChange();
    });

    // Reset password form when modal is opened
    $('#passwordChangeModal').on('show.bs.modal', function() {
        // Reset the form fields
        $('#passwordChangeForm')[0].reset();
        
        // Clear any error or success messages (including PHP-generated ones)
        $('#password-change-messages').html('');
        
        // Remove any inline error messages that may have been added to the fields
        $('#passwordChangeForm .text-danger').remove();
        $('#passwordChangeForm .alert').remove();
        
        // Ensure all form fields are enabled
        $('#passwordChangeForm input').prop('disabled', false);
        
        // Reset the submit button state
        $('#submitPasswordChange').prop('disabled', false).text('Save Changes');
    });
    
    // Reset the edit modal when opened
    $('#editModal').on('show.bs.modal', function() {
        // The form reset is handled in openEditModal, but ensure all error messages are cleared
        $('#editModal .text-danger').remove();
        $('#editModal .alert').remove();
        $('#password-error-container').empty();
        
        // Reset button state
        $('#saveChanges').prop('disabled', false);
    });

    // Image cropper variables
    window.cropper = null;

    // Preload cropper resources if we're on profile page with file upload
    if ($("#field-file").length > 0) {
        loadCropperResources();
    }

    // File upload change event - simplified
    $('#field-file').change(function() {
        if (this.files && this.files[0]) {
            initImageCropper(this.files[0]);
        }
    });

    // Rotation buttons
    $('#rotate-left').click(function() {
        if (window.cropper) window.cropper.rotate(-90);
    });

    $('#rotate-right').click(function() {
        if (window.cropper) window.cropper.rotate(90);
    });

    // Save button
    $('#saveChanges').click(function() {
        saveChanges();
    });
    
    // Set profile picture dimensions
    const navUserIcon = $('#navUserIcon');
    if (navUserIcon.length) {
        navUserIcon.width(40).height(40);
    }
});

// Initialize image cropper with the selected file
function initImageCropper(file) {
    // First ensure cropper is loaded
    loadCropperResources().then(() => {
        const reader = new FileReader();
        reader.onload = function(e) {
            $('.preview-container').hide();
            $('.image-editor').show();
            $('#cropper-image').attr('src', e.target.result);
            
            if (window.cropper) {
                window.cropper.destroy();
            }
            
            window.cropper = new Cropper(document.getElementById('cropper-image'), {
                aspectRatio: 1,
                viewMode: 1,
                dragMode: 'move',
                autoCropArea: 0.8,
                restore: false,
                guides: true,
                center: true,
                highlight: false,
                cropBoxMovable: true,
                cropBoxResizable: true,
                toggleDragModeOnDblclick: false
            });
        }
        reader.readAsDataURL(file);
    }).catch(error => {
        // Fallback to simple preview without cropping
        const reader = new FileReader();
        reader.onload = function(e) {
            $('.image-editor').hide();
            $('.preview-container').show();
            $('#image-preview').attr('src', e.target.result);
        }
        reader.readAsDataURL(file);
    });
}

// Refresh profile picture
function refreshIcons() {
    const timestamp = new Date().getTime();
    const iconUrl = apiPaths.icon + '?action=get&t=' + timestamp;
    
    // Update profile picture
    $('#profile-picture, #nav-profile-pic').attr('src', iconUrl);
}

// Load user profile - now also updates navbar
function loadUserProfile() {
    const requestData = { action: 'get_profile' };
    
    $.ajax({
        url: apiPaths.profile,
        type: 'GET',
        data: requestData,
        dataType: 'json',
        success: function(data) {
            if (data && data.success) {
                const user = data.user;
                
                if (user) {
                    // Update profile info
                    $('#username-value').text(user.username);
                    $('#user-type').text(user.user_type);
                    $('#email-value').text(user.email);
                    
                    if (user.user_type === 'Business') {
                        $('#name-label').text('Business Name');
                    } else {
                        $('#name-label').text('User Name');
                    }
                    
                    if (user.bio !== undefined) {
                        $('#bio-value').text(user.bio);
                    } else {
                        $('#bio-row').hide();
                    }
                    
                    // Update navbar info
                    $('.navbar .username').text(user.username);
                    $('.navbar .user-type').text(user.user_type);
                }
            }
        },
        error: function(xhr, status, error) {
            // Handle error silently
        }
    });
}

// Open edit modal
function openEditModal(field) {
    $('#editForm')[0].reset();
    $('.preview-container, .image-editor').hide();
    
    $('#field-name').val(field);
    
    let title = 'Edit ';
    if (field === 'username') {
        title += $('#user-type').text().includes('Business') ? 'Business Name' : 'Username';
    }
    else if (field === 'email') title += 'Email';
    else if (field === 'profile_picture') title += 'Avatar';
    else if (field === 'bio') title += 'Bio';
    else if (field === 'password') title += 'Password';
    $('#editModalLabel').text(title);
    
    $('#text-field-container, #textarea-field-container, #file-field-container, #password-field-container').hide();
    
    if (field === 'bio') {
        $('#textarea-field-container').show();
        $('#field-textarea').val($('#bio-value').text());
    } else if (field === 'profile_picture') {
        $('#file-field-container').show();
    } else if (field === 'password') {
        $('#password-field-container').show();
        
        // Clear all error messages
        $('#password-error-container').empty();
        $('#password-field-container .text-danger').remove();
        $('#password-field-container .alert').remove();
        
        // Ensure password fields are empty
        $('#edit-current-password, #edit-new-password, #edit-confirm-password').val('');
    } else {
        $('#text-field-container').show();
        const currentValue = field === 'username' ? $('#username-value').text() :
                            field === 'email' ? $('#email-value').text() : '';
        $('#field-value').val(currentValue);
    }
    
    const editModal = new bootstrap.Modal(document.getElementById('editModal'));
    editModal.show();
}

// Auto-clean old icons
function autoCleanIcons() {
    $.ajax({
        url: apiPaths.icon,
        type: 'GET',
        data: { action: 'clean' }
    });
}

// Process and upload cropped image
function processCroppedImage(fileInput) {
    return new Promise((resolve, reject) => {
        if (!window.cropper) {
            reject(new Error('Cropper not initialized'));
            return;
        }
        
        window.cropper.getCroppedCanvas({
            width: 300,
            height: 300,
            minWidth: 100,
            minHeight: 100,
            imageSmoothingEnabled: true,
            imageSmoothingQuality: 'high',
        }).toBlob(function(blob) {
            const imageFormData = new FormData();
            const fileName = fileInput.files[0].name.split('.')[0] + '.jpg';
            imageFormData.append('file', blob, fileName);
            imageFormData.append('is_cropped', 'true');
            
            resolve(imageFormData);
        }, 'image/jpeg', 0.95);
    });
}

// Save changes
function saveChanges() {
    const field = $('#field-name').val();
    let formData = new FormData();
    
    formData.append('field', field);
    
    if (field === 'bio') {
        const bioValue = $('#field-textarea').val();
        formData.append('value', bioValue);
        submitFormData(formData, apiPaths.profile);
    } else if (field === 'profile_picture') {
        const fileInput = document.getElementById('field-file');
        if (fileInput.files.length > 0) {
            $('#file-field-container .text-danger').remove();
            
            if (window.cropper) {
                // Use the dedicated function for cropping
                processCroppedImage(fileInput)
                    .then(imageFormData => {
                        submitFormData(imageFormData, apiPaths.icon + '?action=update');
                    })
                    .catch(error => {
                        // Fallback to original file upload
                        formData.append('file', fileInput.files[0]);
                        submitFormData(formData, apiPaths.icon + '?action=update');
                    });
            } else {
                formData.append('file', fileInput.files[0]);
                submitFormData(formData, apiPaths.icon + '?action=update');
            }
        } else {
            $('#field-file').addClass('is-invalid');
            setTimeout(() => $('#field-file').removeClass('is-invalid'), 1500);
        }
    } else if (field === 'password') {
        // Clear previous error messages
        $('#password-field-container .text-danger').remove();
        
        // Get password values
        const currentPassword = $('#edit-current-password').val();
        const newPassword = $('#edit-new-password').val();
        const confirmPassword = $('#edit-confirm-password').val();
        
        // Validation
        let isValid = true;
        
        if (!currentPassword) {
            $('#edit-current-password').after('<div class="text-danger">Please enter your current password</div>');
            isValid = false;
        }
        
        if (!newPassword) {
            $('#edit-new-password').after('<div class="text-danger">Please enter a new password</div>');
            isValid = false;
        } else if (newPassword.length < 8) {
            $('#edit-new-password').after('<div class="text-danger">New password must be at least 8 characters</div>');
            isValid = false;
        }
        
        if (!confirmPassword) {
            $('#edit-confirm-password').after('<div class="text-danger">Please confirm the new password</div>');
            isValid = false;
        } else if (newPassword !== confirmPassword) {
            $('#edit-confirm-password').after('<div class="text-danger">Passwords do not match</div>');
            isValid = false;
        }
        
        if (isValid) {
            // Disable save button
            $('#saveChanges').prop('disabled', true);
            
            // Prepare data object - use simple object instead of FormData
            const data = {
                field: 'password',
                current_password: currentPassword,
                new_password: newPassword,
                confirm_password: confirmPassword
            };
            
            // Send AJAX request
            $.ajax({
                url: apiPaths.profile + '?action=update',
                type: 'POST',
                data: data,  // Send object directly
                success: function(response) {
                    if (response && response.success) {
                        // Close modal
                        bootstrap.Modal.getInstance(document.getElementById('editModal')).hide();
                        alert("Password changed successfully!");
                    } else {
                        const errorMsg = response && response.message ? response.message : "Unknown error";
                        
                        // Show error in the form
                        $('#password-error-container').html(
                            '<div class="alert alert-danger">' + 
                            'Password change failed: ' + errorMsg + 
                            '</div>'
                        );
                    }
                },
                error: function(xhr, status, error) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        const errorMsg = response && response.message ? response.message : error;
                        
                        // Show error in the form
                        $('#password-error-container').html(
                            '<div class="alert alert-danger">' + 
                            'Submission error: ' + errorMsg + 
                            '</div>'
                        );
                    } catch(e) {
                        $('#password-error-container').html(
                            '<div class="alert alert-danger">' + 
                            'An error occurred. Please try again.' + 
                            '</div>'
                        );
                    }
                },
                complete: function() {
                    // Re-enable save button
                    $('#saveChanges').prop('disabled', false);
                }
            });
        }
    } else {
        const value = $('#field-value').val();
        formData.append('value', value);
        submitFormData(formData, apiPaths.profile);
    }
}

// Submit form data
function submitFormData(formData, url) {
    $('#saveChanges').prop('disabled', true);
    
    // Add action=update parameter if submitting to profile_api.php and not already present
    if (url.includes('profile_api.php') && !url.includes('?action=')) {
        url += '?action=update';
    }
    
    $.ajax({
        url: url,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(data) {
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('editModal')).hide();
                
                // Reload data
                refreshIcons();
                loadUserProfile();
            } else {
                alert("Failed to save changes: " + (data.message || "Unknown error"));
            }
        },
        error: function(xhr, status, error) {
            alert("Failed to save changes. Please try again.");
        },
        complete: function() {
            $('#saveChanges').prop('disabled', false);
        }
    });
}

// Load Cropper.js resources dynamically
function loadCropperResources() {
    return new Promise((resolve, reject) => {
        // Check if Cropper is already loaded
        if (typeof Cropper !== 'undefined') {
            resolve();
            return;
        }
        
        // Load CSS
        const cropperCSS = document.createElement('link');
        cropperCSS.rel = 'stylesheet';
        cropperCSS.href = 'https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css';
        document.head.appendChild(cropperCSS);
        
        // Load JS
        const cropperJS = document.createElement('script');
        cropperJS.src = 'https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js';
        
        cropperJS.onload = () => {
            resolve();
        };
        
        cropperJS.onerror = () => {
            reject(new Error('Failed to load Cropper.js'));
        };
        
        document.body.appendChild(cropperJS);
    });
}

// Handle password change form submission
function submitPasswordChange() {
    // Clear ALL previous error messages
    $('#password-change-messages').html('');
    $('#passwordChangeForm .text-danger').remove();
    
    // Get form values
    const currentPassword = $('#current_password').val();
    const newPassword = $('#new_password').val();
    const confirmPassword = $('#confirm_password').val();
    
    // Simple client-side validation
    if (!currentPassword || !newPassword || !confirmPassword) {
        $('#password-change-messages').html('<div class="alert alert-danger">All fields are required</div>');
        return;
    }
    
    if (newPassword.length < 8) {
        $('#password-change-messages').html('<div class="alert alert-danger">New password must be at least 8 characters long</div>');
        return;
    }
    
    if (newPassword !== confirmPassword) {
        $('#password-change-messages').html('<div class="alert alert-danger">New passwords do not match</div>');
        return;
    }
    
    // Disable submit button
    $('#submitPasswordChange').prop('disabled', true).text('Processing...');
    
    // Send AJAX request
    $.ajax({
        url: apiPaths.password,
        type: 'POST',
        data: {
            current_password: currentPassword,
            new_password: newPassword,
            confirm_password: confirmPassword
        },
        dataType: 'json',
        success: function(response) {
            if (response && response.success) {
                $('#password-change-messages').html('<div class="alert alert-success">' + response.message + '</div>');
                
                // Delay closing the modal
                setTimeout(function() {
                    bootstrap.Modal.getInstance(document.getElementById('passwordChangeModal')).hide();
                    // Reset form and clear messages
                    $('#passwordChangeForm')[0].reset();
                    $('#password-change-messages').html('');
                }, 2000);
            } else {
                $('#password-change-messages').html('<div class="alert alert-danger">' + (response.message || 'Unknown error occurred') + '</div>');
            }
        },
        error: function(xhr, status, error) {
            let errorMessage = 'An error occurred while processing your request';
            try {
                const response = JSON.parse(xhr.responseText);
                if (response && response.message) {
                    errorMessage = response.message;
                }
            } catch (e) {
                // JSON parsing failed, use default message
            }
            
            $('#password-change-messages').html('<div class="alert alert-danger">' + errorMessage + '</div>');
        },
        complete: function() {
            // Re-enable submit button
            $('#submitPasswordChange').prop('disabled', false).text('Save Changes');
        }
    });
} 