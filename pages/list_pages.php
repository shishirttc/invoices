<?php
require_once '../config/database.php';

if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $pdo->prepare("DELETE FROM pages WHERE id = ?")->execute([$id]);
    header("Location: list_pages.php");
    exit;
}

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-bold text-gray-800">Facebook Pages</h2>
    <a href="add_page.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
        <i class="fas fa-plus mr-2"></i> Add Page
    </a>
</div>

<div class="bg-white shadow rounded-lg overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Client</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Page Name</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Page URL</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
            <?php
            $stmt = $pdo->query("SELECT p.*, c.name as client_name FROM pages p JOIN clients c ON p.client_id = c.id ORDER BY p.id DESC");
            while ($row = $stmt->fetch()):
            ?>
            <tr>
                <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-900"><?= htmlspecialchars($row['client_name']) ?></td>
                <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($row['page_name']) ?></td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <?php if($row['page_url']): ?>
                        <a href="<?= htmlspecialchars($row['page_url']) ?>" target="_blank" class="text-blue-500 hover:underline">View Page <i class="fas fa-external-link-alt text-xs"></i></a>
                    <?php endif; ?>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                    <a href="edit_page.php?id=<?= $row['id'] ?>" class="text-indigo-600 hover:text-indigo-900 mr-3"><i class="fas fa-edit"></i> Edit</a>
                    <a href="list_pages.php?delete=<?= $row['id'] ?>" class="text-red-600 hover:text-red-900" onclick="return confirm('Are you sure you want to delete this page?');"><i class="fas fa-trash"></i> Delete</a>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>
<?php require_once '../includes/footer.php'; ?>