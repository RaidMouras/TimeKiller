<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}
$_SERVER['PHP_SELF'] = '/business/service_history.php';  
require_once '../utils/db_config.php';
$page_title = "Services Sold History";
$page_specific_css = '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">';
$disable_header_redirect = true;

include_once '../utils/header.php';

// Get sold services history
function getSoldServicesHistory($businessID) {
    $conn = get_db_connection();
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Query to get services sold through direct tier purchases
    $query1 = "SELECT 
                ph.PurchaseID, 
                ph.UserID as CustomerID, 
                ph.Time_Of_Purchase as PurchaseDate, 
                pt.Price, 
                pt.Tier_Name, 
                l.Listing_Name, 
                c.Username as CustomerName,
                'Direct Purchase' as PurchaseType
              FROM Purchase_History_Tier ph
              JOIN Price_Tiers pt ON ph.TierID = pt.TierID
              JOIN Listing l ON pt.ListingID = l.ListingID
              JOIN Customers c ON ph.UserID = c.UserID
              WHERE l.UserID = ?";
    
    // Query to get services sold through negotiations
    $query2 = "SELECT 
                phn.PurchaseID, 
                phn.UserID as CustomerID, 
                phn.Time_Of_Purchase as PurchaseDate, 
                n.Price, 
                'Negotiated Offer' as Tier_Name, 
                l.Listing_Name, 
                c.Username as CustomerName,
                'Negotiation' as PurchaseType
              FROM Purchase_History_Negotiation phn
              JOIN Negotiations n ON phn.NegotiationID = n.NegotiationID
              JOIN Listing l ON n.ListingID = l.ListingID
              JOIN Customers c ON phn.UserID = c.UserID
              WHERE l.UserID = ? AND n.Current_Status = 'Accepted'";
    
    // Combine both queries with UNION ALL
    $query = "($query1) UNION ALL ($query2) ORDER BY PurchaseDate DESC";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("ii", $businessID, $businessID);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    $stmt->close();
    $conn->close();
    return $data;
}

$soldServices = getSoldServicesHistory($_SESSION["user_id"]);
?>
<script>
    document.body.style.backgroundColor = "rgb(221, 239, 240)";
    document.addEventListener("DOMContentLoaded", function() {
        var profilePic = document.getElementById("nav-profile-pic");
        if (profilePic) {
            profilePic.setAttribute("src", "../api/icon_handler.php?action=get&nc=" + Date.now());
        }
        var logo = document.getElementById("nav-center-logo");
        if (logo) {
            logo.setAttribute("src", "../assets/mask_1.png");
        }
        var navLinks = document.querySelectorAll(".navbar a:not([href='#'])");
        navLinks.forEach(function(link) {
            var href = link.getAttribute("href");
            if (href && !href.startsWith("../") && !href.startsWith("http") && !href.startsWith("#")) {
                link.setAttribute("href", "../" + href);
            }
        });
        var dropdownItems = document.querySelectorAll(".dropdown-item");
        dropdownItems.forEach(function(item) {
            var href = item.getAttribute("href");
            if (href && !href.startsWith("../") && !href.startsWith("http")) {
                item.setAttribute("href", "../" + href);
            }
        });
        
        // Show More / Show Less functionality for service history
        function initializeShowMoreLess() {
            const tableBody = document.querySelector('.table tbody');
            if (!tableBody) return;
            
            const rows = tableBody.querySelectorAll('tr');
            const initialDisplay = 10; // Number of rows to initially show
            
            if (rows.length > initialDisplay) {
                // Hide rows beyond the initial display count
                for (let i = initialDisplay; i < rows.length; i++) {
                    rows[i].classList.add('d-none');
                }
                
                // Create and add the Show More button
                const buttonContainer = document.createElement('div');
                buttonContainer.className = 'text-center mt-3';
                
                const showMoreBtn = document.createElement('button');
                showMoreBtn.className = 'btn btn-outline-primary';
                showMoreBtn.textContent = 'Show More (' + (rows.length - initialDisplay) + ' more)';
                showMoreBtn.id = 'show-more-services';
                buttonContainer.appendChild(showMoreBtn);
                
                // Insert after the table
                const tableResponsive = document.querySelector('.table-responsive');
                tableResponsive.parentNode.insertBefore(buttonContainer, tableResponsive.nextSibling);
                
                // Add event listener for Show More button
                showMoreBtn.addEventListener('click', function() {
                    if (showMoreBtn.textContent.includes('Show More')) {
                        // Show all hidden rows
                        rows.forEach(row => row.classList.remove('d-none'));
                        showMoreBtn.textContent = 'Show Less';
                    } else {
                        // Hide rows beyond the initial display count
                        for (let i = initialDisplay; i < rows.length; i++) {
                            rows[i].classList.add('d-none');
                        }
                        showMoreBtn.textContent = 'Show More (' + (rows.length - initialDisplay) + ' more)';
                    }
                });
            }
        }
        
        // Initialize when DOM is loaded
        initializeShowMoreLess();
    });
</script>
<style>
    .action-buttons .btn {
        margin-right: 5px;
    }
    .card {
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    #show-more-services {
        transition: all 0.3s ease;
    }
    #show-more-services:hover {
        background-color: #0d6efd;
        color: white;
    }
    .badge-negotiation {
        background-color: #ffc107;
        color: #212529;
    }
    .badge-direct {
        background-color: #0d6efd;
        color: white;
    }
</style>

<div class="container mt-4">
    <form action="../views/businesshub.php">
        <button type="submit" class="btn btn-outline-primary mb-4">
            <i class="bi bi-arrow-left"></i> Back
        </button>
    </form>
    
    <div class="card">
        <div class="card-header">
            <h2>Services Sold History</h2>
        </div>
        <div class="card-body">
            <?php if (empty($soldServices)): ?>
                <div class="alert alert-info">
                    No services have been sold yet.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Listing</th>
                                <th>Type</th>
                                <th>Price</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($soldServices as $service): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($service['CustomerName']); ?></td>
                                <td><?php echo htmlspecialchars($service['Listing_Name']); ?></td>
                                <td>
                                    <?php if ($service['PurchaseType'] == 'Negotiation'): ?>
                                        <span class="badge badge-negotiation">
                                            <?php echo htmlspecialchars($service['Tier_Name']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-direct">
                                            <?php echo htmlspecialchars($service['Tier_Name']); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>€<?php echo number_format($service['Price'], 2); ?></td>
                                <td><?php echo date('Y-m-d H:i', strtotime($service['PurchaseDate'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
include_once '../utils/footer.php';
?> 