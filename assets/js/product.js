/**
 * WaterWorld Water Station - Product Page Scripts
 */

// Global state
const state = {
  cart: [],
  selectedProduct: null,
  selectedWaterType: null,
  selectedOrderType: null,
  editingItem: null
};

// Initialize page
document.addEventListener('DOMContentLoaded', async function() {
  try {
  // Check auth status
  const authResponse = await fetch('../api/auth/session.php');
    const authData = await authResponse.json();
    if (authData.authenticated) {
      document.getElementById('loginBtn').style.display = 'none';
      document.getElementById('registerBtn').style.display = 'none';
      document.getElementById('userMenu').style.display = 'block';
      document.getElementById('userName').textContent = authData.username;
    }

    // Load products
  const productsResponse = await fetch('../api/common/containers.php');
    const products = await productsResponse.json();
    renderProducts(products);

    // Load water types
  const waterTypesResponse = await fetch('../api/common/water_types.php');
    const waterTypes = await waterTypesResponse.json();
    renderWaterTypes(waterTypes);

    // Load order types
  const orderTypesResponse = await fetch('../api/common/order_types.php');
    const orderTypes = await orderTypesResponse.json();
    renderOrderTypes(orderTypes);

    // Load cart
    loadCart();
  } catch (error) {
    console.error('Error loading data:', error);
  }
});

function renderProducts(products) {
  const grid = document.getElementById('productsGrid');
  grid.innerHTML = products.map(product => `
    <div class="product-card">
      <img src="../assets/images/${product.image}" 
           alt="${product.container_type} Container" 
           class="product-image"
           onerror="this.src='../assets/images/placeholder.png'">
      <h3 class="product-title">${product.container_type} Container</h3>
      <p class="product-price">₱${Number(product.price).toFixed(2)}</p>
      <p class="product-description">${getProductDescription(product.container_type)}</p>
      <button class="btn btn-primary btn-block add-to-cart-btn" 
              data-id="${product.container_id}" 
              data-name="${product.container_type}"
              data-price="${product.price}"
              data-image="${product.image}">
        <i class="fa fa-shopping-cart"></i> Add to Cart
      </button>
    </div>
  `).join('');
}

function getProductDescription(type) {
  return type === 'Round' 
    ? 'Classic round gallon - Perfect for home use with easy grip handles'
    : 'Space-saving slim design - Ideal for refrigerators and compact spaces';
}

function renderWaterTypes(types) {
  const container = document.getElementById('waterTypeOptions');
  container.innerHTML = `<h4>Water Type</h4>` + types.map(type => `
    <div class="water-type-option" data-id="${type.water_type_id}">
      <input type="radio" name="water_type" id="water_${type.water_type_id}" value="${type.water_type_id}" aria-label="${type.type_name}" title="${type.type_name}">
      <label for="water_${type.water_type_id}">
        <div class="water-type-name">${type.type_name}</div>
        <div class="water-type-desc">${type.description || 'High-quality water'}</div>
      </label>
    </div>
  `).join('');
}

function renderOrderTypes(types) {
  const container = document.getElementById('orderTypeOptions');
  container.innerHTML = `<h4>Order Type</h4>` + types.map(type => `
    <div class="order-type-option" data-id="${type.order_type_id}">
      <input type="radio" name="order_type" id="order_${type.order_type_id}" value="${type.order_type_id}" aria-label="${type.type_name}" title="${type.type_name}">
      <label for="order_${type.order_type_id}">
        <div class="order-type-name">${type.type_name}</div>
      </label>
    </div>
  `).join('');
}

// Event delegation for add-to-cart buttons and option-card clicks
document.addEventListener('click', function (e) {
  // Add to cart
  const addBtn = e.target.closest('.add-to-cart-btn');
  if (addBtn) {
    const id = parseInt(addBtn.dataset.id, 10);
    const name = addBtn.dataset.name;
    const price = parseFloat(addBtn.dataset.price);
    const image = addBtn.dataset.image;
    addToCart(id, name, price, image);
    return;
  }

  // Option card selection (water/order types) — support reference class names
  const waterOption = e.target.closest('.water-type-option');
  if (waterOption) {
    const input = waterOption.querySelector('input[type="radio"]');
    if (input) {
      input.checked = true;
      selectWaterType(parseInt(input.value, 10), waterOption.querySelector('.water-type-name')?.textContent || input.title);
    }
    return;
  }

  const orderOption = e.target.closest('.order-type-option');
  if (orderOption) {
    const input = orderOption.querySelector('input[type="radio"]');
    if (input) {
      input.checked = true;
      selectOrderType(parseInt(input.value, 10), orderOption.querySelector('.order-type-name')?.textContent || input.title);
    }
    return;
  }
});

// Cart Management Functions
function addToCart(id, name, price, image) {
  openModal(id, name, price, image);
}

function openModal(productId, productName, productPrice, productImage, isEdit = false, item = null) {
  state.selectedProduct = { id: productId, name: productName, price: productPrice, image: productImage };
  state.selectedWaterType = null;
  state.selectedOrderType = null;
  state.editingItem = isEdit ? item : null;

  document.getElementById('modalTitle').textContent = isEdit ? 'Edit Cart Item' : 'Select Water and Order Type';
  document.getElementById('selectionModal').classList.add('active');
  document.getElementById('confirmSelection').disabled = true;

  // Reset selections
  // Reset selections for both water and order option elements
  document.querySelectorAll('.water-type-option, .order-type-option').forEach(card => {
    card.classList.remove('selected');
    const input = card.querySelector('input[type="radio"]');
    if (input) input.checked = false;
  });

  if (isEdit && item) {
    preSelectOptions(item);
  }
}

function preSelectOptions(item) {
  state.selectedWaterType = { id: item.water_type_id, name: item.water_type_name };
  state.selectedOrderType = { id: item.order_type_id, name: item.order_type_name };

  document.querySelectorAll('[name="water_type"]').forEach(input => {
    const card = input.closest('.water-type-option');
    if (parseInt(input.value) === item.water_type_id) {
      if (card) card.classList.add('selected');
      input.checked = true;
    }
  });

  document.querySelectorAll('[name="order_type"]').forEach(input => {
    const card = input.closest('.order-type-option');
    if (parseInt(input.value) === item.order_type_id) {
      if (card) card.classList.add('selected');
      input.checked = true;
    }
  });

  updateConfirmButton();
}

function selectWaterType(id, name) {
  state.selectedWaterType = { id, name };
  document.querySelectorAll('[name="water_type"]').forEach(input => {
    const card = input.closest('.water-type-option');
    if (parseInt(input.value) === id) {
      if (card) card.classList.add('selected');
      input.checked = true;
    } else {
      if (card) card.classList.remove('selected');
      input.checked = false;
    }
  });
  updateConfirmButton();
}

function selectOrderType(id, name) {
  state.selectedOrderType = { id, name };
  document.querySelectorAll('[name="order_type"]').forEach(input => {
    const card = input.closest('.order-type-option');
    if (parseInt(input.value) === id) {
      if (card) card.classList.add('selected');
      input.checked = true;
    } else {
      if (card) card.classList.remove('selected');
      input.checked = false;
    }
  });
  updateConfirmButton();
}

function updateConfirmButton() {
  document.getElementById('confirmSelection').disabled = !(state.selectedWaterType && state.selectedOrderType);
}

function confirmSelection() {
  if (!state.selectedProduct || !state.selectedWaterType || !state.selectedOrderType) return;

  if (state.editingItem) {
    state.cart = state.cart.filter(item => !(
      item.id === state.editingItem.id && 
      item.water_type_id === state.editingItem.water_type_id && 
      item.order_type_id === state.editingItem.order_type_id
    ));
  }

  const newItem = {
    id: state.selectedProduct.id,
    name: state.selectedProduct.name,
    price: state.selectedProduct.price,
    image: state.selectedProduct.image,
    quantity: state.editingItem ? state.editingItem.quantity : 1,
    water_type_id: state.selectedWaterType.id,
    water_type_name: state.selectedWaterType.name,
    order_type_id: state.selectedOrderType.id,
    order_type_name: state.selectedOrderType.name
  };

  const existingItem = state.cart.find(item => 
    item.id === newItem.id && 
    item.water_type_id === newItem.water_type_id && 
    item.order_type_id === newItem.order_type_id
  );

  if (existingItem) {
    existingItem.quantity += newItem.quantity;
  } else {
    state.cart.push(newItem);
  }

  updateCart();
  closeModal();
  showAddedFeedback(state.selectedProduct.id);
}

function showAddedFeedback(productId) {
  const btn = document.querySelector(`.add-to-cart-btn[data-id="${productId}"]`);
  if (!btn) return;
  const originalHtml = btn.innerHTML;
  btn.innerHTML = '<i class="fa fa-check"></i> Added!';
  btn.classList.add('btn-success');
  setTimeout(() => {
    btn.innerHTML = originalHtml;
    btn.classList.remove('btn-success');
  }, 1000);
}

function closeModal() {
  document.getElementById('selectionModal').classList.remove('active');
  state.selectedProduct = null;
  state.selectedWaterType = null;
  state.selectedOrderType = null;
  state.editingItem = null;
}

function updateCart() {
  const cartCount = document.getElementById('cartCount');
  const cartItems = document.getElementById('cartItems');
  const cartTotal = document.getElementById('cartTotal');
  const checkoutBtn = document.getElementById('checkoutBtn');

  const totalItems = state.cart.reduce((sum, item) => sum + item.quantity, 0);
  const totalPrice = state.cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);

  cartCount.textContent = totalItems;
  cartTotal.textContent = '₱' + totalPrice.toFixed(2);

  if (state.cart.length === 0) {
    cartItems.innerHTML = `
      <div class="empty-cart">
        <i class="fa fa-shopping-cart fa-3x"></i>
        <p>Your cart is empty</p>
      </div>
    `;
    checkoutBtn.disabled = true;
  } else {
    cartItems.innerHTML = state.cart.map(item => `
      <div class="cart-item">
        <img src="../assets/images/${item.image}" 
             alt="${item.name}" 
             class="cart-item-image"
             onerror="this.src='../assets/images/placeholder.png'">
        <div class="cart-item-details">
          <div class="cart-item-title">
            ${item.name} Container
            <div class="cart-item-subtitle">${item.water_type_name}, ${item.order_type_name}</div>
          </div>
          <div class="cart-item-price">₱${item.price.toFixed(2)} each</div>
          <div class="quantity-controls">
            <button class="btn btn-sm" onclick="updateQuantity(${item.id}, ${item.water_type_id}, ${item.order_type_id}, -1)">
              <i class="fa fa-minus"></i>
            </button>
            <span class="quantity-value">${item.quantity}</span>
            <button class="btn btn-sm" onclick="updateQuantity(${item.id}, ${item.water_type_id}, ${item.order_type_id}, 1)">
              <i class="fa fa-plus"></i>
            </button>
            <button class="btn btn-primary btn-sm" onclick="editItem(${item.id}, ${item.water_type_id}, ${item.order_type_id})">
              <i class="fa fa-edit"></i>
            </button>
            <button class="btn btn-danger btn-sm" onclick="removeItem(${item.id}, ${item.water_type_id}, ${item.order_type_id})">
              <i class="fa fa-trash"></i>
            </button>
          </div>
        </div>
      </div>
    `).join('');
    checkoutBtn.disabled = false;
  }

  // Save cart to server
  fetch('../api/orders/update_cart.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({ cart: state.cart })
  })
  .then(response => {
    if (!response.ok) {
      throw new Error('Failed to update cart');
    }
    return response.json();
  })
  .catch(error => {
    console.error('Error updating cart:', error);
    showError('Failed to update cart. Please try again.');
  });
}

function updateQuantity(id, water_type_id, order_type_id, change) {
  const item = state.cart.find(item => 
    item.id === id && 
    item.water_type_id === water_type_id && 
    item.order_type_id === order_type_id
  );
  if (item) {
    const newQuantity = item.quantity + change;
    if (newQuantity > 0) {
      item.quantity = newQuantity;
      updateCart();
    } else if (newQuantity <= 0) {
      removeItem(id, water_type_id, order_type_id);
    }
  }
}

function removeItem(id, water_type_id, order_type_id) {
  state.cart = state.cart.filter(item => !(
    item.id === id && 
    item.water_type_id === water_type_id && 
    item.order_type_id === order_type_id
  ));
  updateCart();
}

function editItem(id, water_type_id, order_type_id) {
  const item = state.cart.find(item => 
    item.id === id && 
    item.water_type_id === water_type_id && 
    item.order_type_id === order_type_id
  );
  if (item) {
    openModal(item.id, item.name, item.price, item.image, true, item);
  }
}

function toggleCart() {
  document.getElementById('cartPanel').classList.toggle('open');
}

function toggleUserDropdown() {
  const dropdown = document.getElementById('userDropdown');
  dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
}

function loadCart() {
  fetch('../api/orders/get_cart.php')
    .then(response => {
      if (!response.ok) {
        throw new Error('Failed to load cart');
      }
      return response.json();
    })
    .then(data => {
      if (data.cart) {
        state.cart = data.cart;
        updateCart();
      }
    })
    .catch(error => {
      console.error('Error loading cart:', error);
      showError('Failed to load cart. Please refresh the page.');
    });
}

function checkout() {
  if (state.cart.length === 0) return;
  
  fetch('../api/auth/session.php')
    .then(response => response.json())
    .then(data => {
      if (data.authenticated) {
        window.location.href = 'checkout.html';
      } else {
        alert('Please login to proceed with checkout');
        window.location.href = 'login.html';
      }
    })
    .catch(error => console.error('Error checking session:', error));
}

// Close dropdowns when clicking outside
document.addEventListener('click', function(event) {
  const cartPanel = document.getElementById('cartPanel');
  const floatingCart = document.querySelector('.floating-cart');
  const userMenu = document.querySelector('.user-menu');
  const modalOverlay = document.getElementById('selectionModal');
  const modalContent = modalOverlay.querySelector('.modal-content');
  
  if (!cartPanel.contains(event.target) && !floatingCart.contains(event.target)) {
    cartPanel.classList.remove('open');
  }

  if (!userMenu?.contains(event.target)) {
    document.getElementById('userDropdown').style.display = 'none';
  }

  if (modalOverlay.classList.contains('active') && !modalContent.contains(event.target) && !event.target.classList.contains('add-to-cart-btn')) {
    closeModal();
  }
});

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