<?php
ob_start();

// Function to ensure directory exists and is writable
function ensureWritableDir($dir) {
    if (!file_exists($dir)) {
        if (!@mkdir($dir, 0777, true)) {
            error_log("Failed to create directory: $dir");
            return false;
        }
    }
    if (!is_writable($dir)) {
        if (!@chmod($dir, 0777)) {
            error_log("Directory not writable: $dir");
            return false;
        }
    }
    return true;
}

// Set up temporary directory
$customTmpDir = __DIR__ . '/tmp';
$tmpDir = $customTmpDir;

if (!ensureWritableDir($customTmpDir)) {
    error_log("Warning: Using system default temp directory due to failure with: $customTmpDir");
    $tmpDir = sys_get_temp_dir();
}

if (!is_writable($tmpDir)) {
    die("Fatal error: Temporary directory not writable: $tmpDir. Please check server permissions.");
}

// File definitions
$usersFile = "users.txt";
$ordersFile = "orders.json";
$productsFile = "products.txt";

// Initialize files if they don't exist
if (!file_exists($usersFile)) {
    file_put_contents($usersFile, "admin@example.com,admin123,admin\nuser@example.com,user123,user\njhames.martin@example.com,Jhames123,user\n");
}
if (!file_exists($ordersFile)) {
    file_put_contents($ordersFile, json_encode([
        [
            "name" => "Jhames BOT",
            "email" => "jhameseditingph@gmail.com",
            "address" => "Jhajsisu",
            "payment" => "COD",
            "cart" => [["name" => "Premium Ballpens", "price" => 25, "quantity" => 6]],
            "total" => 150,
            "timestamp" => "2025-03-07T12:00:00Z"
        ]
    ], JSON_PRETTY_PRINT));
}
if (!file_exists($productsFile)) {
    file_put_contents($productsFile, "1,Premium Ballpens,25,ballpen.webp\n2,Pencil Set,350,pencil set.jpg\n3,Notebooks,40,notebook.webp\n4,Scientific Calculator,1000,scie cal.jpg\n5,Flash Drive,230,flashdrive.jpg\n");
}

// Load data with fallback
$usersData = file($usersFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
$ordersData = json_decode(file_get_contents($ordersFile), true) ?: [];
$productsData = file($productsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

$productsArray = [];
foreach ($productsData as $line) {
    // Remove any trailing '[\n]' or similar artifacts if present
    $line = rtrim($line, "[\n]");
    $parts = explode(",", $line, 4);
    if (count($parts) === 4) {
        list($id, $name, $price, $image) = $parts;
        $productsArray[] = [
            "id" => (int)$id,
            "name" => trim($name),
            "price" => floatval($price),
            "image" => trim($image)
        ];
    }
}

// Handle delete operations
if (isset($_GET['delete_user'])) {
    $index = (int)$_GET['delete_user'];
    unset($usersData[$index]);
    file_put_contents($usersFile, implode("\n", array_values($usersData)) . "\n");
    header("Location: admin_dashboard.php");
    exit;
}

if (isset($_GET['delete_order'])) {
    $index = (int)$_GET['delete_order'];
    unset($ordersData[$index]);
    file_put_contents($ordersFile, json_encode(array_values($ordersData), JSON_PRETTY_PRINT));
    header("Location: admin_dashboard.php");
    exit;
}

if (isset($_GET['delete_product'])) {
    $id = (int)$_GET['delete_product'];
    $productsData = array_filter($productsData, fn($line) => (int)explode(",", rtrim($line, "[\n]"))[0] !== $id);
    file_put_contents($productsFile, implode("\n", array_values($productsData)) . "\n");
    header("Location: admin_dashboard.php");
    exit;
}

// Handle POST requests
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Add new user
    if (isset($_POST["newEmail"], $_POST["newPassword"], $_POST["newRole"])) {
        $newUser = implode(",", [
            trim($_POST["newEmail"] ?? ''),
            trim($_POST["newPassword"] ?? ''),
            trim($_POST["newRole"] ?? '')
        ]);
        file_put_contents($usersFile, implode("\n", $usersData) . "\n" . $newUser . "\n");
        header("Location: admin_dashboard.php");
        exit;
    }

    // Update users
    if (isset($_POST["users"])) {
        $updatedUsers = [];
        foreach ($_POST["users"] as $user) {
            $updatedUsers[] = implode(",", [
                trim($user['email'] ?? ''),
                trim($user['password'] ?? ''),
                trim($user['role'] ?? '')
            ]);
        }
        file_put_contents($usersFile, implode("\n", $updatedUsers) . "\n");
        header("Location: admin_dashboard.php");
        exit;
    }

    // Add new product
    if (isset($_POST["newProductName"], $_POST["newProductPrice"], $_POST["newProductImageUrl"])) {
        $imageInput = trim($_POST["newProductImageUrl"] ?? '');
        
        if (empty($imageInput)) {
            die("Image URL or base64 data is required.");
        }
        
        if (!preg_match('/^data:image\/[a-z]+;base64,/', $imageInput) && 
            !filter_var($imageInput, FILTER_VALIDATE_URL) && 
            !file_exists($imageInput)) {
            die("Invalid image input. Please provide a valid URL, file path, or base64 data URL.");
        }

        $newId = empty($productsArray) ? 1 : max(array_column($productsArray, 'id')) + 1;
        $newProduct = implode(",", [
            $newId,
            trim($_POST["newProductName"] ?? ''),
            floatval($_POST["newProductPrice"] ?? 0),
            $imageInput
        ]);
        // Append with just a newline, no brackets
        file_put_contents($productsFile, file_get_contents($productsFile) . $newProduct . "\n");
        header("Location: admin_dashboard.php?success=1");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Shop Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #1A1A1A;
            --accent-color: #FF4D4D;
            --secondary-color: #28a745;
            --danger-color: #dc3545;
            --light-bg: #F5F6FA;
            --dark-text: #2D3436;
            --sidebar-bg: #1A2526;
            --border-color: #E4E7EB;
            --transition: all 0.3s ease;
            --shadow-sm: 0 2px 5px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 15px rgba(0,0,0,0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', system-ui, sans-serif;
        }

        body {
            background: var(--light-bg);
            color: var(--dark-text);
            min-height: 100vh;
            overflow-x: hidden;
            position: relative;
        }

        .sidebar {
            width: 260px;
            background: var(--sidebar-bg);
            color: white;
            position: fixed;
            top: 0;
            left: -260px;
            height: 100%;
            padding: 1.5rem;
            transition: var(--transition);
            z-index: 1001;
            box-shadow: var(--shadow-md);
        }

        .sidebar.active {
            left: 0;
        }

        .sidebar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar h2 {
            font-size: 1.4rem;
            font-weight: 600;
            color: var(--accent-color);
        }

        .close-btn {
            background: none;
            border: none;
            color: white;
            font-size: 1.4rem;
            cursor: pointer;
            transition: var(--transition);
        }

        .close-btn:hover {
            color: var(--accent-color);
        }

        .sidebar-nav {
            list-style: none;
        }

        .sidebar-nav li {
            margin-bottom: 0.75rem;
        }

        .sidebar-nav a {
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            border-radius: 6px;
            transition: var(--transition);
            font-size: 0.95rem;
        }

        .sidebar-nav a:hover, .sidebar-nav a.active {
            background: rgba(255,255,255,0.1);
            color: var(--accent-color);
        }

        .sidebar-nav i {
            margin-right: 0.75rem;
            width: 20px;
            text-align: center;
        }

        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: none;
            z-index: 1000;
        }

        .overlay.active {
            display: block;
        }

        .main-content {
            padding: 1.5rem;
            width: 100%;
            transition: var(--transition);
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            background: white;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            box-shadow: var(--shadow-sm);
        }

        .header h1 {
            color: var(--primary-color);
            font-size: 1.5rem;
            font-weight: 600;
        }

        .menu-btn {
            background: var(--primary-color);
            color: white;
            padding: 0.6rem 1rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            transition: var(--transition);
        }

        .menu-btn:hover {
            background: var(--accent-color);
        }

        .section {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: var(--shadow-sm);
            margin-bottom: 1.5rem;
        }

        h3 {
            color: var(--primary-color);
            margin-bottom: 1rem;
            font-weight: 600;
            font-size: 1.25rem;
        }

        .table-container {
            overflow-x: auto;
            margin-bottom: 1rem;
            border-radius: 6px;
            border: 1px solid var(--border-color);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }

        th, td {
            padding: 0.75rem 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.9rem;
        }

        th {
            background: var(--primary-color);
            color: white;
            font-weight: 600;
        }

        td input, td select {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 0.9rem;
        }

        td input:focus, td select:focus {
            outline: none;
            border-color: var(--accent-color);
            box-shadow: 0 0 5px rgba(255,107,107,0.2);
        }

        tr:last-child td {
            border-bottom: none;
        }

        .btn {
            padding: 0.6rem 1.2rem;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
            cursor: pointer;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }

        .btn-primary { background: var(--primary-color); color: white; }
        .btn-success { background: var(--secondary-color); color: white; }
        .btn-danger { background: var(--danger-color); color: white; }
        .btn:hover { transform: translateY(-1px); box-shadow: var(--shadow-sm); }

        .form-container {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .form-container input, .form-container select {
            flex: 1;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            min-width: 0;
            font-size: 0.9rem;
        }

        .form-container input:focus, .form-container select:focus {
            border-color: var(--accent-color);
            outline: none;
            box-shadow: 0 0 5px rgba(255,107,107,0.2);
        }

        .list-container {
            max-height: 60vh;
            overflow-y: auto;
            padding: 1rem;
            background: #f9f9f9;
            border-radius: 6px;
            border: 1px solid var(--border-color);
        }

        .list-item {
            padding: 1rem;
            margin-bottom: 1rem;
            background: white;
            border-radius: 6px;
            box-shadow: var(--shadow-sm);
            font-size: 0.9rem;
        }

        .list-item:last-child {
            margin-bottom: 0;
        }

        .product-image {
            max-width: 100px;
            height: auto;
            margin-top: 0.5rem;
        }

        .file-drop-area {
            border: 2px dashed var(--border-color);
            padding: 2rem;
            text-align: center;
            border-radius: 6px;
            margin-bottom: 1rem;
            cursor: pointer;
            transition: var(--transition);
        }

        .file-drop-area.is-dragover {
            border-color: var(--accent-color);
            background: rgba(255, 107, 107, 0.1);
        }

        .file-drop-area.preview-loading {
            opacity: 0.7;
            pointer-events: none;
        }

        .file-drop-area.preview-loaded .file-input-preview {
            display: block;
        }

        .file-input-preview {
            display: none;
            max-width: 100%;
            margin: 1rem auto 0;
            border-radius: 6px;
        }

        .base64-result {
            width: 100%;
            height: 150px;
            margin: 10px 0;
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            resize: vertical;
        }

        .error-message {
            color: #721c24;
            background: #f8d7da;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 1rem;
        }

        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 2000;
        }

        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #fff;
            border-top: 4px solid var(--accent-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .success-message {
            color: var(--secondary-color);
            margin-bottom: 1rem;
            padding: 0.75rem;
            background: #e6ffe6;
            border-radius: 6px;
            display: none;
        }

        @media (min-width: 1024px) {
            .sidebar {
                left: 0;
            }
            .main-content {
                margin-left: 260px;
                width: calc(100% - 260px);
            }
            .menu-btn {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }
            .header {
                padding: 0.75rem 1rem;
            }
            .section {
                padding: 1rem;
            }
            .form-container {
                flex-direction: column;
            }
            .form-container input, .form-container select {
                width: 100%;
            }
            .btn {
                width: 100%;
                justify-content: center;
            }
            .list-container {
                max-height: 50vh;
            }
        }

        @media (max-width: 480px) {
            .sidebar {
                width: 80%;
            }
            .header h1 {
                font-size: 1.3rem;
            }
            h3 {
                font-size: 1.1rem;
            }
            th, td {
                padding: 0.5rem 0.75rem;
                font-size: 0.85rem;
            }
            td input, td select {
                font-size: 0.85rem;
            }
            .list-item {
                font-size: 0.85rem;
            }
            .btn {
                font-size: 0.85rem;
                padding: 0.5rem 1rem;
            }
            .product-image {
                max-width: 80px;
            }
            .base64-result {
                height: 100px;
            }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>E-Shop Admin</h2>
            <button class="close-btn"><i class="fas fa-times"></i></button>
        </div>
        <ul class="sidebar-nav">
            <li><a href="#users" class="active"><i class="fas fa-users"></i> Users</a></li>
            <li><a href="#products"><i class="fas fa-box"></i> Products</a></li>
            <li><a href="#orders"><i class="fas fa-shopping-cart"></i> Orders</a></li>
            <li><a href="#image-to-base64"><i class="fas fa-image"></i> Image to Base64</a></li>
            <li><a href="index.html"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <div class="overlay"></div>

    <div class="main-content">
        <?php if (!is_writable($tmpDir)): ?>
            <div class="error-message">
                Warning: Temporary directory (<?php echo htmlspecialchars($tmpDir); ?>) is not writable. 
                Some features may not work correctly. Please check server permissions.
            </div>
        <?php endif; ?>

        <div class="header">
            <h1>Shop Admin Dashboard</h1>
            <button class="menu-btn"><i class="fas fa-bars"></i> Menu</button>
        </div>

        <div class="section" id="users">
            <h3>Manage Users</h3>
            <form method="POST" onsubmit="showLoading()">
                <div class="table-container">
                    <table>
                        <tr>
                            <th>Email</th>
                            <th>Password</th>
                            <th>Role</th>
                            <th>Actions</th>
                        </tr>
                        <?php foreach ($usersData as $index => $line): 
                            list($email, $password, $role) = explode(",", $line);
                        ?>
                        <tr>
                            <td><input type="text" name="users[<?= $index ?>][email]" value="<?= htmlspecialchars($email) ?>" required></td>
                            <td><input type="password" name="users[<?= $index ?>][password]" value="<?= htmlspecialchars($password) ?>" required></td>
                            <td>
                                <select name="users[<?= $index ?>][role]">
                                    <option value="admin" <?= $role == 'admin' ? 'selected' : '' ?>>Admin</option>
                                    <option value="user" <?= $role == 'user' ? 'selected' : '' ?>>User</option>
                                </select>
                            </td>
                            <td>
                                <a href="?delete_user=<?= $index ?>" class="btn btn-danger" onclick="return confirmDelete('user')"><i class="fas fa-trash"></i> Delete</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
            </form>

            <h3>Add User</h3>
            <form method="POST" class="form-container" onsubmit="showLoading()">
                <input type="text" name="newEmail" placeholder="Enter email" required>
                <input type="password" name="newPassword" placeholder="Enter password" required>
                <select name="newRole">
                    <option value="user">User</option>
                    <option value="admin">Admin</option>
                </select>
                <button type="submit" class="btn btn-success"><i class="fas fa-plus"></i> Add User</button>
            </form>
        </div>

        <div class="section" id="products">
            <h3>Manage Products</h3>
            <div class="list-container">
                <?php if (empty($productsArray)): ?>
                    <p>No products available.</p>
                <?php else: ?>
                    <?php foreach ($productsArray as $product): ?>
                        <div class="list-item">
                            <strong>ID:</strong> <?= htmlspecialchars($product['id']) ?><br>
                            <strong>Name:</strong> <?= htmlspecialchars($product['name']) ?><br>
                            <strong>Price:</strong> ₱<?= number_format($product['price'], 2) ?><br>
                            <strong>Image:</strong><br>
                            <img src="<?= htmlspecialchars($product['image']) ?>" alt="<?= htmlspecialchars($product['name']) ?>" class="product-image" onerror="this.src='https://via.placeholder.com/100?text=Image+Not+Found';"><br>
                            <a href="?delete_product=<?= $product['id'] ?>" class="btn btn-danger" onclick="return confirmDelete('product')"><i class="fas fa-trash"></i> Delete</a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <h3>Add Product</h3>
            <div class="success-message" id="successMessage">Product added successfully!</div>
            <form method="POST" class="form-container" onsubmit="showLoading()">
                <input type="text" name="newProductName" placeholder="Product Name" required>
                <input type="number" name="newProductPrice" placeholder="Price" step="0.01" required>
                <input type="text" name="newProductImageUrl" placeholder="Image URL or Base64" required>
                <button type="submit" class="btn btn-success"><i class="fas fa-plus"></i> Add Product</button>
            </form>
        </div>

        <div class="section" id="orders">
            <h3>Manage Orders</h3>
            <div class="list-container">
                <?php if (empty($ordersData)): ?>
                    <p>No orders available.</p>
                <?php else: ?>
                    <?php foreach ($ordersData as $index => $order): ?>
                        <div class="list-item">
                            <strong>Order #<?= $index + 1 ?></strong><br>
                            <strong>Name:</strong> <?= htmlspecialchars($order['name']) ?><br>
                            <strong>Email:</strong> <?= htmlspecialchars($order['email']) ?><br>
                            <strong>Address:</strong> <?= htmlspecialchars($order['address']) ?><br>
                            <strong>Payment:</strong> <?= htmlspecialchars($order['payment']) ?><br>
                            <strong>Items:</strong><br>
                            <?php foreach ($order['cart'] as $item): ?>
                                - <?= htmlspecialchars($item['name']) ?> (<?= $item['quantity'] ?>x) - ₱<?= number_format($item['price'] * $item['quantity'], 2) ?><br>
                            <?php endforeach; ?>
                            <strong>Total:</strong> ₱<?= number_format($order['total'], 2) ?><br>
                            <strong>Date:</strong> <?= date('Y-m-d H:i', strtotime($order['timestamp'])) ?><br>
                            <a href="?delete_order=<?= $index ?>" class="btn btn-danger" onclick="return confirmDelete('order')"><i class="fas fa-trash"></i> Delete</a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="section" id="image-to-base64">
            <h3>Image to Base64 Converter</h3>
            <input type="file" id="file" class="d-none" accept="image/*">
            <div class="file-drop-area">
                <p>Drag & drop an image here or click to select<br>(or paste from clipboard)</p>
                <img id="preview" class="file-input-preview" src="https://via.placeholder.com/150" alt="Preview">
            </div>
            <div class="mb-3">
                <input type="text" id="image-url" class="form-control" placeholder="Or enter image URL">
                <div id="image-url-errors"></div>
            </div>
            <div class="mb-3">
                <label>Output Format:</label>
                <div id="base64-output-types" class="btn-group" role="group">
                    <input type="radio" class="btn-check" name="output-type" id="base64-output-type-text" value="text" checked>
                    <label class="btn btn-outline-primary" for="base64-output-type-text">Text</label>
                    <input type="radio" class="btn-check" name="output-type" id="base64-output-type-img" value="img">
                    <label class="btn btn-outline-primary" for="base64-output-type-img">HTML</label>
                    <input type="radio" class="btn-check" name="output-type" id="base64-output-type-md" value="md">
                    <label class="btn btn-outline-primary" for="base64-output-type-md">Markdown</label>
                </div>
            </div>
            <div class="mb-3">
                <textarea class="base64-result" id="base64-output" readonly placeholder="Base64 output will appear here"></textarea>
                <div class="d-flex justify-content-between">
                    <small id="base64-output-length">0 chars</small>
                    <div>
                        <button id="reset" class="btn btn-danger me-2"><i class="fas fa-trash"></i> Reset</button>
                        <button id="copy" class="btn btn-primary"><i class="fas fa-copy"></i> Copy</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.querySelector('.overlay');
        const menuBtn = document.querySelector('.menu-btn');
        const closeBtn = document.querySelector('.close-btn');
        const successMessage = document.getElementById('successMessage');

        menuBtn.addEventListener('click', () => {
            sidebar.classList.add('active');
            overlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        });

        closeBtn.addEventListener('click', () => {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
            document.body.style.overflow = 'auto';
        });

        overlay.addEventListener('click', () => {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
            document.body.style.overflow = 'auto';
        });

        function showLoading() {
            document.getElementById('loadingOverlay').style.display = 'flex';
            setTimeout(() => {
                document.getElementById('loadingOverlay').style.display = 'none';
            }, 1000);
        }

        function confirmDelete(type) {
            showLoading();
            return confirm(`Delete this ${type}?`);
        }

        document.querySelectorAll('.sidebar-nav a').forEach(link => {
            link.addEventListener('click', function(e) {
                if (!this.href.includes('index.html')) {
                    e.preventDefault();
                    document.querySelectorAll('.sidebar-nav a').forEach(l => l.classList.remove('active'));
                    this.classList.add('active');
                    document.querySelectorAll('.section').forEach(section => section.style.display = 'none');
                    document.querySelector(this.getAttribute('href')).style.display = 'block';
                    sidebar.classList.remove('active');
                    overlay.classList.remove('active');
                    document.body.style.overflow = 'auto';
                }
            });
        });

        document.querySelectorAll('.section').forEach(section => section.style.display = 'none');
        document.querySelector('#users').style.display = 'block';

        <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
            successMessage.style.display = 'block';
            setTimeout(() => {
                successMessage.style.display = 'none';
            }, 3000);
        <?php endif; ?>

        let fileInput = $("#file");
        let fileDropArea = $(".file-drop-area");
        let fileInputPreview = $(".file-input-preview");
        let dummyImageSrc = fileInputPreview.attr("src");
        let imageURLInput = $("#image-url");
        let imageURLErrors = $("#image-url-errors");
        let resetBtn = $("#reset");
        let base64Output = $("#base64-output");
        let base64OutputLength = $("#base64-output-length");
        let base64OutputTypeInputs = $("#base64-output-types .btn-check");
        let copyBtn = $("#copy");

        resetBtn.click(() => resetUI(true, true));

        fileInput.change(function () {
            resetUI(true);
            if (this.files.length) {
                convertAndPreview(this.files[0]);
            }
        });

        imageURLInput.on("input", () => {
            let url = imageURLInput.val();
            if (url.length != 0) {
                resetUI(false, true);
                fetch(url)
                    .then(res => res.blob())
                    .then(blob => {
                        let downloadedFile = new File([blob], "download");
                        convertAndPreview(downloadedFile);
                    })
                    .catch(error => {
                        imageURLErrors.html(`
                            <div class="alert alert-danger alert-dismissible mb-0">
                                <span>${error.message}. Invalid URL, Not Found or blocked by CORS policy.</span>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        `);
                    });
            }
        });

        fileDropArea.on("click", () => fileInput.click());

        fileDropArea
            .on("drag dragstart dragend dragover dragenter dragleave drop", function (e) {
                e.preventDefault();
                e.stopPropagation();
            })
            .on("dragover dragenter", function () {
                fileDropArea.addClass("is-dragover");
            })
            .on("dragleave dragend drop", function () {
                fileDropArea.removeClass("is-dragover");
            })
            .on("drop", function (e) {
                if (e.originalEvent.dataTransfer.files.length) {
                    convertAndPreview(e.originalEvent.dataTransfer.files[0]);
                }
            });

        $(window).on("paste", function(e) {
            if (e.originalEvent.clipboardData.files.length) {
                e.preventDefault();
                resetUI(true, true);
                convertAndPreview(e.originalEvent.clipboardData.files[0]);
            }
        });

        base64OutputTypeInputs.change(function (e) {
            if (this.checked) {
                const base64 = fileInputPreview.attr("src");
                if (base64 === dummyImageSrc) return;
                updateBase64Output(
                    formatBase64(base64, getSelectedOutputType())
                );
            }
        });

        copyBtn.on("click", () => navigator.clipboard.writeText(base64Output.val()));

        function resetUI(clearURL = false, clearFile = false) {
            fileDropArea.removeClass("preview-loading");
            fileDropArea.removeClass("preview-loaded");
            fileInputPreview.attr("src", dummyImageSrc);
            if (clearURL) imageURLInput.val(null);
            imageURLErrors.html(null);
            base64Output.val(null);
            base64OutputLength.text("0 chars");
            if (clearFile) {
                fileInput.val(null);
            }
        }

        function convertAndPreview(file) {
            fileDropArea.addClass("preview-loading");
            convertFile2Base64(file, (base64) => {
                fileInputPreview.one("load", () => {
                    setTimeout(() => {
                        fileDropArea.removeClass("preview-loading");
                        fileDropArea.addClass("preview-loaded");
                    });
                });
                fileInputPreview.attr("src", base64);
                updateBase64Output(
                    formatBase64(base64, getSelectedOutputType())
                );
            });
        }

        function convertFile2Base64(file, callback) {
            let reader = new FileReader();
            reader.onloadend = function () {
                callback(reader.result);
            };
            reader.readAsDataURL(file);
        }

        function updateBase64Output(base64) {
            base64Output.val(base64);
            base64OutputLength.text(base64.length.toLocaleString() + " chars");
        }

        function formatBase64(base64, type) {
            switch (type) {
                case 'text':
                    return base64;
                case 'img':
                    return `<img alt="" src="${base64}" />`;
                case 'md':
                    return `![](${base64})`;
                default:
                    console.error("Unexpected output type: " + type);
            }
        }

        function getSelectedOutputType() {
            const inputId = base64OutputTypeInputs.filter(":checked").attr('id');
            const outputType = inputId.replace('base64-output-type-', '');
            return outputType;
        }
    </script>
</body>
</html>