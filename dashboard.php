<?php
require_once 'config.php';
requireLogin();

// Redirect staff to staff panel
if (!User::isAdmin()) {
    header('Location: staff/dashboard.php');
    exit();
}

$dashboardController = new DashboardController();
$stats = $dashboardController->getStats();
$recentOrders = $dashboardController->getRecentOrders();
$lowStockProducts = $dashboardController->getLowStockProducts();
$nearExpiryProducts = $dashboardController->getNearExpiryProducts();

// Debug: Let's see what we're getting
error_log("Low stock products count: " . count($lowStockProducts));
error_log("Near expiry products count: " . count($nearExpiryProducts));
if (!empty($nearExpiryProducts)) {
    error_log("Near expiry products: " . print_r($nearExpiryProducts, true));
}

$role = $_SESSION['role'];
$page_title = $role === 'admin' ? 'Admin Dashboard' : 'Staff Dashboard';

// Start content
ob_start();
?>

<!-- Dashboard Statistics Cards -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stats-card">
            <div class="d-flex align-items-center">
                <div class="stats-icon bg-success me-3" style="width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: white;">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div>
                    <h3 class="mb-0"><?php echo formatCurrency($stats['total_sales']); ?></h3>
                    <p class="text-muted mb-0">Total Sales</p>
                    <small class="text-success"><i class="fas fa-arrow-up me-1"></i>+4.75%</small>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stats-card">
            <div class="d-flex align-items-center">
                <div class="stats-icon bg-info me-3" style="width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: white;">
                    <i class="fas fa-boxes"></i>
                </div>
                <div>
                    <h3 class="mb-0"><?php echo $stats['total_products']; ?></h3>
                    <p class="text-muted mb-0">Products</p>
                    <small class="text-info"><i class="fas fa-arrow-right me-1"></i>Active</small>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stats-card">
            <div class="d-flex align-items-center">
                <div class="stats-icon bg-warning me-3" style="width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: white;">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <div>
                    <h3 class="mb-0"><?php echo $stats['total_orders']; ?></h3>
                    <p class="text-muted mb-0">Total Orders</p>
                    <small class="text-success"><i class="fas fa-arrow-up me-1"></i>+8.2%</small>
                </div>
            </div>
        </div>
    </div>
    
    <?php if ($role === 'admin'): ?>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stats-card">
            <div class="d-flex align-items-center">
                <div class="stats-icon bg-danger me-3" style="width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: white;">
                    <i class="fas fa-users"></i>
                </div>
                <div>
                    <h3 class="mb-0"><?php echo $stats['active_users'] ?? 0; ?></h3>
                    <p class="text-muted mb-0">Active Users</p>
                    <small class="text-success"><i class="fas fa-arrow-up me-1"></i>+2.5%</small>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<div class="row">
    <!-- Recent Activity -->
    <div class="col-xl-12 mb-4">
        <div class="content-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Recent Orders</h5>
                <span class="badge bg-<?php echo $role === 'admin' ? 'danger' : 'success'; ?>"><?php echo count($recentOrders); ?> Orders</span>
            </div>
            <div class="card-body">
                <?php if (empty($recentOrders)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No recent orders found</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Order #</th>
                                    <th>Staff</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentOrders as $order): ?>
                                <tr>
                                    <td>
                                        <strong class="text-<?php echo $role === 'admin' ? 'danger' : 'success'; ?>"><?php echo htmlspecialchars($order['order_number']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></td>
                                    <td><?php echo formatCurrency($order['total_amount']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $order['status'] == 'completed' ? 'success' : 'warning'; ?>">
                                            <?php echo ucfirst($order['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?php echo Layout::getTimeAgo($order['created_at']); ?>
                                        </small>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Low Stock Alert or Quick Actions -->
   
</div>

<!-- Quick Actions Row -->
<div class="row">
    <div class="col-12">
        <div class="content-card">
            <div class="card-header">
                <h5 class="mb-0">Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    
                    <?php if ($role === 'admin'): ?>
                    <div class="col-md-3 mb-3">
                        <a href="inventory.php" class="btn btn-success w-100 btn-custom">
                            <i class="fas fa-plus me-2"></i>
                            Add Product
                        </a>
                    </div>
                        <div class="col-md-3 mb-3">
                            <a href="near_expiries.php" class="btn btn-danger w-100 btn-custom">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Near Expiry Products
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="low_stock.php" class="btn btn-warning w-100 btn-custom">
                                <i class="fas fa-box-open me-2"></i>
                                Low Stock Products
                            </a>
                        </div>
                 
                    <?php endif; ?>
                    <div class="col-md-3 mb-3">
                        <a href="reports.php" class="btn btn-warning w-100 btn-custom">
                            <i class="fas fa-chart-bar me-2"></i>
                            View Reports
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include 'layout.php';
?>
