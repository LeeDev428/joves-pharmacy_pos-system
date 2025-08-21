<?php
require_once 'config.php';
requireLogin();
requireAdmin();

$page_title = 'Low Stock Alert';
$db = Database::getInstance()->getConnection();

// Handle restocking action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restock_product'])) {
    try {
        $product_id = $_POST['product_id'];
        $new_stock = $_POST['new_stock'];
        
        $stmt = $db->prepare("UPDATE products SET stock_quantity = ? WHERE id = ?");
        $stmt->execute([$new_stock, $product_id]);
        
        $success_message = "Stock updated successfully!";
    } catch (PDOException $e) {
        $error_message = "Error updating stock: " . $e->getMessage();
    }
}

// Fetch all low stock products
try {
    $stmt = $db->query("
        SELECT p.*, c.name as category_name,
               CASE 
                   WHEN p.stock_quantity = 0 THEN 'out-of-stock'
                   WHEN p.stock_quantity <= (p.low_stock_threshold * 0.5) THEN 'critical'
                   WHEN p.stock_quantity <= p.low_stock_threshold THEN 'low'
                   ELSE 'normal'
               END as stock_status
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.stock_quantity <= p.low_stock_threshold 
        AND p.status = 'active'
        ORDER BY p.stock_quantity ASC, p.name ASC
    ");
    $lowStockProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Error fetching products: " . $e->getMessage();
    $lowStockProducts = [];
}

// Count different stock levels
$outOfStock = array_filter($lowStockProducts, function($p) { return $p['stock_status'] === 'out-of-stock'; });
$critical = array_filter($lowStockProducts, function($p) { return $p['stock_status'] === 'critical'; });
$lowStock = array_filter($lowStockProducts, function($p) { return $p['stock_status'] === 'low'; });

ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0">
            <i class="fas fa-exclamation-triangle text-warning me-2"></i>
            Low Stock Alert
        </h2>
        <p class="text-muted mb-0">Products running low on stock based on threshold settings</p>
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

<!-- Stock Status Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card border-dark">
            <div class="card-body text-center">
                <i class="fas fa-times-circle fa-2x text-dark mb-2"></i>
                <h3 class="text-dark"><?php echo count($outOfStock); ?></h3>
                <p class="mb-0 text-muted">Out of Stock</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-danger">
            <div class="card-body text-center">
                <i class="fas fa-exclamation-triangle fa-2x text-danger mb-2"></i>
                <h3 class="text-danger"><?php echo count($critical); ?></h3>
                <p class="mb-0 text-muted">Critical Level</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-warning">
            <div class="card-body text-center">
                <i class="fas fa-exclamation-circle fa-2x text-warning mb-2"></i>
                <h3 class="text-warning"><?php echo count($lowStock); ?></h3>
                <p class="mb-0 text-muted">Low Stock</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-info">
            <div class="card-body text-center">
                <i class="fas fa-list fa-2x text-info mb-2"></i>
                <h3 class="text-info"><?php echo count($lowStockProducts); ?></h3>
                <p class="mb-0 text-muted">Total Items</p>
            </div>
        </div>
    </div>
</div>

<div class="content-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="fas fa-boxes me-2"></i>
            Low Stock Products
        </h5>
        <input type="text" id="searchInput" class="form-control form-control-sm" placeholder="Search products..." style="width: 250px;">
    </div>
    <div class="card-body p-0">
        <?php if (empty($lowStockProducts)): ?>
            <div class="text-center py-5">
                <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                <h5 class="text-success">All Products Well Stocked!</h5>
                <p class="text-muted">No products are currently below their low stock thresholds</p>
                <a href="inventory.php" class="btn btn-primary">
                    <i class="fas fa-boxes me-2"></i>View All Inventory
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="lowStockTable">
                    <thead class="table-light">
                        <tr>
                            <th>Product</th>
                            <th>Category</th>
                            <th class="text-center">Current Stock</th>
                            <th class="text-center">Threshold</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Price</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lowStockProducts as $product): ?>
                            <tr class="<?php echo $product['stock_status'] === 'out-of-stock' ? 'table-dark' : ($product['stock_status'] === 'critical' ? 'table-danger' : 'table-warning'); ?>">
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="product-icon bg-<?php echo $product['stock_status'] === 'out-of-stock' ? 'dark' : ($product['stock_status'] === 'critical' ? 'danger' : 'warning'); ?> text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
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
                                    <span class="fw-bold fs-5 text-<?php echo $product['stock_status'] === 'out-of-stock' ? 'dark' : ($product['stock_status'] === 'critical' ? 'danger' : 'warning'); ?>">
                                        <?php echo $product['stock_quantity']; ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="text-muted"><?php echo $product['low_stock_threshold']; ?></span>
                                </td>
                                <td class="text-center">
                                    <?php if ($product['stock_status'] === 'out-of-stock'): ?>
                                        <span class="badge bg-dark fs-6">
                                            <i class="fas fa-times me-1"></i>Out of Stock
                                        </span>
                                    <?php elseif ($product['stock_status'] === 'critical'): ?>
                                        <span class="badge bg-danger fs-6">
                                            <i class="fas fa-exclamation-triangle me-1"></i>Critical
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-warning fs-6">
                                            <i class="fas fa-exclamation-circle me-1"></i>Low Stock
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="fw-bold"><?php echo formatCurrency($product['price']); ?></span>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group" role="group">
                                        <button class="btn btn-sm btn-outline-primary" onclick="restockProduct(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['name']); ?>', <?php echo $product['stock_quantity']; ?>)" title="Restock Product">
                                            <i class="fas fa-plus"></i>
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

<!-- Restock Modal -->
<div class="modal fade" id="restockModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-plus-circle me-2"></i>
                    Restock Product
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="restockForm">
                <div class="modal-body">
                    <input type="hidden" name="restock_product" value="1">
                    <input type="hidden" name="product_id" id="restockProductId">
                    
                    <div class="mb-3">
                        <label class="form-label">Product</label>
                        <input type="text" class="form-control" id="restockProductName" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Current Stock</label>
                        <input type="text" class="form-control" id="restockCurrentStock" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label for="newStock" class="form-label">New Stock Quantity <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="newStock" name="new_stock" min="0" required>
                        <div class="form-text">Enter the new total stock quantity</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Update Stock
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
</style>

<script>
// Search functionality
document.getElementById('searchInput').addEventListener('keyup', function() {
    const searchValue = this.value.toLowerCase();
    const tableRows = document.querySelectorAll('#lowStockTable tbody tr');
    
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

// Restock product function
function restockProduct(productId, productName, currentStock) {
    document.getElementById('restockProductId').value = productId;
    document.getElementById('restockProductName').value = productName;
    document.getElementById('restockCurrentStock').value = currentStock;
    document.getElementById('newStock').value = '';
    
    const restockModal = new bootstrap.Modal(document.getElementById('restockModal'));
    restockModal.show();
}

// Form validation
document.getElementById('restockForm').addEventListener('submit', function(e) {
    const newStock = parseInt(document.getElementById('newStock').value);
    if (newStock < 0) {
        e.preventDefault();
        alert('Stock quantity cannot be negative.');
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
