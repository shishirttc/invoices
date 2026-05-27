<?php
session_start();
require_once 'config/database.php';

// If already logged in, go to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['username'] = $user['username'];
        
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Invalid username or password!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Siddik IT Ltd.</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-900 flex items-center justify-center h-screen">
    <div class="bg-white p-8 rounded-lg shadow-2xl w-full max-w-md">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-blue-600">Siddik IT Ltd.</h1>
            <p class="text-gray-500 mt-2">Invoice Management System</p>
        </div>

        <?php if($error): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded">
                <p><?= $error ?></p>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-6">
                <label class="block text-gray-700 text-sm font-bold mb-2">Username</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                        <i class="fas fa-user"></i>
                    </span>
                    <input type="text" name="username" required placeholder="Enter username" class="pl-10 w-full px-4 py-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50">
                </div>
            </div>

            <div class="mb-8">
                <label class="block text-gray-700 text-sm font-bold mb-2">Password</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                        <i class="fas fa-lock"></i>
                    </span>
                    <input type="password" name="password" required placeholder="Enter password" class="pl-10 w-full px-4 py-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50">
                </div>
            </div>

            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-lg transition duration-200">
                Sign In
            </button>
        </form>

        <div class="mt-8 text-center text-gray-400 text-xs uppercase tracking-widest font-bold">
            222, Kadirganj, Boalia, Rajshahi, Bangladesh
        </div>
    </div>
</body>
</html>