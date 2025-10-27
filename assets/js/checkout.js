// NCR cities and barangays data
const ncrCities = {
  'Taguig': [
    'Bagumbayan', 'Bambang', 'Calzada', 'Central Bicutan', 'Central Signal Village',
    'Fort Bonifacio', 'Hagonoy', 'Ibayo-Tipas', 'Katuparan', 'Ligid-Tipas',
    'Lower Bicutan', 'Maharlika Village', 'Napindan', 'New Lower Bicutan',
    'North Daang Hari', 'North Signal Village', 'Palingon', 'Pinagsama',
    'San Miguel', 'Santa Ana', 'South Daang Hari', 'South Signal Village',
    'Tanyag', 'Tuktukan', 'Upper Bicutan', 'Ususan', 'Wawa', 'Western Bicutan',
    'Comembo', 'Cembo', 'South Cembo', 'East Rembo', 'West Rembo', 'Pembo',
    'Pitogo', 'Post Proper Northside', 'Post Proper Southside', 'Rizal'
  ],
  'Quezon City': [
    'Bagong Pag-asa', 'Batasan Hills', 'Commonwealth', 'Holy Spirit', 'Payatas'
  ],
  'Manila': [
    'Tondo', 'Binondo', 'Ermita', 'Malate', 'Paco'
  ],
  'Makati': [
    'Bangkal', 'Bel-Air', 'Magallanes', 'Pio del Pilar', 'San Lorenzo'
  ],
  'Pasig': [
    'Bagong Ilog', 'Oranbo', 'San Antonio', 'Santa Lucia', 'Ugong'
  ],
  'Pateros': [
    'Aguho', 'Martyrs', 'San Roque', 'Santa Ana'
  ]
};

// Vehicle capacity by type
const vehicleCapacity = {
  'Tricycle': 5,
  'Car': 10
};

// Initialize page
document.addEventListener('DOMContentLoaded', async function() {
  try {
    // Check auth status
    const authResponse = await fetch('/api/auth/session.php');
    const authData = await authResponse.json();
    if (!authData.authenticated) {
      window.location.href = 'login.html';
      return;
    }

    document.getElementById('userName').textContent = authData.username;
    loadUserAddress(authData.customer_id);
    loadCart();
    setupCityDropdown();
    setupDeliveryDateMin();

  } catch (error) {
    console.error('Error initializing page:', error);
  }
});

// Load user's saved address
async function loadUserAddress(customerId) {
  try {
    const response = await fetch(`/api/common/get_address.php?customer_id=${customerId}`);
    const address = await response.json();
    
    if (address) {
      document.getElementById('street').value = address.street || '';
      document.getElementById('contactNumber').value = address.customer_contact || '';
      
      if (address.city) {
        document.getElementById('city').value = address.city;
        updateBarangays(address.city);
        if (address.barangay) {
          setTimeout(() => {
            document.getElementById('barangay').value = address.barangay;
          }, 100);
        }
      }
    }
  } catch (error) {
    console.error('Error loading address:', error);
  }
}

// Load cart items
async function loadCart() {
  try {
    const response = await fetch('/api/orders/get_cart.php');
    if (!response.ok) {
      throw new Error('Failed to load cart');
    }
    
    const data = await response.json();
    if (data.cart && data.cart.length > 0) {
      renderCart(data.cart);
      updateVehicleInfo(data.cart);
    } else {
      window.location.href = 'product.html';
    }
  } catch (error) {
    console.error('Error loading cart:', error);
  }
}

function renderCart(cart) {
  const cartItems = document.getElementById('cartItems');
  const cartTotal = document.getElementById('cartTotal');
  let total = 0;

  cartItems.innerHTML = cart.map(item => {
    const itemTotal = item.price * item.quantity;
    total += itemTotal;
    return `
      <div class="cart-item">
        <div class="cart-item-details">
          <div class="cart-item-title">
            ${item.name} Container
            <div class="cart-item-subtitle">${item.water_type_name}, ${item.order_type_name}</div>
          </div>
          <div>Quantity: ${item.quantity}</div>
        </div>
        <div class="cart-item-price">₱${itemTotal.toFixed(2)}</div>
      </div>
    `;
  }).join('');

  cartTotal.textContent = '₱' + total.toFixed(2);
}

function updateVehicleInfo(cart) {
  const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
  const city = document.getElementById('city').value;
  const vehicleType = getVehicleType(city);
  const capacity = vehicleCapacity[vehicleType];

  const info = document.getElementById('vehicleInfo');
  if (totalItems > capacity) {
    info.innerHTML = `
      <div class="alert alert-warning">
        <i class="fa fa-exclamation-triangle"></i>
        Your order quantity (${totalItems}) exceeds the ${vehicleType} capacity (${capacity} containers).
        Please reduce your order quantity or split into multiple orders.
      </div>
    `;
    document.getElementById('placeOrderBtn').disabled = true;
  } else {
    info.innerHTML = `
      <div class="alert alert-info">
        <i class="fa fa-truck"></i>
        Your order will be delivered by ${vehicleType} (Capacity: ${capacity} containers)
      </div>
    `;
    document.getElementById('placeOrderBtn').disabled = false;
  }
}

function setupCityDropdown() {
  const citySelect = document.getElementById('city');
  citySelect.innerHTML = '<option value="">Select City</option>' + 
    Object.keys(ncrCities).map(city => 
      `<option value="${city}">${city}</option>`
    ).join('');

  citySelect.addEventListener('change', function() {
    updateBarangays(this.value);
    updateVehicleInfo(window.cart || []);
  });
}

function updateBarangays(city) {
  const barangaySelect = document.getElementById('barangay');
  barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
  
  if (city && ncrCities[city]) {
    barangaySelect.innerHTML += ncrCities[city].map(barangay => 
      `<option value="${barangay}">${barangay}</option>`
    ).join('');
  }
}

function setupDeliveryDateMin() {
  const today = new Date();
  const tomorrow = new Date(today);
  tomorrow.setDate(tomorrow.getDate() + 1);
  
  const dateInput = document.getElementById('deliveryDate');
  dateInput.min = tomorrow.toISOString().split('T')[0];
  dateInput.value = tomorrow.toISOString().split('T')[0];
}

function getVehicleType(city) {
  return city === 'Taguig' ? 'Tricycle' : 'Car';
}

function validateForm() {
  const required = [
    { id: 'street', message: 'Street address is required' },
    { id: 'city', message: 'City is required' },
    { id: 'barangay', message: 'Barangay is required' },
    { id: 'contactNumber', message: 'Contact number is required' },
    { id: 'deliveryDate', message: 'Delivery date is required' }
  ];

  for (const field of required) {
    const element = document.getElementById(field.id);
    if (!element.value) {
      alert(field.message);
      element.focus();
      return false;
    }
  }

  const contact = document.getElementById('contactNumber').value;
  if (!contact.match(/^09\d{9}$/)) {
    alert('Please enter a valid contact number (e.g., 09XXXXXXXXX)');
    return false;
  }

  return true;
}

function placeOrder() {
  if (!validateForm()) return;

  const orderDetails = {
    street: document.getElementById('street').value,
    city: document.getElementById('city').value,
    barangay: document.getElementById('barangay').value,
    contactNumber: document.getElementById('contactNumber').value,
    deliveryDate: document.getElementById('deliveryDate').value,
    paymentMethod: document.querySelector('input[name="payment"]:checked').value
  };

  showOrderConfirmation(orderDetails);
}

function showOrderConfirmation(details) {
  const modal = document.getElementById('orderConfirmation');
  const orderDetails = modal.querySelector('.order-details');

  orderDetails.innerHTML = `
    <div class="confirmation-details">
      <h3>Delivery Address</h3>
      <p>${details.street}</p>
      <p>${details.barangay}, ${details.city}</p>
      <p>Contact: ${details.contactNumber}</p>
      
      <h3>Delivery Date</h3>
      <p>${new Date(details.deliveryDate).toLocaleDateString('en-US', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
      })}</p>
      
      <h3>Payment Method</h3>
      <p>${details.paymentMethod === 'cod' ? 'Cash on Delivery' : 'GCash'}</p>
    </div>
  `;

  modal.classList.add('active');
}

async function confirmOrder() {
  const orderData = {
    street: document.getElementById('street').value,
    city: document.getElementById('city').value,
    barangay: document.getElementById('barangay').value,
    contactNumber: document.getElementById('contactNumber').value,
    deliveryDate: document.getElementById('deliveryDate').value,
    paymentMethod: document.querySelector('input[name="payment"]:checked').value
  };

  try {
    const response = await fetch('/api/orders/create.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(orderData)
    });

    const result = await response.json();
    if (result.success) {
      showReceipt(result.order);
    } else {
      alert(result.error || 'Failed to place order. Please try again.');
    }
  } catch (error) {
    console.error('Error placing order:', error);
    alert('Failed to place order. Please try again.');
  }
}

function showReceipt(order) {
  const modal = document.getElementById('receiptModal');
  const details = modal.querySelector('.receipt-details');

  details.innerHTML = `
    <div class="receipt-section">
      <h3>Order Reference: ${order.reference_id}</h3>
      <p>Date: ${new Date(order.order_date).toLocaleString()}</p>
    </div>

    <div class="receipt-section">
      <h3>Delivery Details</h3>
      <p>Date: ${new Date(order.delivery_date).toLocaleDateString()}</p>
      <p>Address: ${order.address}</p>
      <p>Contact: ${order.customer_contact}</p>
    </div>

    <div class="receipt-section">
      <h3>Delivery Batch</h3>
      <p>Batch #${order.batch_number}</p>
      <p>Vehicle: ${order.vehicle_type}</p>
    </div>

    <div class="receipt-section">
      <h3>Items</h3>
      ${order.items.map(item => `
        <div class="receipt-item">
          <div>${item.name} Container</div>
          <div>${item.water_type_name}, ${item.order_type_name}</div>
          <div>Quantity: ${item.quantity}</div>
          <div>₱${(item.price * item.quantity).toFixed(2)}</div>
        </div>
      `).join('')}
    </div>

    <div class="receipt-total">
      <h3>Total Amount: ₱${order.total_amount.toFixed(2)}</h3>
    </div>
  `;

  document.getElementById('orderConfirmation').classList.remove('active');
  modal.classList.add('active');
}

function printReceipt() {
  const receiptContent = document.querySelector('.receipt').cloneNode(true);
  const printWindow = window.open('', '', 'width=800,height=600');
  
  printWindow.document.write(`
    <!DOCTYPE html>
    <html>
    <head>
      <title>Order Receipt - WaterWorld</title>
      <link rel="stylesheet" href="../assets/css/design-system.css">
      <link rel="stylesheet" href="../assets/css/checkout.css">
      <style>
        body { padding: 2rem; }
        @media print {
          button { display: none; }
        }
      </style>
    </head>
    <body>
      ${receiptContent.outerHTML}
    </body>
    </html>
  `);

  printWindow.document.close();
  setTimeout(() => {
    printWindow.print();
    printWindow.close();
  }, 250);
}

function closeModal() {
  document.getElementById('orderConfirmation').classList.remove('active');
}

function closeReceipt() {
  document.getElementById('receiptModal').classList.remove('active');
  window.location.href = 'product.html';
}

function toggleUserDropdown() {
  const dropdown = document.getElementById('userDropdown');
  dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
}

function logout() {
  fetch('/api/auth/logout.php')
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        window.location.href = '../index.html';
      }
    })
    .catch(error => console.error('Error logging out:', error));
}