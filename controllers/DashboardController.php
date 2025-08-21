<?php
class DashboardController {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    public function getStats() {
        try {
            $stats = [];
            
            // Total Sales
            $stmt = $this->db->query("SELECT SUM(total_amount) as total_sales FROM orders WHERE status = 'completed'");
            $stats['total_sales'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_sales'] ?? 0;
            
            // Total Products
            $stmt = $this->db->query("SELECT COUNT(*) as total_products FROM products WHERE status = 'active'");
            $stats['total_products'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_products'] ?? 0;
            
            // Total Orders
            $stmt = $this->db->query("SELECT COUNT(*) as total_orders FROM orders");
            $stats['total_orders'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_orders'] ?? 0;
            
            // Active Users (admin only)
            if (User::isAdmin()) {
                $stmt = $this->db->query("SELECT COUNT(*) as active_users FROM users WHERE status = 'active'");
                $stats['active_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['active_users'] ?? 0;
            }
            
            return $stats;
        } catch(PDOException $e) {
            return [];
        }
    }
    
    public function getRecentOrders($limit = 5) {
        try {
            $stmt = $this->db->prepare("SELECT o.*, u.first_name, u.last_name FROM orders o 
                                      JOIN users u ON o.user_id = u.id 
                                      ORDER BY o.created_at DESC LIMIT ?");
            $stmt->execute([$limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            return [];
        }
    }
    
    public function getLowStockProducts($limit = 5) {
        try {
            $stmt = $this->db->prepare("
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
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error fetching low stock products: " . $e->getMessage());
            return [];
        }
    }

    public function getNearExpiryProducts($limit = 5) {
        try {
            // First, let's see what products exist with expiry dates
            $debugStmt = $this->db->query("
                SELECT COUNT(*) as total_with_expiry, 
                       MIN(expiry_date) as earliest_expiry, 
                       MAX(expiry_date) as latest_expiry,
                       CURDATE() as today
                FROM products 
                WHERE expiry_date IS NOT NULL 
                AND status = 'active'
            ");
            $debug = $debugStmt->fetch(PDO::FETCH_ASSOC);
            error_log("Debug - Products with expiry dates: " . print_r($debug, true));
            
            // Main query - let's make it more inclusive
            $stmt = $this->db->prepare("
                SELECT p.*, c.name as category_name,
                       DATEDIFF(p.expiry_date, CURDATE()) as days_to_expiry,
                       p.expiry_date,
                       CURDATE() as today
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.id 
                WHERE p.expiry_date IS NOT NULL 
                AND p.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 5 DAY)
                AND p.status = 'active'
                ORDER BY p.expiry_date ASC, p.name ASC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Debug logging
            error_log("Near expiry query executed. Found " . count($result) . " products");
            if (!empty($result)) {
                error_log("First product: " . print_r($result[0], true));
            }
            
            return $result;
        } catch(PDOException $e) {
            error_log("Error fetching near expiry products: " . $e->getMessage());
            return [];
        }
    }
}
?>
