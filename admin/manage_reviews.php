<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header('Location: ../login.php');
    exit();
}

$page_title = 'Manage Reviews';
require_once '../utils/db_config.php';

$conn = get_db_connection();

// Get all reviews with listing information
$query = "SELECT r.*, 
          l.Listing_Name,
          CASE 
              WHEN c.UserID IS NOT NULL THEN c.Username
              WHEN b.UserID IS NOT NULL THEN b.Business_Name
              ELSE 'Unknown'
          END as ReviewerName,
          (SELECT COUNT(*) FROM Review_Response WHERE ReviewID = r.ReviewID) as ResponseCount
          FROM Review r
          JOIN Listing l ON r.ListingID = l.ListingID
          LEFT JOIN Users u ON r.UserID = u.UserID
          LEFT JOIN Customers c ON r.UserID = c.UserID
          LEFT JOIN Business b ON r.UserID = b.UserID
          ORDER BY r.Created_At DESC";

$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Time Killer Admin - Manage Reviews">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo htmlspecialchars($page_title); ?> - Time Killer</title>
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="admin.css" rel="stylesheet">
    
    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="admin.js" defer></script>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-light bg-white">
        <div class="container">
            <span class="navbar-brand">Time Killer</span>
            <div class="navbar-center">
                <img src="../assets/mask_1.png" alt="Logo" style="height: 40px;">
            </div>
            <div class="d-flex align-items-center">
                <div class="text-end">
                    <span class="d-block" style="font-size: 1.1rem;"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></span>
                    <small class="text-muted">Administrator</small>
                </div>
                <a href="../api/logout.php" class="btn btn-outline-danger ms-3">Logout</a>
            </div>
        </div>
    </nav>

    <!-- Main Container -->
    <div class="admin-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <a href="admin.php?view=all" class="nav-link">All Users</a>
            <a href="admin.php?view=banned" class="nav-link">BanList</a>
            <a href="admin.php?view=tags" class="nav-link">Manage Tags</a>
            <a href="manage_reviews.php" class="nav-link active">Manage Reviews</a>
            <a href="manage_messages.php" class="nav-link">Manage Messages</a>
            <a href="admin.php?view=history" class="nav-link">Admin History</a>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4>Manage Reviews</h4>
                <input type="text" class="search-box" placeholder="Search reviews" id="searchReviews">
            </div>
            
            <div class="table-container">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Listing</th>
                                <th>Reviewer</th>
                                <th>Rating</th>
                                <th>Review</th>
                                <th>Responses</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php while($row = $result->fetch_assoc()): ?>
                                <tr data-review-id="<?php echo htmlspecialchars($row['ReviewID']); ?>">
                                    <td><?php echo htmlspecialchars($row['ReviewID']); ?></td>
                                    <td><?php echo htmlspecialchars($row['Listing_Name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['ReviewerName']); ?></td>
                                    <td><?php echo htmlspecialchars($row['Star_Rating']); ?>/5</td>
                                    <td>
                                        <?php 
                                            $content = htmlspecialchars($row['Body']);
                                            echo strlen($content) > 50 ? substr($content, 0, 50) . '...' : $content;
                                        ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?php echo htmlspecialchars($row['ResponseCount']); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['Created_At']); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-info view-review-btn" 
                                                data-review-id="<?php echo $row['ReviewID']; ?>"
                                                data-review-content="<?php echo htmlspecialchars($row['Body']); ?>"
                                                data-listing-name="<?php echo htmlspecialchars($row['Listing_Name']); ?>"
                                                data-reviewer-name="<?php echo htmlspecialchars($row['ReviewerName']); ?>"
                                                data-rating="<?php echo htmlspecialchars($row['Star_Rating']); ?>"
                                                data-date="<?php echo htmlspecialchars($row['Created_At']); ?>"
                                                data-responses="<?php echo htmlspecialchars($row['ResponseCount']); ?>">
                                            View
                                        </button>
                                        <button class="btn btn-sm btn-danger delete-review-btn" 
                                                data-review-id="<?php echo $row['ReviewID']; ?>">
                                            Delete
                                        </button>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center">No reviews found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Review Detail Modal -->
    <div class="modal fade" id="reviewDetailModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Review Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between">
                            <span id="reviewListing"></span>
                            <span class="badge bg-primary" id="reviewRating"></span>
                        </div>
                        <div class="card-body">
                            <h6 class="card-subtitle mb-2 text-muted">
                                By <span id="reviewerName"></span> on <span id="reviewDate"></span>
                            </h6>
                            <p class="card-text" id="reviewContent"></p>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">Response count: <span id="responseCount"></span></small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Review Responses Section -->
                    <div id="responsesSection">
                        <h5 class="border-bottom pb-2 mb-3">Responses</h5>
                        <div id="responsesContainer">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-danger" id="deleteReviewFromModal">Delete Review</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteReviewModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this review?（This will also delete all responses to this review.）</p>
                    <p class="text-danger">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteReview">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Response Confirmation Modal -->
    <div class="modal fade" id="deleteResponseModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete Response</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this response?</p>
                    <p class="text-danger">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteResponse">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            let currentReviewId = null;
            let currentResponseId = null;
            const reviewDetailModal = new bootstrap.Modal(document.getElementById('reviewDetailModal'));
            const deleteReviewModal = new bootstrap.Modal(document.getElementById('deleteReviewModal'));
            const deleteResponseModal = new bootstrap.Modal(document.getElementById('deleteResponseModal'));
            
            // Set up search functionality
            document.getElementById('searchReviews').addEventListener('input', function(e) {
                const searchText = e.target.value.toLowerCase().trim();
                const tableRows = document.querySelectorAll('tbody tr');
                
                tableRows.forEach(row => {
                    const cells = row.querySelectorAll('td');
                    let rowText = '';
                    cells.forEach((cell, index) => {
                        if (index < 7) { // Exclude the Actions column
                            rowText += cell.textContent.toLowerCase() + ' ';
                        }
                    });
                    
                    row.style.display = rowText.includes(searchText) ? '' : 'none';
                });
            });
            
            // View review details
            document.querySelectorAll('.view-review-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const reviewId = this.dataset.reviewId;
                    currentReviewId = reviewId;
                    
                    document.getElementById('reviewListing').textContent = this.dataset.listingName;
                    document.getElementById('reviewRating').textContent = this.dataset.rating + '/5';
                    document.getElementById('reviewerName').textContent = this.dataset.reviewerName;
                    document.getElementById('reviewDate').textContent = this.dataset.date;
                    document.getElementById('reviewContent').textContent = this.dataset.reviewContent;
                    document.getElementById('responseCount').textContent = this.dataset.responses;
                    
                    // Load review responses
                    loadReviewResponses(reviewId);
                    
                    reviewDetailModal.show();
                });
            });
            
            // Load review responses
            function loadReviewResponses(reviewId) {
                const responsesContainer = document.getElementById('responsesContainer');
                responsesContainer.innerHTML = '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>';
                
                fetch(`get_review_responses.php?reviewId=${reviewId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Display responses
                            if (data.responses.length === 0) {
                                responsesContainer.innerHTML = '<p class="text-muted">No responses found</p>';
                            } else {
                                const responsesList = document.createElement('div');
                                responsesList.className = 'list-group';
                                
                                data.responses.forEach(response => {
                                    const responseItem = document.createElement('div');
                                    responseItem.className = 'list-group-item';
                                    responseItem.innerHTML = `
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="text-muted small">${response.SenderName} on ${response.Time_sent}</span>
                                            <button class="btn btn-sm btn-danger delete-response-btn" data-response-id="${response.ReplyID}">Delete</button>
                                        </div>
                                        <p class="mb-0">${response.Content}</p>
                                    `;
                                    responsesList.appendChild(responseItem);
                                });
                                
                                responsesContainer.innerHTML = '';
                                responsesContainer.appendChild(responsesList);
                                
                                // Attach event listeners to delete buttons
                                document.querySelectorAll('.delete-response-btn').forEach(button => {
                                    button.addEventListener('click', function() {
                                        currentResponseId = this.dataset.responseId;
                                        deleteResponseModal.show();
                                    });
                                });
                            }
                        } else {
                            responsesContainer.innerHTML = `<p class="text-danger">Error: ${data.error || 'Failed to load responses'}</p>`;
                        }
                    })
                    .catch(error => {
                        responsesContainer.innerHTML = '<p class="text-danger">Error loading responses</p>';
                        console.error('Error:', error);
                    });
            }
            
            // Delete review button in detail modal
            document.getElementById('deleteReviewFromModal').addEventListener('click', function() {
                reviewDetailModal.hide();
                deleteReviewModal.show();
            });
            
            // Delete review button in table
            document.querySelectorAll('.delete-review-btn').forEach(button => {
                button.addEventListener('click', function() {
                    currentReviewId = this.dataset.reviewId;
                    deleteReviewModal.show();
                });
            });
            
            // Confirm delete review
            document.getElementById('confirmDeleteReview').addEventListener('click', function() {
                if (currentReviewId) {
                    deleteReview(currentReviewId);
                }
            });
            
            // Confirm delete response
            document.getElementById('confirmDeleteResponse').addEventListener('click', function() {
                if (currentResponseId) {
                    deleteResponse(currentResponseId);
                }
            });
            
            function deleteReview(reviewId) {
                // Show loading indicator
                document.getElementById('confirmDeleteReview').disabled = true;
                document.getElementById('confirmDeleteReview').innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Deleting...';
                
                $.ajax({
                    url: 'delete_content.php',
                    method: 'POST',
                    data: { 
                        type: 'review',
                        id: reviewId
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            const row = document.querySelector(`tr[data-review-id="${reviewId}"]`);
                            if (row) {
                                row.remove();
                            }
                            deleteReviewModal.hide();
                            
                            // Add success notification
                            const notification = document.createElement('div');
                            notification.className = 'alert alert-success alert-dismissible fade show';
                            notification.innerHTML = `
                                ${response.message}
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            `;
                            document.querySelector('.main-content').insertBefore(notification, document.querySelector('.table-container'));
                            
                            // Auto-dismiss notification after 5 seconds
                            setTimeout(() => {
                                notification.classList.remove('show');
                                setTimeout(() => notification.remove(), 150);
                            }, 5000);
                        } else {
                            alert('Error: ' + (response.error || 'Failed to delete review'));
                        }
                    },
                    error: function() {
                        alert('Error: Failed to delete review');
                    },
                    complete: function() {
                        document.getElementById('confirmDeleteReview').disabled = false;
                        document.getElementById('confirmDeleteReview').textContent = 'Delete';
                    }
                });
            }
            
            function deleteResponse(responseId) {
                // Show loading indicator
                document.getElementById('confirmDeleteResponse').disabled = true;
                document.getElementById('confirmDeleteResponse').innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Deleting...';
                
                $.ajax({
                    url: 'delete_content.php',
                    method: 'POST',
                    data: { 
                        type: 'response',
                        id: responseId
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            deleteResponseModal.hide();
                            
                            // Refresh responses list
                            loadReviewResponses(currentReviewId);
                            
                            // Add success notification
                            const notification = document.createElement('div');
                            notification.className = 'alert alert-success alert-dismissible fade show';
                            notification.innerHTML = `
                                ${response.message}
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            `;
                            document.querySelector('.main-content').insertBefore(notification, document.querySelector('.table-container'));
                            
                            // Auto-dismiss notification after 5 seconds
                            setTimeout(() => {
                                notification.classList.remove('show');
                                setTimeout(() => notification.remove(), 150);
                            }, 5000);
                            
                            // Refresh the reviews table to update response counts
                            setTimeout(() => {
                                location.reload();
                            }, 1000);
                        } else {
                            alert('Error: ' + (response.error || 'Failed to delete response'));
                        }
                    },
                    error: function() {
                        alert('Error: Failed to delete response');
                    },
                    complete: function() {
                        document.getElementById('confirmDeleteResponse').disabled = false;
                        document.getElementById('confirmDeleteResponse').textContent = 'Delete';
                    }
                });
            }
        });
    </script>
</body>
</html>

<?php $conn->close(); ?> 