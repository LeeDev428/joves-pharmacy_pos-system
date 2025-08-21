<?php
require_once '../config.php';
User::requireLogin();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'cashier') {
    header('Location: ../login.php');
    exit();
}

$page_title = 'Transaction History';
$db = Database::getInstance()->getConnection();

// Get filter parameters
$search = $_GET['search'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$status = $_GET['status'] ?? '';
$limit = $_GET['limit'] ?? 100;

// Build query with filters
$sql = "SELECT o.*, u.first_name, u.last_name, u.username, o.created_at as date,
               COUNT(oi.id) as item_count
        FROM orders o 
        LEFT JOIN users u ON o.user_id = u.id 
        LEFT JOIN order_items oi ON o.id = oi.order_id
        WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND (o.order_number LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.username LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($date_from) {
    $sql .= " AND DATE(o.created_at) >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $sql .= " AND DATE(o.created_at) <= ?";
    $params[] = $date_to;
}

if ($status) {
    $sql .= " AND o.status = ?";
    $params[] = $status;
}

$sql .= " GROUP BY o.id ORDER BY o.created_at DESC LIMIT " . (int)$limit;

$stmt = $db->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total count for pagination info
$countSql = "SELECT COUNT(DISTINCT o.id) FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE 1=1";
if ($search) {
    $countSql .= " AND (o.order_number LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.username LIKE ?)";
}
if ($date_from) {
    $countSql .= " AND DATE(o.created_at) >= ?";
}
if ($date_to) {
    $countSql .= " AND DATE(o.created_at) <= ?";
}
if ($status) {
    $countSql .= " AND o.status = ?";
}

$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$totalTransactions = $countStmt->fetchColumn();

$title = 'Transaction History';
ob_start();
?>
<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0">Transaction History</h2>
        <p class="text-muted mb-0">Manage and view all transaction records</p>
    </div>
    <div class="d-flex gap-2">
        <span class="badge bg-primary fs-6">Total: <?php echo number_format($totalTransactions); ?></span>
    </div>
</div>

<!-- Filters Card -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-0">
        <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filters & Search</h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Search</label>
                <input type="text" name="search" class="form-control" placeholder="Order number, cashier..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">From Date</label>
                <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($date_from); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">To Date</label>
                <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($date_to); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Show</label>
                <select name="limit" class="form-select">
                    <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50 records</option>
                    <option value="100" <?php echo $limit == 100 ? 'selected' : ''; ?>>100 records</option>
                    <option value="200" <?php echo $limit == 200 ? 'selected' : ''; ?>>200 records</option>
                </select>
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Transactions Table -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0">
        <h5 class="mb-0"><i class="fas fa-receipt me-2"></i>Transaction Records</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Order #</th>
                        <th>Date & Time</th>
                        <th>Cashier</th>
                        <th>Items</th>
                        <th>Total Amount</th>
                        <th>Status</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transactions)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <i class="fas fa-receipt fa-3x text-muted mb-3 d-block"></i>
                                <h5 class="text-muted">No transactions found</h5>
                                <p class="text-muted">Try adjusting your search criteria</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($transactions as $transaction): ?>
                            <tr>
                                <td>
                                    <strong class="text-primary"><?php echo htmlspecialchars($transaction['order_number']); ?></strong>
                                </td>
                                <td>
                                    <div>
                                        <strong><?php echo date('M d, Y', strtotime($transaction['date'])); ?></strong><br>
                                        <small class="text-muted"><?php echo date('h:i A', strtotime($transaction['date'])); ?></small>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($transaction['first_name'] . ' ' . $transaction['last_name']); ?></strong><br>
                                        <small class="text-muted">@<?php echo htmlspecialchars($transaction['username']); ?></small>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo $transaction['item_count']; ?> items</span>
                                </td>
                                <td>
                                    <strong class="text-success fs-6">₱<?php echo number_format($transaction['total_amount'], 2); ?></strong>
                                </td>
                                <td>
                                    <?php
                                    $statusClass = match($transaction['status']) {
                                        'completed' => 'bg-success',
                                        'pending' => 'bg-warning',
                                        'cancelled' => 'bg-danger',
                                        default => 'bg-secondary'
                                    };
                                    ?>
                                    <span class="badge <?php echo $statusClass; ?>"><?php echo ucfirst($transaction['status']); ?></span>
                                </td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-outline-primary" onclick="viewTransaction(<?php echo $transaction['id']; ?>)">
                                        <i class="fas fa-eye me-1"></i>View
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Transaction Details Modal -->
<div class="modal fade" id="transactionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Transaction Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="transactionDetails">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function viewTransaction(orderId) {
    const modal = new bootstrap.Modal(document.getElementById('transactionModal'));
    const detailsContainer = document.getElementById('transactionDetails');
    
    // Show loading
    detailsContainer.innerHTML = `
        <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
    `;
    
    modal.show();
    
    // Fetch transaction details
    fetch(`get_transaction_details.php?id=${orderId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const transaction = data.transaction;
                const items = data.items;
                
                detailsContainer.innerHTML = `
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6 class="text-muted">Transaction Information</h6>
                            <table class="table table-sm">
                                <tr><td><strong>Order Number:</strong></td><td>${transaction.order_number}</td></tr>
                                <tr><td><strong>Date & Time:</strong></td><td>${new Date(transaction.created_at).toLocaleString()}</td></tr>
                                <tr><td><strong>Cashier:</strong></td><td>${transaction.first_name} ${transaction.last_name}</td></tr>
                                <tr><td><strong>Status:</strong></td><td><span class="badge bg-${transaction.status === 'completed' ? 'success' : transaction.status === 'pending' ? 'warning' : 'danger'}">${transaction.status}</span></td></tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted">Payment Details</h6>
                            <table class="table table-sm">
                                <tr><td><strong>Subtotal:</strong></td><td>₱${parseFloat(transaction.subtotal || transaction.total_amount).toFixed(2)}</td></tr>
                                <tr><td><strong>Discount:</strong></td><td>₱${parseFloat(transaction.discount_amount || 0).toFixed(2)} (${transaction.discount_percent || 0}%)</td></tr>
                                <tr><td><strong>Tax:</strong></td><td>₱${parseFloat(transaction.tax_amount || 0).toFixed(2)}</td></tr>
                                <tr><td><strong>Total:</strong></td><td class="text-success fw-bold">₱${parseFloat(transaction.total_amount).toFixed(2)}</td></tr>
                                <tr><td><strong>Payment Method:</strong></td><td>${transaction.payment_method || 'Cash'}</td></tr>
                                <tr><td><strong>Amount Received:</strong></td><td>₱${parseFloat(transaction.amount_received || transaction.total_amount).toFixed(2)}</td></tr>
                            </table>
                        </div>
                    </div>
                    
                    <h6 class="text-muted">Order Items</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Brand Name</th>
                                    <th>Quantity</th>
                                    <th>Unit Price</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${items.map(item => `
                                    <tr>
                                        <td>${item.name}</td>
                                        <td>${item.quantity}</td>
                                        <td>₱${parseFloat(item.unit_price).toFixed(2)}</td>
                                        <td>₱${parseFloat(item.total_price).toFixed(2)}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                `;
            } else {
                detailsContainer.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Error loading transaction details: ${data.message}
                    </div>
                `;
            }
        })
        .catch(error => {
            detailsContainer.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Error loading transaction details. Please try again.
                </div>
            `;
        });
}
</script>
    .print-only { display: block !important; }
    body { font-size: 12px; }
    .content-card { box-shadow: none !important; border: 1px solid #000 !important; }
    .table { font-size: 11px; }
    @page { margin: 1cm; }
}
.print-only { display: none; }
</style>

<!-- Filter Section -->
<div class="row mb-4 no-print">
    <div class="col-12">
        <div class="content-card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-filter me-2"></i>Filter Transactions
                </h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Search</label>
                        <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Order number, cashier name...">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">From Date</label>
                        <input type="date" class="form-control" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">To Date</label>
                        <input type="date" class="form-control" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Limit</label>
                        <select class="form-select" name="limit">
                            <option value="25" <?php echo $limit == 25 ? 'selected' : ''; ?>>25</option>
                            <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50</option>
                            <option value="100" <?php echo $limit == 100 ? 'selected' : ''; ?>>100</option>
                            <option value="200" <?php echo $limit == 200 ? 'selected' : ''; ?>>200</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary btn-custom me-2">
                            <i class="fas fa-search me-2"></i>Filter
                        </button>
                        <button type="button" class="btn btn-outline-primary btn-custom" onclick="printTransactions()">
                            <i class="fas fa-print me-2"></i>Print List
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Transaction List -->
<div class="row">
    <div class="col-12">
        <div class="content-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-receipt me-2"></i>Transaction History
                </h5>
                <span class="badge bg-primary"><?php echo count($transactions); ?> transactions</span>
            </div>
            <div class="card-body">
                <div class="print-only">
                    <div class="text-center mb-4">
                        <h3>Joves Pharmacy POS</h3>
                        <h4>Transaction History Report</h4>
                        <p>Generated on: <?php echo date('F j, Y g:i A'); ?></p>
                        <p>Cashier: <?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></p>
                        <?php if ($date_from || $date_to): ?>
                            <p>Period: <?php echo $date_from ?: 'Beginning'; ?> to <?php echo $date_to ?: 'Now'; ?></p>
                        <?php endif; ?>
                        <hr>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Order #</th>
                                <th>Date & Time</th>
                                <th>Cashier</th>
                                <th>Total Amount</th>
                                <th>Status</th>
                                <th class="no-print">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($transactions)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        <i class="fas fa-info-circle me-2"></i>No transactions found
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($transactions as $t): ?>
                                    <tr>
                                        <td>
                                            <strong class="text-primary"><?php echo htmlspecialchars($t['order_number'] ?? 'ORD-' . $t['id']); ?></strong>
                                        </td>
                                        <td>
                                            <div><?php echo date('M j, Y', strtotime($t['date'])); ?></div>
                                            <small class="text-muted"><?php echo date('g:i A', strtotime($t['date'])); ?></small>
                                        </td>
                                        <td>
                                            <?php 
                                            $cashierName = trim(($t['first_name'] ?? '') . ' ' . ($t['last_name'] ?? ''));
                                            echo htmlspecialchars($cashierName ?: $t['username'] ?: 'Unknown');
                                            ?>
                                        </td>
                                        <td>
                                            <strong class="text-success"><?php echo formatCurrency($t['total_amount']); ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $t['status'] === 'completed' ? 'success' : ($t['status'] === 'pending' ? 'warning' : 'danger'); ?>">
                                                <?php echo ucfirst($t['status']); ?>
                                            </span>
                                        </td>
                                        <td class="no-print">
                                            <a href="?details=<?php echo $t['id']; ?>" class="btn btn-info btn-sm">
                                                <i class="fas fa-eye me-1"></i>View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Transaction Details Modal/Section -->
<?php if ($details && $selectedTransaction): ?>
<div class="row mt-4">
    <div class="col-12">
        <div class="content-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-file-invoice me-2"></i>Transaction Details - <?php echo htmlspecialchars($selectedTransaction['order_number'] ?? 'ORD-' . $selectedTransaction['id']); ?>
                </h5>
                <div class="no-print">
                    <button class="btn btn-outline-primary btn-sm me-2" onclick="printTransactionDetails()">
                        <i class="fas fa-print me-1"></i>Print Receipt
                    </button>
                    <a href="transactions.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-times me-1"></i>Close
                    </a>
                </div>
            </div>
            <div class="card-body" id="transactionDetails">
                <div class="print-only">
                    <div class="text-center mb-4">
                        <h3>Joves Pharmacy POS</h3>
                        <p>Transaction Receipt</p>
                        <hr style="border-top: 2px solid #000;">
                    </div>
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6>Transaction Information</h6>
                        <table class="table table-borderless table-sm">
                            <tr>
                                <td><strong>Order Number:</strong></td>
                                <td><?php echo htmlspecialchars($selectedTransaction['order_number'] ?? 'ORD-' . $selectedTransaction['id']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Date:</strong></td>
                                <td><?php echo date('F j, Y g:i A', strtotime($selectedTransaction['created_at'])); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Cashier:</strong></td>
                                <td><?php echo htmlspecialchars(trim(($selectedTransaction['first_name'] ?? '') . ' ' . ($selectedTransaction['last_name'] ?? '')) ?: 'Unknown'); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Status:</strong></td>
                                <td>
                                    <span class="badge bg-<?php echo $selectedTransaction['status'] === 'completed' ? 'success' : ($selectedTransaction['status'] === 'pending' ? 'warning' : 'danger'); ?>">
                                        <?php echo ucfirst($selectedTransaction['status']); ?>
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <h6>Items Purchased</h6>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Brand Name</th>
                                <th class="text-center">Qty</th>
                                <th class="text-end">Unit Price</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $subtotal = 0;
                            foreach ($details as $d): 
                                $itemTotal = $d['total_price'];
                                $subtotal += $itemTotal;
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($d['name']); ?></td>
                                    <td class="text-center"><?php echo $d['quantity']; ?></td>
                                    <td class="text-end"><?php echo formatCurrency($d['unit_price']); ?></td>
                                    <td class="text-end"><?php echo formatCurrency($itemTotal); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="3" class="text-end">Subtotal:</th>
                                <th class="text-end"><?php echo formatCurrency($subtotal); ?></th>
                            </tr>
                            <tr>
                                <th colspan="3" class="text-end">Tax (12%):</th>
                                <th class="text-end"><?php echo formatCurrency($selectedTransaction['tax_amount'] ?? ($subtotal * 0.12)); ?></th>
                            </tr>
                            <tr class="table-success">
                                <th colspan="3" class="text-end">TOTAL:</th>
                                <th class="text-end"><?php echo formatCurrency($selectedTransaction['total_amount']); ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                
                <div class="print-only text-center mt-4">
                    <hr style="border-top: 2px solid #000;">
                    <p>Thank you for your business!</p>
                    <p><small>This receipt was generated on <?php echo date('F j, Y g:i A'); ?></small></p>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function printTransactions() {
    window.print();
}

function printTransactionDetails() {
    // Create a new window for printing just the transaction details
    const printContent = document.getElementById('transactionDetails').innerHTML;
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
        <head>
            <title>Transaction Receipt</title>
            <style>
                body { font-family: Arial, sans-serif; font-size: 12px; margin: 20px; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
                th, td { padding: 8px; border: 1px solid #ddd; text-align: left; }
                th { background-color: #f5f5f5; font-weight: bold; }
                .text-center { text-align: center; }
                .text-end { text-align: right; }
                .table-borderless td { border: none; }
                .badge { padding: 3px 8px; border-radius: 3px; color: white; }
                .bg-success { background-color: #28a745; }
                .bg-warning { background-color: #ffc107; color: #000; }
                .bg-danger { background-color: #dc3545; }
                hr { border: 1px solid #000; margin: 15px 0; }
            </style>
        </head>
        <body>
            ${printContent}
        </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
}
</script>

<?php
$content = ob_get_clean();
include 'views/layout.php';
?>
