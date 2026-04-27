<?php
// Simple and Robust Dynamic Base URL
$script_name = $_SERVER['SCRIPT_NAME'];
$dir = dirname($script_name);
$base_url = ($dir == DIRECTORY_SEPARATOR || $dir == '/') ? '' : $dir;

// If we are already in a subfolder, we need the parent for the base URL
if (in_array(basename($dir), ['clients', 'invoices', 'payments', 'services', 'pages'])) {
    $base_url = dirname($dir);
}
// Ensure base_url is empty string if it's just a slash (root)
if ($base_url == '/' || $base_url == '\\') {
    $base_url = '';
}

$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
?>
<aside class="w-64 bg-gray-900 text-white flex-shrink-0 hidden md:flex flex-col">
    <div class="p-6 border-b border-gray-700 text-center">
        <h1 class="text-2xl font-bold text-blue-400">Siddik IT Ltd</h1>
        <p class="text-xs text-gray-400 mt-1">Invoice System</p>
    </div>
    <nav class="flex-1 overflow-y-auto py-4 space-y-1 px-3">
        <a href="<?= $base_url ?>/dashboard.php" class="flex items-center gap-3 px-4 py-3 rounded-lg <?= $current_page == 'dashboard.php' ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' ?>">
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
        <a href="<?= $base_url ?>/logout.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-red-400 hover:bg-red-900 hover:text-white mt-auto">
            <i class="fas fa-sign-out-alt w-5"></i> Logout
        </a>
    </nav>
</aside>

<main class="flex-1 flex flex-col h-screen overflow-y-auto w-full">
    <!-- Mobile Header -->
    <header class="bg-gray-900 text-white p-4 flex justify-between items-center md:hidden">
        <h1 class="text-xl font-bold text-blue-400">Siddik IT Ltd.</h1>
        <button id="mobileMenuBtn" class="focus:outline-none">
            <i class="fas fa-bars fa-lg"></i>
        </button>
    </header>

    <!-- Mobile Menu -->
    <div id="mobileMenu" class="hidden bg-gray-800 text-white md:hidden flex flex-col px-4 py-2 space-y-2">
        <a href="<?= $base_url ?>/dashboard.php" class="block py-2">Dashboard</a>
        <a href="<?= $base_url ?>/clients/list_clients.php" class="block py-2">Clients</a>
        <a href="<?= $base_url ?>/pages/list_pages.php" class="block py-2">Pages</a>
        <a href="<?= $base_url ?>/services/list_services.php" class="block py-2">Services</a>
        <a href="<?= $base_url ?>/invoices/list_invoices.php" class="block py-2">Invoices</a>
        <a href="<?= $base_url ?>/payments/payment_history.php" class="block py-2">Payments</a>
        <a href="<?= $base_url ?>/logout.php" class="block py-2 text-red-400">Logout</a>
    </div>

    <!-- Content Area -->
    <div class="p-6 bg-gray-100 flex-1">