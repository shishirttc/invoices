<?php
// Simple and Robust Dynamic Base URL
$script_name = $_SERVER['SCRIPT_NAME'];
$dir = dirname($script_name);
$base_url = ($dir == DIRECTORY_SEPARATOR || $dir == '/') ? '' : $dir;

// If we are already in a subfolder, we need the parent for the base URL
if (in_array(basename($dir), ['clients', 'invoices', 'payments', 'services', 'pages', 'expenses'])) {
    $base_url = dirname($dir);
}
// Ensure base_url is empty string if it's just a slash (root)
if ($base_url == '/' || $base_url == '\\') {
    $base_url = '';
}

$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
?>
<aside id="mainSidebar" class="w-64 bg-gray-900 text-white flex-shrink-0 fixed inset-y-0 left-0 transform -translate-x-full md:translate-x-0 md:relative transition-transform duration-300 ease-in-out z-50 flex flex-col">
    <div class="p-6 border-b border-gray-700 text-center flex justify-between items-center md:block">
        <div>
            <h1 class="text-2xl font-bold text-blue-400">Siddik IT Ltd</h1>
            <p class="text-xs text-gray-400 mt-1">Invoice System</p>
        </div>
        <button id="closeSidebar" class="md:hidden text-gray-400 hover:text-white">
            <i class="fas fa-times fa-lg"></i>
        </button>
    </div>
    <nav class="flex-1 overflow-y-auto py-4 space-y-1 px-3">
        <a href="<?= $base_url ?>/dashboard.php" class="flex items-center gap-3 px-4 py-3 rounded-lg <?= ($current_page == 'dashboard.php' && ($current_dir != 'expenses' && $current_dir != 'clients' && $current_dir != 'invoices' && $current_dir != 'payments' && $current_dir != 'services' && $current_dir != 'pages')) ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' ?>">
            <i class="fas fa-home w-5"></i> Dashboard
        </a>
        <a href="<?= $base_url ?>/clients/list_clients.php" class="flex items-center gap-3 px-4 py-3 rounded-lg <?= $current_dir == 'clients' ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' ?>">
            <i class="fas fa-users w-5"></i> Clients
        </a>
        <a href="<?= $base_url ?>/pages/list_pages.php" class="flex items-center gap-3 px-4 py-3 rounded-lg <?= $current_dir == 'pages' ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' ?>">
            <i class="fas fa-file-alt w-5"></i> Pages
        </a>
        <a href="<?= $base_url ?>/services/list_services.php" class="flex items-center gap-3 px-4 py-3 rounded-lg <?= $current_dir == 'services' ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' ?>">
            <i class="fas fa-concierge-bell w-5"></i> Services
        </a>
        <a href="<?= $base_url ?>/invoices/list_invoices.php" class="flex items-center gap-3 px-4 py-3 rounded-lg <?= ($current_dir == 'invoices' && $current_page != 'dashboard.php') ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' ?>">
            <i class="fas fa-file-invoice-dollar w-5"></i> Invoices
        </a>
        <a href="<?= $base_url ?>/payments/payment_history.php" class="flex items-center gap-3 px-4 py-3 rounded-lg <?= $current_dir == 'payments' ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' ?>">
            <i class="fas fa-money-bill-wave w-5"></i> Payments
        </a>
        <a href="<?= $base_url ?>/logs.php" class="flex items-center gap-3 px-4 py-3 rounded-lg <?= $current_page == 'logs.php' ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' ?>">
            <i class="fas fa-history w-5"></i> Activity Logs
        </a>
        <a href="<?= $base_url ?>/expenses/dashboard.php" class="flex items-center gap-3 px-4 py-3 rounded-lg <?= $current_dir == 'expenses' ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' ?>">
            <i class="fas fa-chart-pie w-5"></i> Income & Expense
        </a>
        <a href="<?= $base_url ?>/logout.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-red-400 hover:bg-red-900 hover:text-white mt-auto">
            <i class="fas fa-sign-out-alt w-5"></i> Logout
        </a>
    </nav>
</aside>

<!-- Overlay for mobile -->
<div id="sidebarOverlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden md:hidden"></div>

<main class="flex-1 flex flex-col h-screen overflow-y-auto w-full relative">
    <!-- Mobile Header -->
    <header class="bg-gray-900 text-white p-4 flex justify-between items-center md:hidden sticky top-0 z-30">
        <h1 class="text-xl font-bold text-blue-400">Siddik IT Ltd.</h1>
        <button id="mobileMenuBtn" class="focus:outline-none text-gray-300 hover:text-white">
            <i class="fas fa-bars fa-lg"></i>
        </button>
    </header>

    <!-- Content Area -->
    <div class="p-4 md:p-6 bg-gray-100 flex-1">

<script>
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const closeSidebar = document.getElementById('closeSidebar');
    const mainSidebar = document.getElementById('mainSidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');

    function toggleSidebar() {
        mainSidebar.classList.toggle('-translate-x-full');
        sidebarOverlay.classList.toggle('hidden');
    }

    if(mobileMenuBtn) mobileMenuBtn.addEventListener('click', toggleSidebar);
    if(closeSidebar) closeSidebar.addEventListener('click', toggleSidebar);
    if(sidebarOverlay) sidebarOverlay.addEventListener('click', toggleSidebar);
</script>