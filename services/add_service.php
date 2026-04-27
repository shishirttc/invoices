<?php
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $client_id = $_POST['client_id'];
    $page_id = $_POST['page_id'];
    $service_type = $_POST['service_type'];
    $charge = $_POST['charge'];

    $stmt = $pdo->prepare("INSERT INTO services (client_id, page_id, service_type, charge) VALUES (?, ?, ?, ?)");
    $stmt->execute([$client_id, $page_id, $service_type, $charge]);
    
    header("Location: list_services.php");
    exit;
}

$clients = $pdo->query("SELECT id, name FROM clients ORDER BY name ASC")->fetchAll();
// Fetch pages as a JSON object to update dynamically via JS
$pages = $pdo->query("SELECT id, client_id, page_name FROM pages")->fetchAll();
$pages_json = json_encode($pages);

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<div class="mb-6">
    <h2 class="text-2xl font-bold text-gray-800">Add New Service</h2>
</div>

<div class="bg-white shadow rounded-lg p-6 max-w-2xl">
    <form method="POST">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Client *</label>
                <select id="client_select" name="client_id" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline bg-white">
                    <option value="">-- Select Client --</option>
                    <?php foreach($clients as $client): ?>
                        <option value="<?= $client['id'] ?>"><?= htmlspecialchars($client['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Page *</label>
                <select id="page_select" name="page_id" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline bg-white">
                    <option value="">-- Select Client First --</option>
                </select>
            </div>
        </div>

        <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2">Service Type *</label>
            <input type="text" name="service_type" placeholder="e.g. Facebook Ads, SEO, Social Media Management" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
        </div>

        <div class="mb-6">
            <label class="block text-gray-700 text-sm font-bold mb-2">Charge (৳) *</label>
            <input type="number" step="0.01" name="charge" value="0.00" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
        </div>

        <div class="flex items-center justify-end">
            <a href="list_services.php" class="text-gray-600 hover:text-gray-800 mr-4 font-bold">Cancel</a>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                Save Service
            </button>
        </div>
    </form>
</div>

<script>
    const allPages = <?= $pages_json ?>;
    const clientSelect = document.getElementById('client_select');
    const pageSelect = document.getElementById('page_select');

    clientSelect.addEventListener('change', function() {
        const clientId = this.value;
        pageSelect.innerHTML = '<option value="">-- Select Page --</option>';
        
        if(clientId) {
            const filteredPages = allPages.filter(page => page.client_id == clientId);
            filteredPages.forEach(page => {
                const option = document.createElement('option');
                option.value = page.id;
                option.textContent = page.page_name;
                pageSelect.appendChild(option);
            });
        }
    });
</script>

<?php require_once '../includes/footer.php'; ?>