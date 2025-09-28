<?php include 'db_connect.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>WaterWorld - Home</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; font-family: "Segoe UI", sans-serif; }
    body { background: #f9fbfc; color: #333; line-height: 1.6; overflow-x: hidden; }

    header { background: #ffffffcc; backdrop-filter: blur(10px); padding: 1rem 5%;
      display: flex; justify-content: space-between; align-items: center;
      border-bottom: 1px solid #e5e5e5; position: sticky; top: 0; z-index: 1000; }
    .logo { font-size: 1.5rem; font-weight: bold; color: #008CBA; text-transform: uppercase; letter-spacing: 2px; }
    nav ul { list-style: none; display: flex; gap: 1.5rem; }
    nav ul li a { text-decoration: none; color: #333; font-weight: 500; position: relative; padding-bottom: 4px; transition: 0.3s; }
    nav ul li a:hover { color: #008CBA; }
    nav ul li a::after { content: ""; position: absolute; width: 0; height: 2px; bottom: 0; left: 0; background: #008CBA; transition: width 0.3s; }
    nav ul li a:hover::after { width: 100%; }

    .hero { height: 90vh; display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center;
      background: linear-gradient(rgba(0,140,186,0.5), rgba(0,140,186,0.5)), url('water2.png') no-repeat center/cover;
      color: white; padding: 0 5%; animation: fadeIn 2s ease-in-out; }
    .hero h1 { font-size: 3rem; margin-bottom: 1rem; animation: slideDown 1.5s ease forwards; }
    .hero p { font-size: 1.2rem; margin-bottom: 2rem; animation: slideUp 2s ease forwards; }
    .hero a { background: #008CBA; color: white; padding: 0.75rem 1.5rem; border-radius: 30px; text-decoration: none; font-weight: bold; transition: 0.3s; }
    .hero a:hover { background: #005f80; transform: scale(1.05); }

    section { padding: 4rem 5%; text-align: center; }
    section h2 { color: #008CBA; margin-bottom: 2rem; }
    .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem; }
    .card { background: #fff; border-radius: 15px; padding: 2rem; box-shadow: 0 4px 12px rgba(0,0,0,0.1); transition: transform 0.3s; }
    .card:hover { transform: translateY(-8px); }

    footer { background: #008CBA; color: white; text-align: center; padding: 2rem 5%; margin-top: 2rem; }

    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    @keyframes slideDown { from { transform: translateY(-50px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    @keyframes slideUp { from { transform: translateY(50px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
  </style>
</head>
<body>

  <!-- Navbar -->
  <header>
    <div class="logo">WaterWorld</div>
    <nav>
      <ul>
        <li><a href="index.php">Home</a></li>
        <li><a href="orders.php">Order</a></li>
        <li><a href="tracking.php">Track</a></li>
        <li><a href="#">Contact</a></li>
      </ul>
    </nav>
  </header>

  <!-- Hero -->
  <section class="hero">
    <h1>Refreshing Water, Anytime</h1>
    <p>Order, Track, and Stay Hydrated with WaterWorld Refilling Station</p>
    <a href="orders.php">Order Now</a>
  </section>

  <!-- Products from DB -->
  <section>
    <h2>Our Containers</h2>
    <div class="grid">
      <?php
      $stmt = $pdo->query("SELECT * FROM containers LIMIT 3");
      while ($row = $stmt->fetch()) {
          echo "<div class='card'><h3>{$row['container_type']}</h3><p>₱{$row['price']} per refill</p></div>";
      }
      ?>
    </div>
  </section>

  <!-- Riders from DB -->
  <section>
    <h2>Meet Our Riders</h2>
    <div class="grid">
      <?php
      $stmt = $pdo->query("SELECT name FROM employees WHERE role='Delivery Rider' LIMIT 3");
      while ($row = $stmt->fetch()) {
          echo "<div class='card'><h3>{$row['name']}</h3><p>Delivery Rider</p></div>";
      }
      ?>
    </div>
  </section>

  <!-- Feedback from DB -->
  <section>
    <h2>Customer Feedback</h2>
    <div class="grid">
      <?php
      $stmt = $pdo->query("SELECT feedback_text, customer_id FROM feedback LIMIT 3");
      while ($row = $stmt->fetch()) {
          echo "<div class='card'><p>\"{$row['feedback_text']}\" – Customer #{$row['customer_id']}</p></div>";
      }
      ?>
    </div>
  </section>

  <!-- Footer -->
  <footer>
    <p>&copy; 2025 WaterWorld Water Station. All rights reserved.</p>
  </footer>

</body>
</html>
