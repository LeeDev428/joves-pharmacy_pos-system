<?php
require_once 'config.php';
requireLogin();
requireAdmin();

$page_title = 'Near Expiry Products';
$db = Database::getInstance()->getConnection();

// Handle product actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['mark_discounted'])) {
            $product_id = $_POST['product_id'];
            $discount_price = $_POST['discount_price'];
            
            // Update product with discounted price or mark for disposal
            $stmt = $db->prepare("UPDATE products SET discount_price = ?, notes = CONCAT(COALESCE(notes, ''), '\nMarked for discount due to near expiry - ', NOW()) WHERE id = ?");
            $stmt->execute([$discount_price, $product_id]);
            
            $success_message = "Product marked for discount successfully!";
        } elseif (isset($_POST['extend_expiry'])) {
            $product_id = $_POST['product_id'];
            $new_expiry = $_POST['new_expiry'];
            
            $stmt = $db->prepare("UPDATE products SET expiry = ? WHERE id = ?");
            $stmt->execute([$new_expiry, $product_id]);
            
            $success_message = "Expiry date updated successfully!";
        }
    } catch (PDOException $e) {
        $error_message = "Error updating product: " . $e->getMessage();
    }
}

// Fetch products expiring in 5 days
try {
    $stmt = $db->query("
        SELECT p.*, c.name as category_name,
               DATEDIFF(p.expiry, CURDATE()) as days_to_expiry,
               CASE 
                   WHEN DATEDIFF(p.expiry, CURDATE()) <= 0 THEN 'expired'
                   WHEN DATEDIFF(p.expiry, CURDATE()) <= 1 THEN 'critical'
                   WHEN DATEDIFF(p.expiry, CURDATE()) <= 3 THEN 'warning'
                   ELSE 'normal'
               END as expiry_status
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.expiry IS NOT NULL 
        AND p.expiry <= DATE_ADD(CURDATE(), INTERVAL 5 DAY)
        AND p.status = 'active'
        ORDER BY p.expiry ASC, p.name ASC
    ");
    $nearExpiryProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Error fetching products: " . $e->getMessage();
    $nearExpiryProducts = [];
}

// Count different expiry levels
$expired = array_filter($nearExpiryProducts, function($p) { return $p['expiry_status'] === 'expired'; });
$criticalExpiry = array_filter($nearExpiryProducts, function($p) { return $p['expiry_status'] === 'critical'; });
$warningExpiry = array_filter($nearExpiryProducts, function($p) { return $p['expiry_status'] === 'warning'; });
$normalExpiry = array_filter($nearExpiryProducts, function($p) { return $p['expiry_status'] === 'normal'; });

ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0">
            <i class="fas fa-clock text-warning me-2"></i>
            Near Expiry Products
        </h2>
        <p class="text-muted mb-0">Products expiring within 5 days</p>
    </div>
    <div>
        <a href="inventory.php" class="btn btn-primary me-2">
            <i class="fas fa-plus me-2"></i>Add New Product
        </a>
        <a href="dashboard.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
        </a>
    </div>
</div>

<?php if (isset($success_message)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i>
        <?php echo htmlspecialchars($success_message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($error_message)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i>
        <?php echo htmlspecialchars($error_message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Expiry Status Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card border-dark">
            <div class="card-body text-center">
                <i class="fas fa-skull-crossbones fa-2x text-dark mb-2"></i>
                <h3 class="text-dark"><?php echo count($expired); ?></h3>
                <p class="mb-0 text-muted">Expired</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-danger">
            <div class="card-body text-center">
                <i class="fas fa-exclamation-triangle fa-2x text-danger mb-2"></i>
                <h3 class="text-danger"><?php echo count($criticalExpiry); ?></h3>
                <p class="mb-0 text-muted">Expires Today/Tomorrow</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-warning">
            <div class="card-body text-center">
                <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                <h3 class="text-warning"><?php echo count($warningExpiry); ?></h3>
                <p class="mb-0 text-muted">Expires in 2-3 Days</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-info">
            <div class="card-body text-center">
                <i class="fas fa-calendar-alt fa-2x text-info mb-2"></i>
                <h3 class="text-info"><?php echo count($normalExpiry); ?></h3>
                <p class="mb-0 text-muted">Expires in 4-5 Days</p>
            </div>
        </div>
    </div>
</div>

<div class="content-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="fas fa-calendar-exclamation me-2"></i>
            Near Expiry Products
        </h5>
        <input type="text" id="searchInput" class="form-control form-control-sm" placeholder="Search products..." style="width: 250px;">
    </div>
    <div class="card-body p-0">
        <?php if (empty($nearExpiryProducts)): ?>
            <div class="text-center py-5">
                <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                <h5 class="text-success">All Products Fresh!</h5>
                <p class="text-muted">No products are expiring within the next 5 days</p>
                <a href="inventory.php" class="btn btn-primary">
                    <i class="fas fa-boxes me-2"></i>View All Inventory
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="nearExpiryTable">
                    <thead class="table-light">
                        <tr>
                            <th>Product</th>
                            <th>Category</th>
                            <th class="text-center">Stock</th>
                            <th class="text-center">Expiry Date</th>
                            <th class="text-center">Days Left</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Price</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($nearExpiryProducts as $product): ?>
                            <tr class="<?php echo $product['expiry_status'] === 'expired' ? 'table-dark' : ($product['expiry_status'] === 'critical' ? 'table-danger' : ($product['expiry_status'] === 'warning' ? 'table-warning' : 'table-info')); ?>">
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="product-icon bg-<?php echo $product['expiry_status'] === 'expired' ? 'dark' : ($product['expiry_status'] === 'critical' ? 'danger' : ($product['expiry_status'] === 'warning' ? 'warning' : 'info')); ?> text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                            <i class="fas fa-pills"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-0"><?php echo htmlspecialchars($product['name']); ?></h6>
                                            <small class="text-muted"><?php echo htmlspecialchars($product['description'] ?: 'No description'); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-secondary">
                                        <?php echo htmlspecialchars($product['category_name'] ?: 'Uncategorized'); ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="fw-bold"><?php echo $product['stock_quantity']; ?></span>
                                </td>
                                <td class="text-center">
                                    <div class="fw-bold"><?php echo date('M d, Y', strtotime($product['expiry'])); ?></div>
                                    <small class="text-muted"><?php echo date('l', strtotime($product['expiry'])); ?></small>
                                </td>
                                <td class="text-center">
                                    <?php if ($product['days_to_expiry'] <= 0): ?>
                                        <span class="badge bg-dark fs-6">Expired</span>
                                    <?php else: ?>
                                        <span class="fw-bold fs-5 text-<?php echo $product['expiry_status'] === 'critical' ? 'danger' : ($product['expiry_status'] === 'warning' ? 'warning' : 'info'); ?>">
                                            <?php echo $product['days_to_expiry']; ?> days
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($product['expiry_status'] === 'expired'): ?>
                                        <span class="badge bg-dark fs-6">
                                            <i class="fas fa-skull-crossbones me-1"></i>Expired
                                        </span>
                                    <?php elseif ($product['expiry_status'] === 'critical'): ?>
                                        <span class="badge bg-danger fs-6">
                                            <i class="fas fa-exclamation-triangle me-1"></i>Critical
                                        </span>
                                    <?php elseif ($product['expiry_status'] === 'warning'): ?>
                                        <span class="badge bg-warning fs-6">
                                            <i class="fas fa-clock me-1"></i>Warning
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-info fs-6">
                                            <i class="fas fa-calendar me-1"></i>Monitor
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="fw-bold"><?php echo formatCurrency($product['price']); ?></div>
                                    <?php if (isset($product['discount_price']) && $product['discount_price'] > 0): ?>
                                        <small class="text-success">Discount: <?php echo formatCurrency($product['discount_price']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group" role="group">
                                        <button class="btn btn-sm btn-outline-warning" onclick="markDiscount(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['name']); ?>', <?php echo $product['price']; ?>)" title="Mark for Discount">
                                            <i class="fas fa-percentage"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-info" onclick="extendExpiry(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['name']); ?>', '<?php echo $product['expiry']; ?>')" title="Extend Expiry">
                                            <i class="fas fa-calendar-plus"></i>
                                        </button>
                                        <a href="inventory.php?edit=<?php echo $product['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Edit Product">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Mark for Discount Modal -->
<div class="modal fade" id="discountModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title">
                    <i class="fas fa-percentage me-2"></i>
                    Mark for Discount
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="discountForm">
                <div class="modal-body">
                    <input type="hidden" name="mark_discounted" value="1">
                    <input type="hidden" name="product_id" id="discountProductId">
                    
                    <div class="mb-3">
                        <label class="form-label">Product</label>
                        <input type="text" class="form-control" id="discountProductName" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Original Price</label>
                        <input type="text" class="form-control" id="discountOriginalPrice" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label for="discountPrice" class="form-label">Discount Price <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="discountPrice" name="discount_price" min="0" step="0.01" required>
                        <div class="form-text">Enter the discounted price for this near-expiry product</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-percentage me-2"></i>Apply Discount
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Extend Expiry Modal -->
<div class="modal fade" id="expiryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">
                    <i class="fas fa-calendar-plus me-2"></i>
                    Extend Expiry Date
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="expiryForm">
                <div class="modal-body">
                    <input type="hidden" name="extend_expiry" value="1">
                    <input type="hidden" name="product_id" id="expiryProductId">
                    
                    <div class="mb-3">
                        <label class="form-label">Product</label>
                        <input type="text" class="form-control" id="expiryProductName" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Current Expiry Date</label>
                        <input type="text" class="form-control" id="expiryCurrentDate" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label for="newExpiryDate" class="form-label">New Expiry Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="newExpiryDate" name="new_expiry" required>
                        <div class="form-text">Set a new expiry date for this product</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info">
                        <i class="fas fa-calendar-plus me-2"></i>Update Expiry
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.content-card {
    border: none;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    border-radius: 10px;
}

.table th {
    border-top: none;
    font-weight: 600;
    color: #495057;
}

.table td {
    vertical-align: middle;
}

.product-icon {
    font-size: 0.9rem;
}

.btn-group .btn {
    border-radius: 6px;
    margin: 0 2px;
}

.alert {
    border-radius: 10px;
    border: none;
}

.table-dark {
    --bs-table-bg: #212529;
    --bs-table-color: #fff;
}

.table-danger {
    --bs-table-bg: #f8d7da;
    --bs-table-color: #721c24;
}

.table-warning {
    --bs-table-bg: #fff3cd;
    --bs-table-color: #856404;
}

.table-info {
    --bs-table-bg: #d1ecf1;
    --bs-table-color: #0c5460;
}
</style>

<script>
// Search functionality
document.getElementById('searchInput').addEventListener('keyup', function() {
    const searchValue = this.value.toLowerCase();
    const tableRows = document.querySelectorAll('#nearExpiryTable tbody tr');
    
    tableRows.forEach(row => {
        const productName = row.cells[0].textContent.toLowerCase();
        const category = row.cells[1].textContent.toLowerCase();
        
        if (productName.includes(searchValue) || category.includes(searchValue)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
});

// Mark for discount function
function markDiscount(productId, productName, originalPrice) {
    document.getElementById('discountProductId').value = productId;
    document.getElementById('discountProductName').value = productName;
    document.getElementById('discountOriginalPrice').value = '$' + originalPrice.toFixed(2);
    document.getElementById('discountPrice').value = '';
    
    const discountModal = new bootstrap.Modal(document.getElementById('discountModal'));
    discountModal.show();
}

// Extend expiry function
function extendExpiry(productId, productName, currentExpiry) {
    document.getElementById('expiryProductId').value = productId;
    document.getElementById('expiryProductName').value = productName;
    document.getElementById('expiryCurrentDate').value = new Date(currentExpiry).toLocaleDateString();
    document.getElementById('newExpiryDate').value = '';
    
    const expiryModal = new bootstrap.Modal(document.getElementById('expiryModal'));
    expiryModal.show();
}

// Form validation
document.getElementById('discountForm').addEventListener('submit', function(e) {
    const discountPrice = parseFloat(document.getElementById('discountPrice').value);
    if (discountPrice < 0) {
        e.preventDefault();
        alert('Discount price cannot be negative.');
        return false;
    }
});

document.getElementById('expiryForm').addEventListener('submit', function(e) {
    const newDate = new Date(document.getElementById('newExpiryDate').value);
    const currentDate = new Date();
    
    if (newDate <= currentDate) {
        e.preventDefault();
        alert('New expiry date must be in the future.');
        return false;
    }
});

// Auto-hide alerts after 5 seconds
setTimeout(function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
    });
}, 5000);
</script>

<?php
$content = ob_get_clean();
include 'layout.php';
?>
