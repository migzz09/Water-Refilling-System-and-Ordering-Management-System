////test admin panel finished hahahaha

<?php // orders.php ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>WaterWorld - Shop</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; font-family: "Segoe UI", sans-serif; }
    body { background: #f9fbfc; color: #333; line-height: 1.6; overflow-x: hidden; }

    /* Navbar */
    header { background: #ffffffcc; backdrop-filter: blur(10px); padding: 1rem 5%;
      display: flex; justify-content: space-between; align-items: center;
      border-bottom: 1px solid #e5e5e5; position: sticky; top: 0; z-index: 1000; }
    .logo { font-size: 1.5rem; font-weight: bold; color: #008CBA; text-transform: uppercase; letter-spacing: 2px; }
    nav ul { list-style: none; display: flex; gap: 1.5rem; }
    nav ul li a { text-decoration: none; color: #333; font-weight: 500; position: relative; padding-bottom: 4px; transition: 0.3s; }
    nav ul li a:hover { color: #008CBA; }
    nav ul li a::after { content: ""; position: absolute; width: 0; height: 2px; bottom: 0; left: 0; background: #008CBA; transition: width 0.3s; }
    nav ul li a:hover::after { width: 100%; }

    /* Products */
    h1 { text-align: center; margin: 2rem 0; color: #008CBA; }
    .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 2rem; padding: 0 5% 4rem; }
    .card { background: #fff; border-radius: 15px; padding: 2rem; box-shadow: 0 4px 12px rgba(0,0,0,0.1); transition: transform 0.3s; display: flex; flex-direction: column; }
    .card:hover { transform: translateY(-8px); }
    .card img { width: 100%; height: 200px; object-fit: cover; border-radius: 10px; margin-bottom: 1rem; }
    .card h3 { margin-bottom: 0.5rem; color: #008CBA; }
    .card p { font-size: 0.9rem; margin-bottom: 1rem; }
    .card select, .card button { width: 100%; padding: 0.6rem; margin-top: 0.5rem; border-radius: 8px; border: 1px solid #ccc; }
    .card button { background: #008CBA; color: #fff; font-weight: bold; cursor: pointer; transition: 0.3s; border: none; }
    .card button:hover { background: #005f80; }

    /* Cart Drawer */
    .cart-drawer {
      position: fixed; top: 0; right: -400px; width: 350px; height: 100%;
      background: #fff; box-shadow: -2px 0 10px rgba(0,0,0,0.2);
      transition: right 0.4s ease; z-index: 2000; padding: 1.5rem;
      display: flex; flex-direction: column;
    }
    .cart-drawer.open { right: 0; }
    .cart-header { font-size: 1.3rem; font-weight: bold; margin-bottom: 1rem; color: #008CBA; }
    .cart-items { flex: 1; overflow-y: auto; }
    .cart-item { display: flex; justify-content: space-between; align-items: center;
      padding: 0.5rem 0; border-bottom: 1px solid #eee; font-size: 0.9rem; }
    .cart-total { font-size: 1.2rem; font-weight: bold; margin: 1rem 0; }
    .checkout-btn { background: #008CBA; color: #fff; border: none; padding: 0.75rem; border-radius: 8px; cursor: pointer; font-weight: bold; }
    .checkout-btn:hover { background: #005f80; }

    /* Overlay */
    .overlay {
      position: fixed; top: 0; left: 0; width: 100%; height: 100%;
      background: rgba(0,0,0,0.4); opacity: 0; visibility: hidden;
      transition: opacity 0.3s ease;
    }
    .overlay.show { opacity: 1; visibility: visible; }

    footer { background: #008CBA; color: white; text-align: center; padding: 2rem 5%; margin-top: 2rem; }
  </style>
</head>
<body>

  <!-- Navbar -->
  <header>
    <div class="logo">WaterWorld</div>
    <nav>
      <ul>
        <li><a href="index.php">Home</a></li>
        <li><a href="orders.php">Shop</a></li>
        <li><a href="tracking.php">Track</a></li>
        <li><a href="#">Contact</a></li>
      </ul>
    </nav>
  </header>

  <!-- Products -->
  <h1>Shop Our Products</h1>
  <div class="grid">

    <!-- Product 1 -->
    <div class="card">
      <img src="round-gallon.jpg" alt="Round Gallon">
      <h3>Round Gallon</h3>
      <p>Classic round water container. Available in different sizes.</p>
      <label>Size:</label>
      <select id="size1">
        <option value="10L">10L</option>
        <option value="20L">20L</option>
      </select>
      <label>Service:</label>
      <select id="service1">
        <option value="Refill">Refill - ₱30</option>
        <option value="Container Only">Container Only - ₱150</option>
        <option value="Container + Water">Container + Water - ₱200</option>
      </select>
      <button onclick="addToCart('Round Gallon', 'size1', 'service1')">Add to Cart</button>
    </div>

    <!-- Product 2 -->
    <div class="card">
      <img src="slim-gallon.jpg" alt="Slim Gallon">
      <h3>Slim Gallon</h3>
      <p>Space-saving slim water container. Perfect for compact storage.</p>
      <label>Size:</label>
      <select id="size2">
        <option value="10L">10L</option>
        <option value="20L">20L</option>
      </select>
      <label>Service:</label>
      <select id="service2">
        <option value="Refill">Refill - ₱30</option>
        <option value="Container Only">Container Only - ₱150</option>
        <option value="Container + Water">Container + Water - ₱200</option>
      </select>
      <button onclick="addToCart('Slim Gallon', 'size2', 'service2')">Add to Cart</button>
    </div>

    <!-- Product 3 -->
    <div class="card">
      <img src="bottle.jpg" alt="Bottled Water">
      <h3>Bottled Water</h3>
      <p>Stay refreshed with our purified bottled water in multiple sizes.</p>
      <label>Size:</label>
      <select id="size3">
        <option value="350ml">350ml</option>
        <option value="500ml">500ml</option>
        <option value="1L">1L</option>
      </select>
      <label>Service:</label>
      <select id="service3">
        <option value="Bottle Refill">Refill - ₱10</option>
        <option value="New Bottle">New Bottle - ₱25</option>
      </select>
      <button onclick="addToCart('Bottled Water', 'size3', 'service3')">Add to Cart</button>
    </div>

  </div>

  <!-- Cart Drawer -->
  <div class="cart-drawer" id="cartDrawer">
    <div class="cart-header">Your Cart</div>
    <div class="cart-items" id="cartItems"></div>
    <div class="cart-total" id="cartTotal">Total: ₱0</div>
    <button class="checkout-btn">Checkout</button>
  </div>
  <div class="overlay" id="overlay" onclick="toggleCart(false)"></div>

  <!-- Footer -->
  <footer>
    <p>&copy; 2025 WaterWorld Water Station. All rights reserved.</p>
  </footer>

  <script>
    let cart = [];

    function addToCart(product, sizeId, serviceId) {
      const size = document.getElementById(sizeId).value;
      const service = document.getElementById(serviceId).value;
      let price = 0;
      if (service.includes("₱")) {
        price = parseInt(service.split("₱")[1]);
      }

      cart.push({ product, size, service, price });
      renderCart();
      toggleCart(true);
    }

    function renderCart() {
      const cartItemsDiv = document.getElementById("cartItems");
      const cartTotalDiv = document.getElementById("cartTotal");
      cartItemsDiv.innerHTML = "";
      let total = 0;

      cart.forEach((item, index) => {
        total += item.price;
        cartItemsDiv.innerHTML += `
          <div class="cart-item">
            <div>
              <strong>${item.product}</strong><br>
              Size: ${item.size}<br>
              ${item.service}
            </div>
            <button onclick="removeItem(${index})" style="color:red;border:none;background:none;cursor:pointer;">✖</button>
          </div>
        `;
      });

      cartTotalDiv.textContent = "Total: ₱" + total;
    }

    function removeItem(index) {
      cart.splice(index, 1);
      renderCart();
    }

    function toggleCart(show) {
      const drawer = document.getElementById("cartDrawer");
      const overlay = document.getElementById("overlay");
      if (show) {
        drawer.classList.add("open");
        overlay.classList.add("show");
      } else {
        drawer.classList.remove("open");
        overlay.classList.remove("show");
      }
    }
  </script>
</body>
</html>


