<?php
// Load products from products.txt
$productsFile = "products.txt";
$productsData = file($productsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
$productsArray = [];

foreach ($productsData as $line) {
    // Remove any trailing '[\n]' or artifacts from previous format
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

// Convert products array to JSON for JavaScript
$productsJson = json_encode($productsArray);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mini Shop - Stationery Store</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #2A2A2A;
            --accent-color: #FF6B6B;
            --light-bg: #F8F9FA;
            --dark-text: #2D3436;
            --transition: all 0.3s ease;
            --sidebar-width: 400px;
            --header-height: 70px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background-color: var(--light-bg);
            color: var(--dark-text);
            line-height: 1.6;
            overflow-x: hidden;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        header {
            background: var(--primary-color);
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
            height: var(--header-height);
            transition: var(--transition);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 100%;
        }

        .logo {
            font-size: 1.8rem;
            font-weight: 700;
            color: white;
            text-decoration: none;
            transition: var(--transition);
        }

        .logo:hover {
            color: var(--accent-color);
            transform: scale(1.05);
        }

        .nav-menu {
            display: flex;
            gap: 2rem;
            list-style: none;
            transition: var(--transition);
        }

        .nav-link {
            color: white;
            text-decoration: none;
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            transition: var(--transition);
        }

        .nav-link:hover {
            background: rgba(255,255,255,0.1);
            color: var(--accent-color);
            transform: translateY(-2px);
        }

        .cart-link {
            position: relative;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
        }

        .cart-count {
            background: var(--accent-color);
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            transition: var(--transition);
        }

        .cart-count:hover {
            transform: scale(1.1);
        }

        .hamburger {
            display: none;
            font-size: 1.5rem;
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            padding: 0.5rem;
            transition: var(--transition);
        }

        .hamburger:hover {
            color: var(--accent-color);
            transform: rotate(90deg);
        }

        main {
            margin-top: var(--header-height);
            padding-top: 2rem;
            opacity: 0;
            animation: fadeIn 0.5s ease forwards 0.2s;
        }

        .page-title, h2 {
            color: var(--primary-color);
            margin-bottom: 1rem;
            transition: var(--transition);
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 2rem;
            padding: 1rem 0;
        }

        .product-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            transition: var(--transition);
            opacity: 0;
            animation: slideUp 0.5s ease forwards;
        }

        .product-card:nth-child(1) { animation-delay: 0.1s; }
        .product-card:nth-child(2) { animation-delay: 0.2s; }
        .product-card:nth-child(3) { animation-delay: 0.3s; }
        .product-card:nth-child(4) { animation-delay: 0.4s; }
        .product-card:nth-child(5) { animation-delay: 0.5s; }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.1);
        }

        .product-image {
            width: 100%;
            height: 200px;
            object-fit: contain;
            padding: 1rem;
            background: #fff;
        }

        .product-info {
            padding: 1.5rem;
            border-top: 1px solid #eee;
        }

        .product-price {
            color: var(--accent-color);
            font-size: 1.2rem;
            font-weight: 700;
            margin: 0.5rem 0;
        }

        .add-to-cart {
            width: 100%;
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 0.8rem;
            border-radius: 6px;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-weight: 500;
        }

        .add-to-cart:hover {
            background: var(--accent-color);
            transform: scale(1.02);
        }

        .cart-modal {
            position: fixed;
            top: 0;
            right: calc(-1 * var(--sidebar-width));
            width: var(--sidebar-width);
            height: 100vh;
            background: white;
            box-shadow: -5px 0 20px rgba(0,0,0,0.15);
            transition: right 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
            padding: 2rem;
            z-index: 2000;
            overflow-y: auto;
        }

        .cart-modal.active {
            right: 0;
        }

        .cart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 1rem;
        }

        .cart-header h2 {
            color: var(--primary-color);
            font-size: 1.8rem;
        }

        .close-cart {
            background: var(--accent-color);
            color: white;
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            font-size: 1.2rem;
            cursor: pointer;
            transition: var(--transition);
        }

        .close-cart:hover {
            background: #e55a5a;
            transform: rotate(90deg);
        }

        .cart-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            border-bottom: 1px solid #eee;
            background: #f9f9f9;
            border-radius: 8px;
            margin-bottom: 0.5rem;
            opacity: 0;
            animation: fadeIn 0.3s ease forwards;
        }

        .cart-item:nth-child(1) { animation-delay: 0.1s; }
        .cart-item:nth-child(2) { animation-delay: 0.2s; }
        .cart-item:nth-child(3) { animation-delay: 0.3s; }

        .cart-item button {
            background: #ff4444;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            cursor: pointer;
            transition: var(--transition);
        }

        .cart-item button:hover {
            background: #cc3333;
            transform: scale(1.05);
        }

        .cart-summary {
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 2px solid var(--primary-color);
        }

        .cart-summary p {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .checkout-modal {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            z-index: 3000;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .checkout-modal.active {
            display: block;
            opacity: 1;
        }

        .checkout-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .close-checkout {
            background: var(--accent-color);
            color: white;
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            font-size: 1.2rem;
            cursor: pointer;
            transition: var(--transition);
        }

        .close-checkout:hover {
            background: #e55a5a;
            transform: rotate(90deg);
        }

        .checkout-form {
            display: grid;
            gap: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .form-input {
            padding: 0.8rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
            transition: var(--transition);
        }

        .form-input:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 5px rgba(255,107,107,0.3);
            outline: none;
        }

        .checkout-btn {
            background: var(--primary-color);
            color: white;
            padding: 0.8rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: var(--transition);
            font-weight: 500;
        }

        .checkout-btn:hover {
            background: var(--accent-color);
            transform: scale(1.02);
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 1024px) {
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                gap: 1.5rem;
            }

            .cart-modal {
                width: 350px;
            }
        }

        @media (max-width: 768px) {
            .nav-menu {
                display: none;
                position: absolute;
                top: 100%;
                left: 0;
                width: 100%;
                background: var(--primary-color);
                flex-direction: column;
                gap: 0;
                padding: 1rem 0;
                box-shadow: 0 4px 10px rgba(0,0,0,0.2);
                opacity: 0;
                transform: translateY(-10px);
                transition: var(--transition), opacity 0.3s ease, transform 0.3s ease;
            }

            .nav-menu.active {
                display: flex;
                opacity: 1;
                transform: translateY(0);
            }

            .nav-link {
                width: 100%;
                text-align: center;
                padding: 1rem;
                border-bottom: 1px solid rgba(255,255,255,0.1);
            }

            .nav-link:last-child {
                border-bottom: none;
            }

            .hamburger {
                display: block;
            }

            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            }

            .product-image {
                height: 160px;
            }

            .product-info {
                padding: 1rem;
            }

            .cart-modal {
                width: 100%;
                max-width: 350px;
                padding: 1.5rem;
            }

            .checkout-modal {
                width: 85%;
                padding: 1.5rem;
            }
        }

        @media (max-width: 480px) {
            .logo {
                font-size: 1.5rem;
            }

            .products-grid {
                grid-template-columns: 1fr;
            }

            .product-image {
                height: 140px;
            }

            .cart-modal {
                width: 100%;
                max-width: none;
                padding: 1rem;
            }

            .cart-item {
                flex-direction: column;
                text-align: center;
                gap: 0.5rem;
            }

            .cart-item button {
                width: 100%;
            }

            .checkout-modal {
                width: 90%;
                padding: 1rem;
            }

            .checkout-form {
                gap: 1rem;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container header-content">
            <a href="#" class="logo">Mini Shop</a>
            <nav>
                <button class="hamburger" onclick="toggleNav()">
                    <i class="fas fa-bars"></i>
                </button>
                <ul class="nav-menu">
                    <li><a href="index.html" class="nav-link">Log out</a></li>                
                    <li><a href="user_dashboard.php" class="nav-link">Home</a></li>
                    <li><a href="#" class="nav-link">Products</a></li>
                    <li><a href="#" class="nav-link">About</a></li>
                    <li><a href="#" class="nav-link cart-link" onclick="toggleCart()">
                        <i class="fas fa-shopping-cart"></i>               
                        <span class="cart-count" id="cart-count">0</span>
                    </a></li>
                </ul>           
            </nav>
        </div>
    </header>

    <main class="container">
        <h1 class="page-title">Featured Stationery</h1>
        <h2>Featured Products</h2>
        <div class="products-grid" id="productsGrid"></div>
    </main>

    <div class="cart-modal" id="cartModal">
        <div class="cart-header">
            <h2>Your Cart</h2>
            <button class="close-cart" onclick="toggleCart()">×</button>
        </div>
        <div class="cart-items" id="cartItems"></div>
        <div class="cart-summary">
            <p>Total: <span id="cartTotal">₱0.00</span></p>
            <button class="checkout-btn" onclick="showCheckout()">Proceed to Checkout</button>
        </div>
    </div>

    <div class="checkout-modal" id="checkoutModal">
        <div class="checkout-header">
            <h2>Checkout</h2>
            <button class="close-checkout" onclick="closeCheckout()">×</button>
        </div>
        <form id="checkoutForm" class="checkout-form">
            <div class="form-group">
                <label for="name">Name:</label>
                <input type="text" id="name" class="form-input" required>
            </div>
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" class="form-input" required>
            </div>
            <div class="form-group">
                <label for="address">Address:</label>
                <input type="text" id="address" class="form-input" required>
            </div>
            <div class="form-group">
                <label for="payment">Payment Method:</label>
                <select id="payment" class="form-input" required>
                    <option value="credit">Credit Card</option>
                    <option value="paypal">PayPal</option>
                    <option value="cod">Cash on Delivery</option>
                    <option value="gcash">Gcash</option>
                </select>
            </div>
            <button type="submit" class="checkout-btn">Complete Purchase</button>
        </form>
    </div>

    <script>
        let cart = JSON.parse(localStorage.getItem('cart')) || [];
        const products = <?php echo $productsJson; ?>;

        function loadProducts() {
            const productsGrid = document.getElementById('productsGrid');
            if (products.length === 0) {
                productsGrid.innerHTML = '<p>No products available.</p>';
                return;
            }

            productsGrid.innerHTML = products.map(product => `
                <article class="product-card">
                    <img src="${product.image}" alt="${product.name}" class="product-image" 
                         onerror="this.src='https://via.placeholder.com/200?text=Image+Not+Found';">
                    <div class="product-info">
                        <h3>${product.name}</h3>
                        <p class="product-price">₱${product.price.toFixed(2)}</p>
                        <button class="add-to-cart" onclick="addToCart('${product.name}', ${product.price})">
                            <i class="fas fa-cart-plus"></i> Add to Cart
                        </button>
                    </div>
                </article>
            `).join('');
        }

        function addToCart(name, price) {
            const existingItem = cart.find(item => item.name === name);
            if (existingItem) {
                existingItem.quantity++;
            } else {
                cart.push({ name, price, quantity: 1 });
            }
            updateCart();
        }

        function updateCart() {
            localStorage.setItem('cart', JSON.stringify(cart));
            const itemCount = cart.reduce((sum, item) => sum + item.quantity, 0);
            document.getElementById('cart-count').textContent = itemCount;
            renderCartItems();
        }

        function renderCartItems() {
            const cartItems = document.getElementById('cartItems');
            const cartTotal = document.getElementById('cartTotal');
            let total = 0;
            
            cartItems.innerHTML = cart.map((item, index) => {
                const itemTotal = item.price * item.quantity;
                total += itemTotal;
                return `
                    <div class="cart-item">
                        <span>${item.name} (${item.quantity})</span>
                        <span>₱${itemTotal.toFixed(2)}</span>
                        <button onclick="removeFromCart(${index})">Remove</button>
                    </div>
                `;
            }).join('');
            
            cartTotal.textContent = `₱${total.toFixed(2)}`;
        }

        function removeFromCart(index) {
            cart.splice(index, 1);
            updateCart();
        }

        function toggleCart() {
            document.getElementById('cartModal').classList.toggle('active');
        }

        function showCheckout() {
            if (cart.length === 0) {
                alert('Your cart is empty!');
                return;
            }
            document.getElementById('checkoutModal').classList.add('active');
        }

        function closeCheckout() {
            document.getElementById('checkoutModal').classList.remove('active');
        }

        function toggleNav() {
            document.querySelector('.nav-menu').classList.toggle('active');
        }

        document.getElementById('checkoutForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const order = {
                name: document.getElementById('name').value,
                email: document.getElementById('email').value,
                address: document.getElementById('address').value,
                payment: document.getElementById('payment').value,
                cart: cart,
                total: cart.reduce((sum, item) => sum + (item.price * item.quantity), 0),
                timestamp: new Date().toISOString()
            };

            try {
                const response = await fetch('save_order.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(order)
                });

                if (response.ok) {
                    alert('Order placed successfully!');
                    cart = [];
                    updateCart();
                    document.getElementById('checkoutModal').classList.remove('active');
                    document.getElementById('checkoutForm').reset();
                } else {
                    throw new Error('Server error');
                }
            } catch (error) {
                console.error('Checkout error:', error);
                alert('Failed to place order. Please try again.');
            }
        });

        window.onload = function() {
            loadProducts();
            updateCart();
        };
    </script>
</body>
</html>