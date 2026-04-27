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
<div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
    <h2 class="text-2xl font-bold text-gray-800">Facebook Pages</h2>
    
    <div class="relative w-full md:w-96">
        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
            <i class="fas fa-search"></i>
        </span>
        <input type="text" id="pageSearch" placeholder="Search client or page name..." class="pl-10 pr-4 py-2 border rounded-lg w-full focus:outline-none focus:ring-2 focus:ring-blue-500">
    </div>

    <a href="add_page.php" class="w-full md:w-auto bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded text-center whitespace-nowrap">
        <i class="fas fa-plus mr-2"></i> Add Page
    </a>
</div>

<div class="bg-white shadow rounded-lg overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200" id="pagesTable">
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
            <tr class="page-row">
                <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-900 search-client"><?= htmlspecialchars($row['client_name']) ?></td>
                <td class="px-6 py-4 whitespace-nowrap search-page"><?= htmlspecialchars($row['page_name']) ?></td>
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

<script>
    document.getElementById('pageSearch').addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        const rows = document.querySelectorAll('.page-row');
        
        rows.forEach(row => {
            const clientName = row.querySelector('.search-client').textContent.toLowerCase();
            const pageName = row.querySelector('.search-page').textContent.toLowerCase();
            
            if (clientName.includes(searchTerm) || pageName.includes(searchTerm)) {
                row.classList.remove('hidden');
            } else {
                row.classList.add('hidden');
            }
        });
    });
</script>
<?php require_once '../includes/footer.php'; ?>