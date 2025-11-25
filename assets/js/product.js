/**
 * WaterWorld Water Station - Product Page Scripts
 */

// Global state
const state = {
  cart: [],
  selectedProduct: null,
  selectedWaterType: null,
  selectedOrderType: null,
  selectedQuantity: 1,
  editingItem: null
};

// Helper to return correct image path depending on page folder
function getImageSrc(filename) {
  const file = filename || 'placeholder.svg';
  // If we're inside /pages/ use ../assets, otherwise assets/
  const base = window.location.pathname.includes('/pages/') ? '../assets/images/' : 'assets/images/';
  return base + file;
}
// Initialize page
document.addEventListener('DOMContentLoaded', async function() {
  // Initialize centralized auth UI if available, and load cart for all pages
  try {
    if (window.initAuthUI) await initAuthUI();
  } catch (err) {
    console.error('initAuthUI failed:', err);
  }

  // helper to safely parse JSON and surface HTML responses for debugging
  async function safeParseJSON(response) {
    const contentType = response.headers.get('content-type') || '';
    const text = await response.text();
    if (!contentType.includes('application/json')) {
      const snippet = text.length > 1000 ? text.slice(0, 1000) + '... (truncated)' : text;
      throw new Error('Expected JSON but received: ' + snippet);
    }
    try {
      return JSON.parse(text);
    } catch (e) {
      const snippet = text.length > 1000 ? text.slice(0, 1000) + '... (truncated)' : text;
      throw new Error('Invalid JSON response: ' + snippet);
    }
  }

  // Always load cart so floating cart works on index and product pages
  try {
    await loadCart();
  } catch (err) {
    console.error('loadCart failed:', err);
  }

  // If this page contains the products grid, initialize full product listing
  if (document.getElementById('productsGrid')) {
    try {
      const [productsResponse, waterTypesResponse, orderTypesResponse] = await Promise.all([
        fetch('/WRSOMS/api/common/containers.php'),
        fetch('/WRSOMS/api/common/water_types.php'),
        fetch('/WRSOMS/api/common/order_types.php')
      ]);

      if (!productsResponse.ok) throw new Error(`Products HTTP error! status: ${productsResponse.status}`);
      const products = await safeParseJSON(productsResponse);
      renderProducts(products);

      if (!waterTypesResponse.ok) throw new Error(`Water types HTTP error! status: ${waterTypesResponse.status}`);
      const waterTypes = await safeParseJSON(waterTypesResponse);
      renderWaterTypes(waterTypes);

      if (!orderTypesResponse.ok) throw new Error(`Order types HTTP error! status: ${orderTypesResponse.status}`);
      const orderTypes = await safeParseJSON(orderTypesResponse);
      state.orderTypes = Array.isArray(orderTypes) ? orderTypes : [];
      renderOrderTypes(orderTypes);
    } catch (error) {
      console.error('Error initializing product page:', error);
      const grid = document.getElementById('productsGrid');
      if (grid) {
        grid.innerHTML = `
          <div class="error" style="text-align: center; padding: 2rem;">
            <i class="fa fa-exclamation-triangle" style="font-size: 2rem; color: #dc3545;"></i>
            <p style="margin-top: 1rem;">Error loading data. Please try refreshing the page.</p>
            <p style="color: #666; font-size: 0.9rem;">${error.message}</p>
          </div>
        `;
      }
    }
  } else {
    // If not on product listing page, we still want the modal selection options available for editing cart
    try {
      const [waterTypesResponse, orderTypesResponse] = await Promise.all([
        fetch('/WRSOMS/api/common/water_types.php'),
        fetch('/WRSOMS/api/common/order_types.php')
      ]);
      if (waterTypesResponse.ok) {
        const waterTypes = await safeParseJSON(waterTypesResponse);
        // render only if modal exists
        if (document.getElementById('waterTypeOptions')) renderWaterTypes(waterTypes);
      }
      if (orderTypesResponse.ok) {
        const orderTypes = await safeParseJSON(orderTypesResponse);
        if (document.getElementById('orderTypeOptions')) renderOrderTypes(orderTypes);
      }
    } catch (err) {
      console.error('Error loading modal options on non-product page:', err);
    }
  }

});

function renderProducts(products) {
  const grid = document.getElementById('productsGrid');
  if (!products || products.length === 0) {
    grid.innerHTML = '<div class="no-products">No products available</div>';
    return;
  }
  
  try {
    grid.innerHTML = products.map(product => {
      const imageSrc = `/WRSOMS/assets/images/${product.image || 'placeholder.svg'}`;
      return `
        <div class="product-card">
          <img src="${imageSrc}" 
               alt="${product.water_type ? product.water_type + ' - ' : ''}${product.container_type}" 
               class="product-image"
               onerror="this.src='/WRSOMS/assets/images/placeholder.svg'">
          <h3 class="product-title">${product.container_type} Container</h3>
          <p class="product-price">₱${Number(product.price).toFixed(2)}</p>
          ${product.purchase_price ? `<p class="product-purchase-price" style="color:#0066cc; font-weight:600;">Purchase price: ₱${Number(product.purchase_price).toFixed(2)}</p>` : ''}
          <p class="product-description">${getProductDescription(product.container_type)}</p>
            <button class="btn btn-primary btn-block add-to-cart-btn" 
              data-id="${product.container_id}" 
              data-name="${product.container_type}"
              data-price="${product.price}"
              data-purchase-price="${product.purchase_price !== null && product.purchase_price !== undefined ? product.purchase_price : ''}"
              data-image="${product.image || 'placeholder.svg'}">
            <i class="fa fa-shopping-cart"></i> Add to Cart
          </button>
        </div>
      `;
    }).join('');
  } catch (error) {
    console.error('Error rendering products:', error, products);
    grid.innerHTML = '<div class="error">Error loading products. Please try again.</div>';
  }
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
  container.innerHTML = `<h4>Order Type</h4>` + types.map(type => {
    const isPurchaseNew = String(type.type_name).trim() === 'Purchase New Container/s';
    // FIXED: Use container-specific purchase_price with proper fallback
    const displayPrice = isPurchaseNew ? (
      (state.selectedProduct && state.selectedProduct.purchase_price && !isNaN(Number(state.selectedProduct.purchase_price))) 
        ? Number(state.selectedProduct.purchase_price) 
        : (type.price !== null && !isNaN(Number(type.price)) ? Number(type.price) : 
          // Fallback based on container type/id if available
          (state.selectedProduct && (state.selectedProduct.id === 1 || state.selectedProduct.id === 2) ? 250 : 100))
    ) : null;
    const priceHtml = isPurchaseNew ? `<div class="order-type-desc" style="color: #0066cc; font-weight: 500;">(Price: ₱${displayPrice.toFixed(2)} per container)</div>` : '';
    return `
      <div class="order-type-option" data-id="${type.order_type_id}" data-name="${type.type_name}">
        <input type="radio" 
               name="order_type" 
               id="order_${type.order_type_id}" 
               value="${type.order_type_id}" 
               aria-label="${type.type_name}"
               title="${type.type_name}">
        <label for="order_${type.order_type_id}">
          <div class="order-type-name">${type.type_name}</div>
          ${priceHtml}
        </label>
      </div>
    `;
  }).join('');

  // Add click event listeners
  container.querySelectorAll('.order-type-option').forEach(option => {
    option.addEventListener('click', () => {
      const id = parseInt(option.dataset.id, 10);
      const name = option.dataset.name;
      selectOrderType(id, name);
    });
  });
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
    const purchasePrice = addBtn.dataset.purchasePrice ? parseFloat(addBtn.dataset.purchasePrice) : null;
    addToCart(id, name, price, image, purchasePrice);
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
function addToCart(id, name, price, image, purchasePrice) {
  openModal(id, name, price, image, false, null, purchasePrice);
}

function openModal(productId, productName, productPrice, productImage, isEdit = false, item = null, purchasePrice = null) {
  state.selectedProduct = { id: productId, name: productName, price: productPrice, image: productImage, purchase_price: purchasePrice };
  state.selectedWaterType = null;
  state.selectedOrderType = null;
  state.editingItem = isEdit ? item : null;
  // initialize modal quantity
  state.selectedQuantity = (isEdit && item && item.quantity) ? item.quantity : 1;

  // reflect quantity in modal UI if present
  const qtyEl = document.getElementById('modalQuantityValue');
  if (qtyEl) qtyEl.textContent = String(state.selectedQuantity);
  // update +/- disabled state
  updateModalQtyControls();
  // focus modal for keyboard interaction
  const modalContent = document.querySelector('.modal-content');
  if (modalContent) modalContent.focus();
  // attach keyboard handler
  document.addEventListener('keydown', modalKeyHandler);

  document.getElementById('modalTitle').textContent = isEdit ? 'Edit Cart Item' : 'Select Water and Order Type';
  document.getElementById('selectionModal').classList.add('active');
  document.getElementById('confirmSelection').disabled = true;

  // Re-render order types to show correct purchase price for this product
  if (state.orderTypes && state.orderTypes.length > 0) {
    renderOrderTypes(state.orderTypes);
  }

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
  // when editing, initialize selected quantity from item
  if (item.quantity) {
    state.selectedQuantity = item.quantity;
    const qtyEl = document.getElementById('modalQuantityValue');
    if (qtyEl) qtyEl.textContent = String(state.selectedQuantity);
  }

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
  console.log('Water type selected:', state.selectedWaterType); // Debug
  const options = document.querySelectorAll('[name="water_type"]');
  options.forEach(input => {
    const card = input.closest('.water-type-option');
    if (parseInt(input.value) === id) {
      if (card) {
        card.classList.add('selected');
        const typeName = card.querySelector('.water-type-name')?.textContent || name;
        input.checked = true;
        input.title = typeName;
        state.selectedWaterType = { id, name: typeName };
      }
    } else {
      if (card) card.classList.remove('selected');
      input.checked = false;
    }
  });
  updateConfirmButton();
}

function selectOrderType(id, name) {
  state.selectedOrderType = { id, name };
  console.log('Order type selected:', state.selectedOrderType); // Debug
  const options = document.querySelectorAll('[name="order_type"]');
  options.forEach(input => {
    const card = input.closest('.order-type-option');
    if (parseInt(input.value) === id) {
      if (card) {
        card.classList.add('selected');
        const typeName = card.querySelector('.order-type-name')?.textContent || name;
        input.checked = true;
        input.title = typeName;
        state.selectedOrderType = { id, name: typeName };
      }
    } else {
      if (card) card.classList.remove('selected');
      input.checked = false;
    }
  });
  updateConfirmButton();
}

function updateConfirmButton() {
  console.log('Updating confirm button:', { // Debug
    selectedWaterType: state.selectedWaterType,
    selectedOrderType: state.selectedOrderType,
    shouldEnable: !!(state.selectedWaterType && state.selectedOrderType)
  });
  document.getElementById('confirmSelection').disabled = !(state.selectedWaterType && state.selectedOrderType);
}

// Change modal quantity (called by +/- buttons)
function modalChangeQuantity(change) {
  const currentQty = state.selectedQuantity || 1;
  const newQty = currentQty + change;
  
  // Enforce boundaries
  if (newQty < 1 || newQty > 99) return;
  
  state.selectedQuantity = newQty;
  const qtyEl = document.getElementById('modalQuantityValue');
  if (qtyEl) qtyEl.textContent = String(newQty);
  updateModalQtyControls();
}

function updateModalQtyControls() {
  const minus = document.getElementById('modalQtyMinus');
  const plus = document.getElementById('modalQtyPlus');
  const qty = state.selectedQuantity || 1;
  if (minus) {
    if (qty <= 1) {
      minus.classList.add('disabled');
      minus.setAttribute('aria-disabled', 'true');
    } else {
      minus.classList.remove('disabled');
      minus.setAttribute('aria-disabled', 'false');
    }
  }
  if (plus) {
    if (qty >= 99) {
      plus.classList.add('disabled');
      plus.setAttribute('aria-disabled', 'true');
    } else {
      plus.classList.remove('disabled');
      plus.setAttribute('aria-disabled', 'false');
    }
  }
}

// Keyboard support: ArrowUp/Right to increase, ArrowDown/Left to decrease, Esc to close
function modalKeyHandler(e) {
  const modal = document.getElementById('selectionModal');
  if (!modal || !modal.classList.contains('active')) return;
  if (e.key === 'ArrowUp' || e.key === 'ArrowRight') {
    e.preventDefault();
    modalChangeQuantity(1);
  } else if (e.key === 'ArrowDown' || e.key === 'ArrowLeft') {
    e.preventDefault();
    modalChangeQuantity(-1);
  } else if (e.key === 'Escape') {
    closeModal();
  }
}

function confirmSelection() {
  // Get selected values from the radio inputs
  const waterTypeInput = document.querySelector('input[name="water_type"]:checked');
  const orderTypeInput = document.querySelector('input[name="order_type"]:checked');

  console.log('Selections:', { // Debug log
    waterType: waterTypeInput?.value,
    orderType: orderTypeInput?.value,
    product: state.selectedProduct
  });

  // Check if all required selections are made
  if (!waterTypeInput || !orderTypeInput || !state.selectedProduct) {
    alert('Please select both water type and order type before confirming.');
    return;
  }

  // Get the selected water type and order type names from their parent elements
  const waterTypeName = waterTypeInput.closest('.water-type-option').querySelector('.water-type-name').textContent;
  const orderTypeName = orderTypeInput.closest('.order-type-option').querySelector('.order-type-name').textContent;

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
    quantity: state.selectedQuantity || (state.editingItem ? state.editingItem.quantity : 1),
    water_type_id: parseInt(waterTypeInput.value, 10),
    water_type_name: waterTypeName,
    order_type_id: parseInt(orderTypeInput.value, 10),
    order_type_name: orderTypeName,
    order_type_price: (function(){
      try {
        const ot = (state.orderTypes || []).find(t => parseInt(t.order_type_id,10) === parseInt(orderTypeInput.value,10));
        if (ot && ot.price !== null && !isNaN(Number(ot.price))) return Number(ot.price);
      } catch(e) {}
      return null;
    })()
    ,
    purchase_price: state.selectedProduct.purchase_price || null
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
  // remove modal keyboard listener
  document.removeEventListener('keydown', modalKeyHandler);
}

function updateCart() {
  const cartCount = document.getElementById('cartCount');
  const cartItemsContainer = document.getElementById('cartItems');
  const cartTotal = document.getElementById('cartTotal');
  const checkoutBtn = document.getElementById('checkoutBtn');

  const totalItems = state.cart.reduce((sum, item) => sum + item.quantity, 0);
  
  // Calculate total price including container purchase price if applicable
  const totalPrice = state.cart.reduce((sum, item) => {
    const isPurchaseNew = item.order_type_name === 'Purchase New Container/s';
    // FIXED: Use container-specific purchase_price
    const unitPrice = isPurchaseNew ? (
      (item.purchase_price && !isNaN(Number(item.purchase_price))) ? Number(item.purchase_price) : 
      ((item.order_type_price && !isNaN(Number(item.order_type_price))) ? Number(item.order_type_price) : 
      // Use container-specific price based on container_id
      (item.id === 1 || item.id === 2 ? 250 : 100))
    ) : Number(item.price || 0);
    return sum + (unitPrice * item.quantity);
  }, 0);

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
    if (state.cart.length === 0) {
      cartItemsContainer.innerHTML = `
        <div class="empty-cart">
          <div class="empty-cart-icon">🛒</div>
          <p>Your cart is empty</p>
        </div>
      `;
      checkoutBtn.disabled = true;
    } else {
  cartItemsContainer.innerHTML = state.cart.map(item => {
        const isPurchaseNew = item.order_type_name === 'Purchase New Container/s';
        // FIXED: Use container-specific purchase_price
        const unitPrice = isPurchaseNew ? (
          (item.purchase_price && !isNaN(Number(item.purchase_price))) ? Number(item.purchase_price) : 
          ((item.order_type_price && !isNaN(Number(item.order_type_price))) ? Number(item.order_type_price) : 
          // Use container-specific price based on container_id
          (item.id === 1 || item.id === 2 ? 250 : 100))
        ) : Number(item.price || 0);
        
        return `
          <div class="cart-item" data-item-id="${item.id}">
            <img src="${getImageSrc(item.image)}" 
                 alt="${item.name}" 
                 class="cart-item-image"
                 onerror="this.src='${getImageSrc('placeholder.svg')}'">
            <div class="cart-item-details">
              <div class="cart-item-title">
                ${item.name} Container
                <div class="cart-item-subtitle">${item.water_type_name}, ${item.order_type_name}</div>
              </div>
              <div class="cart-item-price">
                <div style="font-weight: 500;">₱${unitPrice.toFixed(2)} each</div>
                ${isPurchaseNew ? `<div style="font-size: 0.85em; color: #666; margin-top: 2px;">₱${unitPrice.toFixed(2)} total per container (already filled)</div>` : '<div style="font-size: 0.85em; color: #666; margin-top: 2px;">₱' + Number(item.price || 0).toFixed(2) + ' refill price</div>'}
              </div>
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
        `;
      }).join('');
      checkoutBtn.disabled = false;
    }
  }

  // Save cart to server using API helper (ensures correct baseURL)
  if (typeof API !== 'undefined' && API.post) {
    API.post('/orders/update_cart.php', { cart: state.cart })
      .then(result => {
        if (result && result.success === false) {
          console.error('Server rejected cart update:', result.message || result);
        }
      })
      .catch(error => {
        console.error('Error updating cart:', error);
        // non-blocking
      });
  } else {
    // Fallback to relative fetch if API helper is not available
    fetch('../api/orders/update_cart.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({ cart: state.cart })
    }).catch(error => console.error('Error updating cart (fallback):', error));
  }
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

async function editItem(id, water_type_id, order_type_id) {
  const item = state.cart.find(item => 
    item.id === id && 
    item.water_type_id === water_type_id && 
    item.order_type_id === order_type_id
  );
  if (!item) return;

  // Set editing state
  state.editingItem = item;
  state.selectedProduct = {
    id: item.id,
    name: item.name,
    price: item.price,
    purchase_price: item.purchase_price || null,
    image: item.image
  };
  state.selectedQuantity = item.quantity;
  state.selectedWaterType = item.water_type_id;
  state.selectedOrderType = item.order_type_id;

  // Show modal
  const modal = document.getElementById('selectionModal');
  modal.classList.add('active');

  // Set modal title
  const modalTitle = document.getElementById('modalTitle');
  if (modalTitle) {
    modalTitle.textContent = `Edit ${item.name} Container`;
  }

  // Set quantity
  const quantityValue = document.getElementById('modalQuantityValue');
  if (quantityValue) {
    quantityValue.textContent = item.quantity;
  }

  // Load water types and order types
  try {
    const [waterTypesResponse, orderTypesResponse] = await Promise.all([
      fetch('/WRSOMS/api/common/water_types.php'),
      fetch('/WRSOMS/api/common/order_types.php')
    ]);

    if (waterTypesResponse.ok) {
      const waterTypes = await waterTypesResponse.json();
      renderWaterTypes(waterTypes);
      
      // Pre-select current water type
      setTimeout(() => {
        const waterInput = document.querySelector(`input[name="waterType"][value="${item.water_type_id}"]`);
        if (waterInput) {
          waterInput.checked = true;
          selectWaterType(item.water_type_id);
        }
      }, 50);
    }

    if (orderTypesResponse.ok) {
      const orderTypes = await orderTypesResponse.json();
      renderOrderTypes(orderTypes);
      
      // Pre-select current order type
      setTimeout(() => {
        const orderInput = document.querySelector(`input[name="orderType"][value="${item.order_type_id}"]`);
        if (orderInput) {
          orderInput.checked = true;
          selectOrderType(item.order_type_id);
        }
      }, 50);
    }

    // Enable confirm button
    const confirmBtn = document.getElementById('confirmSelection');
    if (confirmBtn) {
      confirmBtn.disabled = false;
    }
  } catch (error) {
    console.error('Error loading options for editing:', error);
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
  // Use centralized API helper so base URL is consistent across pages
  if (typeof API !== 'undefined' && API.get) {
    API.get('/orders/get_cart.php')
      .then(data => {
        if (data && data.cart) {
          state.cart = data.cart;
          updateCart();
        }
      })
      .catch(error => {
        console.error('Error loading cart:', error);
      });
  } else {
    // Fallback for pages that include product.js without API helper
    fetch('../api/orders/get_cart.php')
      .then(response => {
        if (!response.ok) throw new Error('Failed to load cart');
        return response.json();
      })
      .then(data => {
        if (data.cart) {
          state.cart = data.cart;
          updateCart();
        }
      })
      .catch(error => {
        console.error('Error loading cart (fallback):', error);
      });
  }
}

function checkout() {
  if (state.cart.length === 0) return;
  // Use API helper if available
  const authCheck = (typeof API !== 'undefined' && API.get) ? API.get('/auth/session.php') : fetch('../api/auth/session.php').then(r => r.json());
  authCheck
    .then(data => {
      if (data && data.authenticated) {
        // Check if we're on index.html (root) or in pages folder
        const isRootPage = window.location.pathname.includes('/index.html') || window.location.pathname.endsWith('/WRSOMS/');
        window.location.href = isRootPage ? 'pages/checkout.html' : 'checkout.html';
      } else {
        alert('Please login to proceed with checkout');
        // Check if we're on index.html (root) or in pages folder
        const isRootPage = window.location.pathname.includes('/index.html') || window.location.pathname.endsWith('/WRSOMS/');
        window.location.href = isRootPage ? 'pages/login.html' : 'login.html';
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

// Prevent clicks inside the cart panel or user dropdown from bubbling to the document
// (this avoids the cart closing when buttons inside it replace DOM nodes during their handlers)
(() => {
  const cartPanel = document.getElementById('cartPanel');
  if (cartPanel) {
    cartPanel.addEventListener('click', function(e) {
      e.stopPropagation();
    });
  }

  const userDropdown = document.getElementById('userDropdown');
  if (userDropdown) {
    userDropdown.addEventListener('click', function(e) {
      e.stopPropagation();
    });
  }
})();

// logout is provided by shared auth.js (initAuthUI / logout)