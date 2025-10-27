<?php
session_start();
require_once 'connect.php';

ini_set('display_errors', 0); // Suppress errors in production
error_reporting(E_ALL); // Log errors for debugging
$is_logged_in = isset($_SESSION['customer_id']) && isset($_SESSION['username']);

// Fetch products from database
try {
    $stmt = $pdo->query("SELECT * FROM containers ORDER BY container_id");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $products = [];
    error_log("Error fetching products: " . $e->getMessage());
}

// Fetch water types from database
try {
    $stmt = $pdo->query("SELECT * FROM water_types");
    $water_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $water_types = [];
    error_log("Error fetching water types: " . $e->getMessage());
}

// Fetch order types from database
try {
    $stmt = $pdo->query("SELECT * FROM order_types");
    $order_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $order_types = [];
    error_log("Error fetching order types: " . $e->getMessage());
}

// Initialize cart in session if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Products - WaterWorld Water Station</title>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
    }
    body {
      background-color: #f9fbfc;
      color: #333;
      line-height: 1.6;
      overflow-x: hidden;
    }
    header {
      background: #ffffffcc;
      backdrop-filter: blur(10px);
      padding: 1rem 5%;
      display: flex;
      justify-content: space-between;
      align-items: center;
      border-bottom: 1px solid #e5e5e5;
      position: sticky;
      top: 0;
      z-index: 1000;
    }
    .logo {
      font-size: 1.5rem;
      font-weight: bold;
      color: #008CBA;
      text-transform: uppercase;
      letter-spacing: 2px;
      display: flex;
      align-items: center;
    }
    .logo img {
      height: 2.5rem;
      margin-right: 0.75rem;
      object-fit: contain;
    }
    nav ul {
      list-style: none;
      display: flex;
      gap: 1.5rem;
      align-items: center;
    }
    nav ul li a {
      text-decoration: none;
      color: #333;
      font-weight: 500;
      position: relative;
      padding-bottom: 4px;
      transition: color 0.3s;
    }
    nav ul li a::after {
      content: "";
      position: absolute;
      width: 0;
      height: 2px;
      bottom: 0;
      left: 0;
      background: #008CBA;
      transition: width 0.3s;
    }
    nav ul li a:hover {
      color: #008CBA;
    }
    nav ul li a:hover::after {
      width: 100%;
    }
    .profile {
      position: relative;
      cursor: pointer;
    }
    .profile-icon img {
      height: 2.5rem;
      width: 2.5rem;
      object-fit: contain;
      display: block;
    }
    .profile:hover .dropdown {
      display: block;
    }
    .dropdown {
      display: none;
      position: absolute;
      top: 100%;
      right: 0;
      background: white;
      border: 1px solid #e5e5e5;
      border-radius: 5px;
      box-shadow: 0 4px 6px rgba(0,0,0,0.1);
      min-width: 220px;
      z-index: 1000;
      margin-top: 5px;
    }
    .dropdown a, .dropdown .welcome {
      display: flex;
      align-items: center;
      padding: 12px 20px;
      text-decoration: none;
      color: #333;
      font-size: 0.9rem;
      font-weight: 400;
      transition: background 0.3s;
    }
    .dropdown a:hover {
      background: #f0f0f0;
    }
    .dropdown a img {
      height: 1.8rem;
      width: 1.8rem;
      margin-right: 8px;
      object-fit: contain;
    }
    .welcome {
      color: #008CBA;
      font-weight: 500;
    }
    .products-hero {
      background: linear-gradient(rgba(0, 140, 186, 0.6), rgba(0, 140, 186, 0.6)),
                  url('images/clear_blue_water.png') no-repeat center/cover;
      color: white;
      text-align: center;
      padding: 4rem 5%;
    }
    .products-hero h1 {
      font-size: 3rem;
      margin-bottom: 0.5rem;
    }
    .products-hero p {
      font-size: 1.2rem;
    }
    .products-container {
      padding: 3rem 5%;
      max-width: 1400px;
      margin: 0 auto;
    }
    .products-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 2.5rem;
      margin-top: 2rem;
    }
    .product-card {
      background: white;
      border-radius: 15px;
      padding: 2rem;
      box-shadow: 0 4px 12px rgba(0,0,0,0.08);
      transition: transform 0.3s, box-shadow 0.3s;
      text-align: center;
    }
    .product-card:hover {
      transform: translateY(-8px);
      box-shadow: 0 8px 24px rgba(0,0,0,0.12);
    }
    .product-image {
      width: 100%;
      height: 250px;
      object-fit: contain;
      margin-bottom: 1.5rem;
      border-radius: 10px;
    }
    .product-title {
      font-size: 1.5rem;
      color: #008CBA;
      margin-bottom: 0.5rem;
    }
    .product-price {
      font-size: 1.8rem;
      font-weight: bold;
      color: #333;
      margin: 1rem 0;
    }
    .product-description {
      color: #666;
      margin-bottom: 1.5rem;
      line-height: 1.6;
    }
    .add-to-cart-btn {
      background: linear-gradient(90deg, #008CBA, #00aaff);
      color: white;
      border: none;
      padding: 0.9rem 2rem;
      border-radius: 30px;
      font-weight: bold;
      font-size: 1rem;
      cursor: pointer;
      transition: transform 0.3s, background 0.3s;
      width: 100%;
    }
    .add-to-cart-btn:hover {
      transform: translateY(-2px);
      background: linear-gradient(90deg, #0077b3, #0099e6);
    }
    .add-to-cart-btn:active {
      transform: translateY(0);
    }
    .floating-cart {
      position: fixed;
      bottom: 30px;
      right: 30px;
      background: #008CBA;
      color: white;
      width: 60px;
      height: 60px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      box-shadow: 0 5px 20px rgba(0,140,186,0.4);
      transition: all 0.3s;
      z-index: 999;
    }
    .floating-cart:hover {
      transform: scale(1.1);
      box-shadow: 0 8px 25px rgba(0,140,186,0.6);
    }
    .cart-icon {
      font-size: 1.8rem;
      position: relative;
    }
    .cart-count {
      position: absolute;
      top: -8px;
      right: -8px;
      background: #ff4444;
      color: white;
      border-radius: 50%;
      width: 22px;
      height: 22px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.75rem;
      font-weight: bold;
    }
    .cart-panel {
      position: fixed;
      top: 0;
      right: -450px;
      width: 450px;
      height: 100vh;
      background: white;
      box-shadow: -5px 0 20px rgba(0,0,0,0.1);
      transition: right 0.3s;
      z-index: 1001;
      display: flex;
      flex-direction: column;
    }
    .cart-panel.open {
      right: 0;
    }
    .cart-header {
      background: #008CBA;
      color: white;
      padding: 1.5rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .cart-header h2 {
      font-size: 1.5rem;
    }
    .close-cart {
      background: none;
      border: none;
      color: white;
      font-size: 2rem;
      cursor: pointer;
      transition: transform 0.2s;
    }
    .close-cart:hover {
      transform: rotate(90deg);
    }
    .cart-items {
      flex: 1;
      overflow-y: auto;
      padding: 1.5rem;
    }
    .cart-item {
      display: flex;
      gap: 1rem;
      padding: 1rem;
      border-bottom: 1px solid #e5e5e5;
      align-items: center;
    }
    .cart-item-image {
      width: 80px;
      height: 80px;
      object-fit: contain;
      border-radius: 8px;
      background: #f9f9f9;
    }
    .cart-item-details {
      flex: 1;
    }
    .cart-item-title {
      font-weight: bold;
      color: #008CBA;
      margin-bottom: 0.3rem;
    }
    .cart-item-price {
      color: #666;
      font-size: 0.95rem;
    }
    .quantity-controls {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      margin-top: 0.5rem;
    }
    .qty-btn {
      background: #008CBA;
      color: white;
      border: none;
      width: 25px;
      height: 25px;
      border-radius: 5px;
      cursor: pointer;
      font-weight: bold;
      transition: background 0.3s;
    }
    .qty-btn:hover {
      background: #0077b3;
    }
    .quantity-value {
      min-width: 30px;
      text-align: center;
      font-weight: bold;
    }
    .remove-item, .edit-item {
      background: #ff4444;
      color: white;
      border: none;
      padding: 0.4rem 0.8rem;
      border-radius: 5px;
      cursor: pointer;
      font-size: 0.85rem;
      transition: background 0.3s;
      margin-left: 0.5rem;
    }
    .edit-item {
      background: #008CBA;
    }
    .remove-item:hover {
      background: #cc0000;
    }
    .edit-item:hover {
      background: #0077b3;
    }
    .cart-footer {
      padding: 1.5rem;
      border-top: 2px solid #e5e5e5;
      background: #f9f9f9;
    }
    .cart-total {
      display: flex;
      justify-content: space-between;
      margin-bottom: 1rem;
      font-size: 1.3rem;
      font-weight: bold;
    }
    .checkout-btn {
      background: linear-gradient(90deg, #008CBA, #00aaff);
      color: white;
      border: none;
      padding: 1rem;
      border-radius: 8px;
      font-weight: bold;
      font-size: 1.1rem;
      cursor: pointer;
      width: 100%;
      transition: transform 0.3s;
    }
    .checkout-btn:hover {
      transform: translateY(-2px);
    }
    .checkout-btn:disabled {
      background: #ccc;
      cursor: not-allowed;
      transform: none;
    }
    .empty-cart {
      text-align: center;
      padding: 3rem 1rem;
      color: #999;
    }
    .empty-cart-icon {
      font-size: 4rem;
      margin-bottom: 1rem;
    }
    .modal-overlay {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0, 0, 0, 0.5);
      z-index: 1002;
      align-items: center;
      justify-content: center;
    }
    .modal-overlay.active {
      display: flex;
    }
    .modal-content {
      background: white;
      border-radius: 15px;
      padding: 2rem;
      max-width: 500px;
      width: 90%;
      max-height: 80vh;
      overflow-y: auto;
      box-shadow: 0 10px 40px rgba(0,0,0,0.2);
      animation: modalSlideIn 0.3s ease;
    }
    @keyframes modalSlideIn {
      from {
        transform: translateY(-50px);
        opacity: 0;
      }
      to {
        transform: translateY(0);
        opacity: 1;
      }
    }
    .modal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 1.5rem;
      padding-bottom: 1rem;
      border-bottom: 2px solid #e5e5e5;
    }
    .modal-header h3 {
      color: #008CBA;
      font-size: 1.5rem;
    }
    .modal-close {
      background: none;
      border: none;
      font-size: 2rem;
      color: #999;
      cursor: pointer;
      transition: color 0.3s;
      line-height: 1;
    }
    .modal-close:hover {
      color: #d32f2f;
    }
    .water-type-options, .order-type-options {
      display: flex;
      flex-direction: column;
      gap: 1rem;
      margin-bottom: 1.5rem;
    }
    .water-type-option, .order-type-option {
      border: 2px solid #e5e5e5;
      border-radius: 10px;
      padding: 1.2rem;
      cursor: pointer;
      transition: all 0.3s;
      position: relative;
    }
    .water-type-option:hover, .order-type-option:hover {
      border-color: #008CBA;
      background: #f0f8fb;
    }
    .water-type-option.selected, .order-type-option.selected {
      border-color: #008CBA;
      background: #e3f2fd;
    }
    .water-type-option input[type="radio"], .order-type-option input[type="radio"] {
      position: absolute;
      opacity: 0;
    }
    .water-type-option label, .order-type-option label {
      cursor: pointer;
      display: block;
    }
    .water-type-name, .order-type-name {
      font-weight: bold;
      color: #008CBA;
      font-size: 1.1rem;
    }
    .water-type-desc {
      color: #666;
      font-size: 0.9rem;
      margin-top: 0.3rem;
    }
    .modal-confirm-btn {
      background: linear-gradient(90deg, #008CBA, #00aaff);
      color: white;
      border: none;
      padding: 1rem;
      border-radius: 8px;
      font-weight: bold;
      font-size: 1.1rem;
      cursor: pointer;
      width: 100%;
      transition: transform 0.3s;
    }
    .modal-confirm-btn:hover {
      transform: translateY(-2px);
    }
    .modal-confirm-btn:disabled {
      background: #ccc;
      cursor: not-allowed;
      transform: none;
    }
    footer {
      background: #008CBA;
      color: white;
      text-align: center;
      padding: 2rem 5%;
      margin-top: 3rem;
    }
    @media (max-width: 768px) {
      .cart-panel {
        width: 100%;
        right: -100%;
      }
      .products-grid {
        grid-template-columns: 1fr;
      }
      .profile-icon img {
        height: 2rem;
        width: 2rem;
      }
    }
  </style>
</head>
<body>
<header>
  <div class="logo">
    <img src="images/ww_logo.png" alt="WaterWorld Logo">
    WaterWorld
  </div>
  <nav>
    <ul>
      <li><a href="index.php">Home</a></li>
      <li><a href="product.php">Products</a></li>
      <li><a href="order_tracking.php">Track</a></li>
      <?php if ($is_logged_in): ?>
        <li><a href="feedback.php">Feedback</a></li>
        <li class="profile" onclick="toggleDropdown(this)">
          <div class="profile-icon">
            <img src="images/profile_pic.png" alt="Profile Icon">
          </div>
          <div class="dropdown">
            <div class="welcome">Welcome <?php echo htmlspecialchars($_SESSION['username']); ?>!</div>
            <a href="user_settings.php">
              <img src="images/user_settings.png" alt="User Settings Icon">
              User Settings
            </a>
            <a href="usertransaction_history.php">
              <img src="images/usertransaction_history.png" alt="Transaction History Icon">
              Transaction History
            </a>
            <a href="logout.php">
              <img src="images/logout.png" alt="Logout Icon">
              Logout
            </a>
          </div>
        </li>
      <?php else: ?>
        <li><a href="index.php#login">Login</a></li>
        <li><a href="register.php">Register</a></li>
      <?php endif; ?>
    </ul>
  </nav>
</header>

<section class="products-hero">
  <h1>Our Products</h1>
  <p>Premium water containers for your home and business</p>
</section>

<div class="products-container">
  <div class="products-grid">
    <?php foreach ($products as $product): 
      // Map container types to correct image names
      if ($product['container_type'] === 'Slim') {
        $image_name = 'slim_container.jpg';
      } elseif ($product['container_type'] === 'Round') {
        $image_name = 'round_container.jpg';
      } else {
        $image_name = 'placeholder.jpg';
      }
    ?>
      <div class="product-card">
        <img src="images/<?php echo htmlspecialchars($image_name); ?>" alt="<?php echo htmlspecialchars($product['container_type']); ?> Container" class="product-image">
        <h3 class="product-title"><?php echo htmlspecialchars($product['container_type']); ?> Container</h3>
        <p class="product-price">₱<?php echo number_format($product['price'], 2); ?></p>
        <p class="product-description">
          <?php 
          if ($product['container_type'] === 'Round') {
            echo "Classic round gallon - Perfect for home use with easy grip handles";
          } elseif ($product['container_type'] === 'Slim') {
            echo "Space-saving slim design - Ideal for refrigerators and compact spaces";
          } else {
            echo "High-quality water container for your daily hydration needs";
          }
          ?>
        </p>
        <button class="add-to-cart-btn" onclick="addToCart(<?php echo $product['container_id']; ?>, '<?php echo htmlspecialchars($product['container_type']); ?>', <?php echo $product['price']; ?>, 'images/<?php echo htmlspecialchars($image_name); ?>')">🛒 Add to Cart</button>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Floating Cart Icon -->
<div class="floating-cart" onclick="toggleCart()">
  <div class="cart-icon">
    🛒
    <span class="cart-count" id="cartCount">0</span>
  </div>
</div>

<!-- Cart Panel -->
<div class="cart-panel" id="cartPanel">
  <div class="cart-header">
    <h2>Your Cart</h2>
    <button class="close-cart" onclick="toggleCart()">&times;</button>
  </div>
  <div class="cart-items" id="cartItems">
    <div class="empty-cart">
      <div class="empty-cart-icon">🛒</div>
      <p>Your cart is empty</p>
    </div>
  </div>
  <div class="cart-footer">
    <div class="cart-total">
      <span>Total:</span>
      <span id="cartTotal">₱0.00</span>
    </div>
    <button class="checkout-btn" id="checkoutBtn" onclick="checkout()" disabled>Proceed to Checkout</button>
  </div>
</div>

<!-- Selection Modal -->
<div class="modal-overlay" id="selectionModal">
  <div class="modal-content">
    <div class="modal-header">
      <h3 id="modalTitle">Select Water and Order Type</h3>
      <button class="modal-close" onclick="closeModal()">&times;</button>
    </div>
    <div class="water-type-options" id="waterTypeOptions">
      <h4>Water Type</h4>
      <?php foreach ($water_types as $water_type): ?>
        <div class="water-type-option" onclick="selectWaterType(<?php echo $water_type['water_type_id']; ?>, '<?php echo htmlspecialchars($water_type['type_name']); ?>')">
          <input type="radio" name="water_type" id="water_<?php echo $water_type['water_type_id']; ?>" value="<?php echo $water_type['water_type_id']; ?>">
          <label for="water_<?php echo $water_type['water_type_id']; ?>">
            <div class="water-type-name"><?php echo htmlspecialchars($water_type['type_name']); ?></div>
            <div class="water-type-desc"><?php echo htmlspecialchars($water_type['description'] ?? 'No description available'); ?></div>
          </label>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="order-type-options" id="orderTypeOptions">
      <h4>Order Type</h4>
      <?php foreach ($order_types as $order_type): ?>
        <div class="order-type-option" onclick="selectOrderType(<?php echo $order_type['order_type_id']; ?>, '<?php echo htmlspecialchars($order_type['type_name']); ?>')">
          <input type="radio" name="order_type" id="order_<?php echo $order_type['order_type_id']; ?>" value="<?php echo $order_type['order_type_id']; ?>">
          <label for="order_<?php echo $order_type['order_type_id']; ?>">
            <div class="order-type-name"><?php echo htmlspecialchars($order_type['type_name']); ?></div>
          </label>
        </div>
      <?php endforeach; ?>
    </div>
    <button class="modal-confirm-btn" id="confirmSelection" onclick="confirmSelection()" disabled>Confirm Selection</button>
  </div>
</div>

<footer>
  <p>&copy; 2025 WaterWorld Water Station. All rights reserved.</p>
</footer>

<script>
let cart = [];
let selectedProduct = null;
let selectedWaterType = null;
let selectedOrderType = null;
let editingItem = null;

function openModal(productId, productName, productPrice, productImage, isEdit = false, item = null) {
  selectedProduct = {
    id: productId,
    name: productName,
    price: productPrice,
    image: productImage
  };
  selectedWaterType = null;
  selectedOrderType = null;
  editingItem = isEdit ? item : null;

  const modalTitle = document.getElementById('modalTitle');
  modalTitle.textContent = isEdit ? 'Edit Cart Item' : 'Select Water and Order Type';

  document.getElementById('selectionModal').classList.add('active');
  document.getElementById('confirmSelection').disabled = true;

  // Reset all selections
  document.querySelectorAll('.water-type-option').forEach(option => {
    option.classList.remove('selected');
    option.querySelector('input[type="radio"]').checked = false;
  });
  document.querySelectorAll('.order-type-option').forEach(option => {
    option.classList.remove('selected');
    option.querySelector('input[type="radio"]').checked = false;
  });

  // Pre-select if editing
  if (isEdit && item) {
    selectedWaterType = { id: item.water_type_id, name: item.water_type_name };
    selectedOrderType = { id: item.order_type_id, name: item.order_type_name };

    const waterOption = document.querySelector(`#water_${item.water_type_id}`);
    if (waterOption) {
      waterOption.checked = true;
      waterOption.parentElement.classList.add('selected');
    }
    const orderOption = document.querySelector(`#order_${item.order_type_id}`);
    if (orderOption) {
      orderOption.checked = true;
      orderOption.parentElement.classList.add('selected');
    }
    updateConfirmButton();
  }
}

function selectWaterType(waterTypeId, waterTypeName) {
  selectedWaterType = { id: waterTypeId, name: waterTypeName };
  document.querySelectorAll('.water-type-option').forEach(option => {
    option.classList.remove('selected');
    const input = option.querySelector('input[type="radio"]');
    if (parseInt(input.value) === waterTypeId) {
      option.classList.add('selected');
      input.checked = true;
    }
  });
  updateConfirmButton();
}

function selectOrderType(orderTypeId, orderTypeName) {
  selectedOrderType = { id: orderTypeId, name: orderTypeName };
  document.querySelectorAll('.order-type-option').forEach(option => {
    option.classList.remove('selected');
    const input = option.querySelector('input[type="radio"]');
    if (parseInt(input.value) === orderTypeId) {
      option.classList.add('selected');
      input.checked = true;
    }
  });
  updateConfirmButton();
}

function updateConfirmButton() {
  document.getElementById('confirmSelection').disabled = !(selectedWaterType && selectedOrderType);
}

function closeModal() {
  document.getElementById('selectionModal').classList.remove('active');
  selectedProduct = null;
  selectedWaterType = null;
  selectedOrderType = null;
  editingItem = null;
}

function confirmSelection() {
  if (!selectedProduct || !selectedWaterType || !selectedOrderType) return;

  if (editingItem) {
    // Remove the old item
    cart = cart.filter(item => !(item.id === editingItem.id && 
                                 item.water_type_id === editingItem.water_type_id && 
                                 item.order_type_id === editingItem.order_type_id));
  }

  // Add or update the item
  const existingItem = cart.find(item => 
    item.id === selectedProduct.id && 
    item.water_type_id === selectedWaterType.id && 
    item.order_type_id === selectedOrderType.id
  );
  if (existingItem) {
    existingItem.quantity += editingItem ? editingItem.quantity : 1;
  } else {
    cart.push({
      id: selectedProduct.id,
      name: selectedProduct.name,
      price: selectedProduct.price,
      image: selectedProduct.image,
      quantity: editingItem ? editingItem.quantity : 1,
      water_type_id: selectedWaterType.id,
      water_type_name: selectedWaterType.name,
      order_type_id: selectedOrderType.id,
      order_type_name: selectedOrderType.name
    });
  }

  updateCart();

  if (!editingItem) {
    const btn = document.querySelector(`button[onclick*="${selectedProduct.id}"]`);
    const originalText = btn.textContent;
    btn.textContent = '✓ Added!';
    btn.style.background = '#4CAF50';
    setTimeout(() => {
      btn.textContent = originalText;
      btn.style.background = '';
    }, 1000);
  }

  closeModal();
}

function addToCart(id, name, price, image) {
  openModal(id, name, price, image);
}

function editItem(id, water_type_id, order_type_id) {
  event.stopPropagation();
  const item = cart.find(item => item.id === id && item.water_type_id === water_type_id && item.order_type_id === order_type_id);
  if (item) {
    openModal(item.id, item.name, item.price, item.image, true, item);
  }
}

function updateCart() {
  const cartCount = document.getElementById('cartCount');
  const cartItems = document.getElementById('cartItems');
  const cartTotal = document.getElementById('cartTotal');
  const checkoutBtn = document.getElementById('checkoutBtn');

  const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
  const totalPrice = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);

  cartCount.textContent = totalItems;
  cartTotal.textContent = '₱' + totalPrice.toFixed(2);

  if (cart.length === 0) {
    cartItems.innerHTML = `
      <div class="empty-cart">
        <div class="empty-cart-icon">🛒</div>
        <p>Your cart is empty</p>
      </div>
    `;
    checkoutBtn.disabled = true;
  } else {
    cartItems.innerHTML = cart.map(item => `
      <div class="cart-item">
        <img src="${item.image}" alt="${item.name}" class="cart-item-image" onerror="this.src='images/placeholder.jpg'">
        <div class="cart-item-details">
          <div class="cart-item-title">${item.name} Container (${item.water_type_name}, ${item.order_type_name})</div>
          <div class="cart-item-price">₱${item.price.toFixed(2)} each</div>
          <div class="quantity-controls">
            <button class="qty-btn" onclick="updateQuantity(${item.id}, ${item.water_type_id}, ${item.order_type_id}, -1)">-</button>
            <span class="quantity-value">${item.quantity}</span>
            <button class="qty-btn" onclick="updateQuantity(${item.id}, ${item.water_type_id}, ${item.order_type_id}, 1)">+</button>
            <button class="edit-item" onclick="editItem(${item.id}, ${item.water_type_id}, ${item.order_type_id})">Edit</button>
            <button class="remove-item" onclick="removeItem(${item.id}, ${item.water_type_id}, ${item.order_type_id})">Remove</button>
          </div>
        </div>
      </div>
    `).join('');
    checkoutBtn.disabled = false;
  }

  fetch('update_cart.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({cart: cart})
  }).catch(error => console.error('Error updating cart:', error));
}

function updateQuantity(id, water_type_id, order_type_id, change) {
  event.stopPropagation();
  const item = cart.find(item => item.id === id && item.water_type_id === water_type_id && item.order_type_id === order_type_id);
  if (item) {
    item.quantity += change;
    if (item.quantity <= 0) {
      removeItem(id, water_type_id, order_type_id);
    } else {
      updateCart();
    }
  }
}

function removeItem(id, water_type_id, order_type_id) {
  event.stopPropagation();
  cart = cart.filter(item => !(item.id === id && item.water_type_id === water_type_id && item.order_type_id === order_type_id));
  updateCart();
}

function toggleCart() {
  const cartPanel = document.getElementById('cartPanel');
  cartPanel.classList.toggle('open');
}

function toggleDropdown(element) {
  const dropdown = element.querySelector('.dropdown');
  dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
}

function checkout() {
  if (cart.length === 0) return;

  <?php if ($is_logged_in): ?>
    window.location.href = 'checkout.php?from_cart=1';
  <?php else: ?>
    alert('Please login to proceed with checkout');
    window.location.href = 'index.php#login';
  <?php endif; ?>
}

document.addEventListener('click', function(event) {
  const cartPanel = document.getElementById('cartPanel');
  const floatingCart = document.querySelector('.floating-cart');
  const selectionModal = document.getElementById('selectionModal');
  const modalContent = selectionModal.querySelector('.modal-content');

  if (!cartPanel.contains(event.target) && !floatingCart.contains(event.target)) {
    cartPanel.classList.remove('open');
  }

  if (selectionModal.classList.contains('active') && !modalContent.contains(event.target) && !event.target.classList.contains('add-to-cart-btn') && !event.target.classList.contains('edit-item')) {
    closeModal();
  }
});

document.addEventListener('DOMContentLoaded', function() {
  // Load cart
  fetch('get_cart.php')
    .then(response => response.json())
    .then(data => {
      if (data.cart) {
        cart = data.cart;
        updateCart();
      }
    })
    .catch(error => console.error('Error loading cart:', error));
});
</script>
</body>
</html>