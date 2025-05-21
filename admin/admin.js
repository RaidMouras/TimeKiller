let currentUserId = null;
let deleteModal = null;
let addTagModal = null;
let editTagModal = null;
let currentTagId = null;

document.addEventListener('DOMContentLoaded', function() {
    deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
    
    // Initialize tag modals if they exist
    const addTagModalEl = document.getElementById('addTagModal');
    const editTagModalEl = document.getElementById('editTagModal');
    
    if (addTagModalEl) {
        addTagModal = new bootstrap.Modal(addTagModalEl);
    }
    
    if (editTagModalEl) {
        editTagModal = new bootstrap.Modal(editTagModalEl);
    }
});

function showDeleteModal(userId) {
    currentUserId = userId;
    document.getElementById('deleteReviews').checked = false;
    document.getElementById('deleteMessages').checked = false;
    deleteModal.show();
}

function deleteContent() {
    const deleteReviews = document.getElementById('deleteReviews').checked;
    const deleteMessages = document.getElementById('deleteMessages').checked;
    
    if (!deleteReviews && !deleteMessages) {
        alert('Please select at least one type of content to delete');
        return;
    }
    
    $.ajax({
        url: 'delete_content.php',
        method: 'POST',
        data: { userId: currentUserId, deleteReviews, deleteMessages },
        success: function(response) {
            if (response.success) {
                alert(response.message);
                deleteModal.hide();
            } else {
                alert('Error: ' + (response.error || 'Unknown error occurred'));
            }
        },
        error: function(xhr) {
            try {
                const response = JSON.parse(xhr.responseText);
                alert('Error: ' + (response.error || 'Unknown error'));
            } catch(e) {
                alert('Error: Unknown error occurred');
            }
        }
    });
}

function toggleBan(userId, currentStatus) {
    if(confirm('confirm to ' + (currentStatus == 1 ? 'unban' : 'ban') + ' this user?')) {
        $.ajax({
            url: 'toggle_ban.php',
            method: 'POST',
            data: { userId, newStatus: currentStatus == 1 ? 0 : 1 },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error: ' + (response.error || 'Unknown error occurred'));
                }
            },
            error: function(xhr) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    alert('Error: ' + (response.error || 'Unknown error'));
                } catch(e) {
                    alert('Error: Unknown error occurred');
                }
            }
        });
    }
}

document.getElementById('searchUser')?.addEventListener('input', function(e) {
    const searchText = e.target.value.toLowerCase().trim();
    const tableRows = document.querySelectorAll('tbody tr');
    
    if (searchText === '') {
        // Show all rows when search box is empty
        tableRows.forEach(row => {
            row.style.display = '';
        });
        return;
    }
    
    // Filter rows based on search text (STRICTLY only name and email)
    tableRows.forEach(row => {
        const cells = row.querySelectorAll('td');
        if (cells.length >= 3) {
            const nameCell = cells[1].textContent.toLowerCase();
            const emailCell = cells[2].textContent.toLowerCase();
            
            // Strict matching - ONLY search in name and email columns
            // Check if searchText is found in either name OR email, nowhere else
            const isVisible = 
                nameCell.includes(searchText) || 
                emailCell.includes(searchText);
            
            row.style.display = isVisible ? '' : 'none';
        }
    });
});

// Tag Management Functions

/**
 * Show the modal for adding a new tag
 */
function showAddTagModal() {
    if (addTagModal) {
        document.getElementById('newTagName').value = '';
        addTagModal.show();
    }
}

/**
 * Show the modal for editing an existing tag
 */
function showEditTagModal(tagId, tagName) {
    if (editTagModal) {
        currentTagId = tagId;
        document.getElementById('editTagId').value = tagId;
        document.getElementById('editTagName').value = tagName;
        editTagModal.show();
    }
}

/**
 * Add a new tag
 */
function addTag() {
    const tagName = document.getElementById('newTagName').value.trim();
    
    if (!tagName) {
        alert('Please enter a tag name');
        return;
    }
    
    $.ajax({
        url: 'manage_tags.php',
        method: 'POST',
        data: { 
            action: 'add',
            tag_name: tagName
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                alert('Tag added successfully');
                addTagModal.hide();
                location.reload();
            } else {
                alert('Error: ' + (response.error || 'Failed to add tag'));
            }
        },
        error: function() {
            alert('Error: Failed to add tag');
        }
    });
}

/**
 * Update an existing tag
 */
function updateTag() {
    const tagId = document.getElementById('editTagId').value;
    const tagName = document.getElementById('editTagName').value.trim();
    
    if (!tagName) {
        alert('Please enter a tag name');
        return;
    }
    
    $.ajax({
        url: 'manage_tags.php',
        method: 'POST',
        data: { 
            action: 'update',
            tag_id: tagId,
            tag_name: tagName
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                alert('Tag updated successfully');
                editTagModal.hide();
                location.reload();
            } else {
                alert('Error: ' + (response.error || 'Failed to update tag'));
            }
        },
        error: function() {
            alert('Error: Failed to update tag');
        }
    });
}

/**
 * Confirm and delete a tag
 */
function confirmDeleteTag(tagId, tagName) {
    if (confirm(`Are you sure you want to delete the tag "${tagName}"? This will remove the tag from all listings.`)) {
        $.ajax({
            url: 'manage_tags.php',
            method: 'POST',
            data: { 
                action: 'delete',
                tag_id: tagId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert('Tag deleted successfully');
                    location.reload();
                } else {
                    alert('Error: ' + (response.error || 'Failed to delete tag'));
                }
            },
            error: function() {
                alert('Error: Failed to delete tag');
            }
        });
    }
} 