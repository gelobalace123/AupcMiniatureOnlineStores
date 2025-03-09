<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $product = trim($_POST['product']);
    $price = trim($_POST['price']);

    if (!empty($product) && !empty($price)) {
        file_put_contents("product.txt", "\n$product,$price,product\n", FILE_APPEND);
        echo "<script>alert('Product added Successful!'); window.location.href='admin_dashboard.php';</script>";
    } else {
        echo "<script>alert('Please fill in all fields!'); window.history.back();</script>";
    }
}
?>