<?php
require_once '../config/database.php';

if (!isset($_GET['id'])) {
    header("Location: list_pages.php");
    exit;
}

$id = $_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM pages WHERE id = ?");
$stmt->execute([$id]);
$page = $stmt->fetch();

if (!$page) {
    header("Location: list_pages.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $client_id = $_POST['client_id'];
    $page_name = $_POST['page_name'];
    $page_url = $_POST['page_url'];

    $stmt = $pdo->prepare("UPDATE pages SET client_id = ?, page_name = ?, page_url = ? WHERE id = ?");
    $stmt->execute([$client_id, $page_name, $page_url, $id]);
    
    header("Location: list_pages.php");
    exit;
}

$clients = $pdo->query("SELECT id, name FROM clients ORDER BY name ASC")->fetchAll();

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<div class="mb-6">
    <h2 class="text-2xl font-bold text-gray-800">Edit Page</h2>
</div>

<div class="bg-white shadow rounded-lg p-6 max-w-xl">
    <form method="POST">
        <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2">Client *</label>
            <select name="client_id" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline bg-white">
                <option value="">-- Select Client --</option>
                <?php foreach($clients as $client): ?>
                    <option value="<?= $client['id'] ?>" <?= $client['id'] == $page['client_id'] ? 'selected' : '' ?>><?= htmlspecialchars($client['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2">Page Name *</label>
            <input type="text" name="page_name" value="<?= htmlspecialchars($page['page_name']) ?>" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
        </div>
        <div class="mb-6">
            <label class="block text-gray-700 text-sm font-bold mb-2">Page URL</label>
            <input type="url" name="page_url" value="<?= htmlspecialchars($page['page_url']) ?>" placeholder="https://facebook.com/..." class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
        </div>
        <div class="flex items-center justify-end">
            <a href="list_pages.php" class="text-gray-600 hover:text-gray-800 mr-4 font-bold">Cancel</a>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                Update Page
            </button>
        </div>
    </form>
</div>
<?php require_once '../includes/footer.php'; ?>