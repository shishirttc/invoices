<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
    // Fetch details for logging
    $stmt = $pdo->prepare("
        SELECT s.service_type, s.total, c.name as client_name, p.page_name 
        FROM services s 
        JOIN clients c ON s.client_id = c.id 
        JOIN pages p ON s.page_id = p.id 
        WHERE s.id = ?
    ");
    $stmt->execute([$id]);
    $service = $stmt->fetch();

    if ($service) {
        $pdo->prepare("DELETE FROM services WHERE id = ?")->execute([$id]);
        log_activity($pdo, "Delete Service", "Deleted service: {$service['service_type']} ({$service['total']} BDT) for {$service['client_name']} ({$service['page_name']})");
    }

    header("Location: list_services.php");
    exit;
}

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
    <h2 class="text-2xl font-bold text-gray-800">Services</h2>
    
    <div class="relative w-full md:w-96">
        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
            <i class="fas fa-search"></i>
        </span>
        <input type="text" id="serviceSearch" placeholder="Search client, page or service..." class="pl-10 pr-4 py-2 border rounded-lg w-full focus:outline-none focus:ring-2 focus:ring-blue-500">
    </div>

    <a href="add_service.php" class="w-full md:w-auto bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded text-center whitespace-nowrap">
        <i class="fas fa-plus mr-2"></i> Add Service
    </a>
</div>

<div class="bg-white shadow rounded-lg overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200" id="servicesTable">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase w-16">SL</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Client / Page</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Service Type</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Charge</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
            <?php
            $stmt = $pdo->query("
                SELECT s.*, c.name as client_name, p.page_name 
                FROM services s 
                JOIN clients c ON s.client_id = c.id 
                JOIN pages p ON s.page_id = p.id 
                ORDER BY s.id DESC
            ");
            $sl = 1;
            while ($row = $stmt->fetch()):
            ?>
            <tr class="service-row">
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= $sl++ ?></td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="font-medium text-gray-900 search-client"><?= htmlspecialchars($row['client_name']) ?></div>
                    <div class="text-sm text-gray-500 search-page"><?= htmlspecialchars($row['page_name']) ?></div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap search-type"><?= htmlspecialchars($row['service_type']) ?></td>
                <td class="px-6 py-4 whitespace-nowrap font-semibold">৳<?= number_format($row['total'], 2) ?></td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                    <a href="edit_service.php?id=<?= $row['id'] ?>" class="text-indigo-600 hover:text-indigo-900 mr-3" title="Edit"><i class="fas fa-edit"></i></a>
                    <a href="list_services.php?delete=<?= $row['id'] ?>" class="text-red-600 hover:text-red-900" onclick="return confirm('Are you sure you want to delete this service?');" title="Delete"><i class="fas fa-trash"></i></a>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<script>
    document.getElementById('serviceSearch').addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        const rows = document.querySelectorAll('.service-row');
        
        rows.forEach(row => {
            const clientName = row.querySelector('.search-client').textContent.toLowerCase();
            const pageName = row.querySelector('.search-page').textContent.toLowerCase();
            const serviceType = row.querySelector('.search-type').textContent.toLowerCase();
            
            if (clientName.includes(searchTerm) || pageName.includes(searchTerm) || serviceType.includes(searchTerm)) {
                row.classList.remove('hidden');
            } else {
                row.classList.add('hidden');
            }
        });
    });
</script>
<?php require_once '../includes/footer.php'; ?>