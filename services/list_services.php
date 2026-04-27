<?php
require_once '../config/database.php';

if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $pdo->prepare("DELETE FROM services WHERE id = ?")->execute([$id]);
    header("Location: list_services.php");
    exit;
}

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-bold text-gray-800">Services</h2>
    <a href="add_service.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
        <i class="fas fa-plus mr-2"></i> Add Service
    </a>
</div>

<div class="bg-white shadow rounded-lg overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
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
            while ($row = $stmt->fetch()):
            ?>
            <tr>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="font-medium text-gray-900"><?= htmlspecialchars($row['client_name']) ?></div>
                    <div class="text-sm text-gray-500"><?= htmlspecialchars($row['page_name']) ?></div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($row['service_type']) ?></td>
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
<?php require_once '../includes/footer.php'; ?>