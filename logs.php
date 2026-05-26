<?php
require_once 'config/database.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

// Search and Pagination
$search = isset($_GET['search']) ? $_GET['search'] : '';
$limit = 50;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

try {
    $where_clause = "";
    if ($search) {
        $where_clause = " WHERE al.action LIKE ? OR al.details LIKE ? OR u.name LIKE ? ";
    }

    // Total logs count with filter
    $count_sql = "SELECT COUNT(*) FROM activity_logs al LEFT JOIN users u ON al.user_id = u.id" . $where_clause;
    $stmt_count = $pdo->prepare($count_sql);
    if ($search) {
        $stmt_count->execute(["%$search%", "%$search%", "%$search%"]);
    } else {
        $stmt_count->execute();
    }
    $total_logs = $stmt_count->fetchColumn();
    $total_pages = ceil($total_logs / $limit);

    // Fetch logs with filter
    $sql = "SELECT al.*, u.name as user_name FROM activity_logs al LEFT JOIN users u ON al.user_id = u.id" . $where_clause . " ORDER BY al.created_at DESC LIMIT ? OFFSET ?";
    $stmt = $pdo->prepare($sql);
    
    $param_index = 1;
    if ($search) {
        $stmt->bindValue($param_index++, "%$search%", PDO::PARAM_STR);
        $stmt->bindValue($param_index++, "%$search%", PDO::PARAM_STR);
        $stmt->bindValue($param_index++, "%$search%", PDO::PARAM_STR);
    }
    
    // Bind limit and offset as integers to fix the syntax error
    $stmt->bindValue($param_index++, (int)$limit, PDO::PARAM_INT);
    $stmt->bindValue($param_index++, (int)$offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $logs = $stmt->fetchAll();
} catch (PDOException $e) {
    $logs = [];
    $total_pages = 0;
    $error = "Error fetching logs: " . $e->getMessage();
}
?>

<div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
    <h2 class="text-2xl font-bold text-gray-800">Activity Logs</h2>
    
    <form method="GET" class="relative w-full md:w-96">
        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
            <i class="fas fa-search"></i>
        </span>
        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search action, details or user..." class="pl-10 pr-4 py-2 border rounded-lg w-full focus:outline-none focus:ring-2 focus:ring-blue-500">
        <?php if($search): ?>
            <a href="logs.php" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600">
                <i class="fas fa-times-circle"></i>
            </a>
        <?php endif; ?>
    </form>
</div>

<?php if(isset($error)): ?>
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded">
        <p><?= $error ?></p>
    </div>
<?php endif; ?>

<div class="bg-white shadow rounded-lg overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-16">SL</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-48">Time</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-40">Performed By</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-32">Action</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Details</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if(empty($logs)): ?>
                    <tr><td colspan="5" class="px-6 py-4 text-center text-gray-500">No logs found.</td></tr>
                <?php else: ?>
                    <?php 
                    $sl = $offset + 1; 
                    foreach($logs as $log): 
                    ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?= $sl++ ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?= date('d M, Y h:i A', strtotime($log['created_at'])) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-medium">
                            <?= htmlspecialchars($log['user_name'] ?? 'System') ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                <?= strpos($log['action'], 'Delete') !== false ? 'bg-red-100 text-red-800' : 
                                   (strpos($log['action'], 'Add') !== false || strpos($log['action'], 'Create') !== false ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800') ?>">
                                <?= htmlspecialchars($log['action']) ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-700">
                            <?= htmlspecialchars($log['details']) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Pagination -->
<?php if ($total_pages > 1): ?>
<div class="mt-6 flex justify-center">
    <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
        <?php 
        $start_page = max(1, $page - 5);
        $end_page = min($total_pages, $page + 5);
        
        for($i=$start_page; $i <= $end_page; $i++): 
            $query_params = $_GET;
            $query_params['page'] = $i;
            $pagination_url = '?' . http_build_query($query_params);
        ?>
            <a href="<?= $pagination_url ?>" class="px-4 py-2 border text-sm font-medium <?= $i == $page ? 'bg-blue-50 border-blue-500 text-blue-600 z-10' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50' ?>">
                <?= $i ?>
            </a>
        <?php endfor; ?>
    </nav>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>