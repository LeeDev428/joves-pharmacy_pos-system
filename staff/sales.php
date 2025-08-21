<?php
require_once '../config.php';
User::requireLogin();

if (User::isAdmin()) {
    header('Location: ../sales_analysis.php');
    exit();
}

$db = Database::getInstance()->getConnection();

// Get sales data for charts
$dailySales = $db->query("
    SELECT DATE(created_at) as sale_date, 
           SUM(total_amount) as total_sales,
           COUNT(*) as order_count
    FROM orders 
    WHERE status = 'completed' 
    AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY DATE(created_at) 
    ORDER BY sale_date DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Get today's stats
$todayStats = $db->query("
    SELECT 
        COUNT(*) as total_orders,
        COALESCE(SUM(total_amount), 0) as total_sales,
        COALESCE(AVG(total_amount), 0) as avg_order_value
    FROM orders 
    WHERE DATE(created_at) = CURDATE() 
    AND status = 'completed'
")->fetch(PDO::FETCH_ASSOC);

// Get top products today
$topProducts = $db->query("
    SELECT p.name, SUM(oi.quantity) as total_sold, SUM(oi.total_price) as revenue
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    JOIN orders o ON oi.order_id = o.id
    WHERE DATE(o.created_at) = CURDATE() AND o.status = 'completed'
    GROUP BY p.id, p.name
    ORDER BY total_sold DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Prepare data for charts
$chartDates = array_reverse(array_column($dailySales, 'sale_date'));
$chartSales = array_reverse(array_column($dailySales, 'total_sales'));
$chartOrders = array_reverse(array_column($dailySales, 'order_count'));

$title = 'Sales Dashboard';

ob_start();
?>

<style>
.gradient-card-1 {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
    color: white !important;
}
.gradient-card-2 {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%) !important;
    color: white !important;
}
.gradient-card-3 {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%) !important;
    color: white !important;
}
.gradient-card-1 *, .gradient-card-2 *, .gradient-card-3 * {
    color: white !important;
}
</style>

<!-- Sales Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-4 mb-3">
        <div class="card border-0 shadow-sm text-center gradient-card-1">
            <div class="card-body">
                <i class="fas fa-shopping-cart fa-2x mb-2"></i>
                <h3><?php echo number_format($todayStats['total_orders']); ?></h3>
                <small>Orders Today</small>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card border-0 shadow-sm text-center gradient-card-2">
            <div class="card-body">
                <i class="fas fa-peso-sign fa-2x mb-2"></i>
                <h3>₱<?php echo number_format($todayStats['total_sales'], 2); ?></h3>
                <small>Sales Today</small>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card border-0 shadow-sm text-center gradient-card-3">
            <div class="card-body">
                <i class="fas fa-chart-line fa-2x mb-2"></i>
                <h3>₱<?php echo number_format($todayStats['avg_order_value'], 2); ?></h3>
                <small>Avg Order Value</small>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row mb-4">
    <!-- Daily Sales Chart -->
    <div class="col-lg-8 mb-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0">
                <h5 class="mb-0"><i class="fas fa-chart-line me-2 text-primary"></i>Daily Sales Trend (Last 30 Days)</h5>
            </div>
            <div class="card-body">
                <canvas id="salesChart" height="300"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Top Products -->
    <div class="col-lg-4 mb-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0">
                <h5 class="mb-0"><i class="fas fa-star me-2 text-warning"></i>Top Products Today</h5>
            </div>
            <div class="card-body">
                <?php if (empty($topProducts)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-box-open fa-2x mb-2"></i>
                        <p>No sales data for today</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($topProducts as $index => $product): ?>
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 30px; height: 30px; font-size: 12px;">
                                <?php echo $index + 1; ?>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-0" style="font-size: 0.9rem;"><?php echo htmlspecialchars($product['name']); ?></h6>
                                <small class="text-muted"><?php echo $product['total_sold']; ?> sold • ₱<?php echo number_format($product['revenue'], 2); ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Daily Sales Table -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0">
        <h5 class="mb-0"><i class="fas fa-table me-2 text-success"></i>Daily Sales Report</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Orders</th>
                        <th>Total Sales</th>
                        <th>Avg Order Value</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($dailySales)): ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">
                                <i class="fas fa-calendar-times fa-2x mb-2 d-block"></i>
                                No sales data available
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($dailySales as $sale): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($sale['sale_date'])); ?></td>
                                <td><span class="badge bg-primary"><?php echo $sale['order_count']; ?></span></td>
                                <td class="fw-bold text-success">₱<?php echo number_format($sale['total_sales'], 2); ?></td>
                                <td>₱<?php echo number_format($sale['total_sales'] / max(1, $sale['order_count']), 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Chart.js Script -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Sales Chart
const ctx = document.getElementById('salesChart').getContext('2d');
const salesChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_map(function($date) { return date('M d', strtotime($date)); }, $chartDates)); ?>,
        datasets: [{
            label: 'Sales (₱)',
            data: <?php echo json_encode(array_map('floatval', $chartSales)); ?>,
            borderColor: 'rgb(75, 192, 192)',
            backgroundColor: 'rgba(75, 192, 192, 0.1)',
            borderWidth: 2,
            fill: true,
            tension: 0.4
        }, {
            label: 'Orders',
            data: <?php echo json_encode(array_map('intval', $chartOrders)); ?>,
            borderColor: 'rgb(255, 99, 132)',
            backgroundColor: 'rgba(255, 99, 132, 0.1)',
            borderWidth: 2,
            fill: true,
            tension: 0.4,
            yAxisID: 'y1'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top',
            }
        },
        scales: {
            y: {
                type: 'linear',
                display: true,
                position: 'left',
                ticks: {
                    callback: function(value) {
                        return '₱' + value.toLocaleString();
                    }
                }
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                grid: {
                    drawOnChartArea: false,
                },
            }
        },
        interaction: {
            intersect: false,
            mode: 'index'
        }
    }
});
</script>

<?php
$content = ob_get_clean();
include 'views/layout.php';
?>
