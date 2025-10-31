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

// Current selected payment method (default COD)
let selectedPaymentMethod = 'cod';

// Initialize page
document.addEventListener('DOMContentLoaded', async function() {
  try {
    // Use API helper to ensure auth and consistent base path
    await API.requireAuth();
    const authData = await API.checkAuth();
    // API.checkAuth returns { success, authenticated, user: { customer_id, username, ... } }
    const sessionUser = authData && authData.user ? authData.user : null;
    if (sessionUser && sessionUser.username) {
      const userNameEl = document.getElementById('userName');
      if (userNameEl) userNameEl.textContent = sessionUser.username;
    }

    if (sessionUser && sessionUser.customer_id) {
      // wait for saved address to be loaded so we don't race with the "renderTempAddressSelection"
      await loadUserAddress(sessionUser.customer_id);
    }

    await loadCart();
    setupCityDropdown();
    setupDeliveryDateMin();
    // Ensure initial visual state for payment options and keyboard wiring
    try {
      selectPaymentMethod(selectedPaymentMethod);
      wirePaymentOptionKeys();
    } catch (e) {}
    // Ensure address selector exists. If there's already a saved address, renderAddressSelection
    // was called by loadUserAddress; otherwise render the temp selection.
    try {
      if (!window.savedAddress) renderTempAddressSelection();
    } catch (err) {
      console.info('Address selector render skipped or failed:', err);
    }

  // Debug banner removed from global UI; debug is available inside the Registered Address box

  } catch (error) {
    console.error('Error initializing page:', error);
  }
});

// Load user's saved address
async function loadUserAddress(customerId) {
  try {
    // addresses.php returns { addresses: [...] } stored in session
    let resp = await API.get(`/common/addresses.php`);
    let address = resp && Array.isArray(resp.addresses) && resp.addresses.length ? resp.addresses[0] : null;

    // If the session-backed addresses endpoint returned nothing, fall back to a direct profile API
    if (!address) {
      try {
        const profileResp = await API.get('/auth/profile.php');
        if (profileResp && profileResp.success && profileResp.profile) {
          address = {
            street: profileResp.profile.street || '',
            barangay: profileResp.profile.barangay || '',
            city: profileResp.profile.city || '',
            province: profileResp.profile.province || '',
            first_name: profileResp.profile.first_name || '',
            last_name: profileResp.profile.last_name || '',
            customer_contact: profileResp.profile.customer_contact || ''
          };
        }
      } catch (err) {
        console.info('Profile fallback failed:', err);
      }
    }

    if (address) {
        // Keep saved address in memory and render a compact selector
        window.savedAddress = address;
        renderAddressSelection(address);
        // make the saved address obvious: scroll into view and briefly highlight
        setTimeout(() => {
          try {
            const savedEl = document.getElementById('address-saved');
            if (savedEl) {
              savedEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
              const prev = savedEl.style.boxShadow;
              savedEl.style.boxShadow = '0 0 0 4px rgba(11,116,222,0.12)';
              setTimeout(() => { savedEl.style.boxShadow = prev; }, 1600);
            }
          } catch (e) { /* no-op */ }
        }, 160);
        // Also prefill the hidden form so users can edit if they choose
        const streetEl = document.getElementById('street');
        if (streetEl) streetEl.value = address.street || '';
  // Do not auto-fill contact number into the form for privacy — leave contact field empty
        if (address.city) {
          const cityEl = document.getElementById('city');
          if (cityEl) {
            cityEl.value = address.city;
            updateBarangays(address.city);
            if (address.barangay) {
              setTimeout(() => {
                const barangayEl = document.getElementById('barangay');
                if (barangayEl) barangayEl.value = address.barangay;
              }, 100);
            }
          }
        }
    } 
    else {
      // No saved address returned
      window.savedAddress = null;
      renderTempAddressSelection();
      selectedAddressType = 'other';
    }
  } catch (error) {
    console.error('Error loading address:', error);
  }
}

  // Selected address type: 'saved' or 'other'
  let selectedAddressType = 'saved';

  function renderAddressSelection(address) {
    const container = document.getElementById('addressList');
    if (!container) return;
  // Consider a saved address present only if it has at least one non-empty component
  const hasSaved = Boolean(address && (address.street || address.barangay || address.city));
    const savedHtml = `
      <div class="address-box" id="address-saved" tabindex="0">
        <label class="address-radio">
          <input type="radio" name="deliveryAddress" value="saved" ${hasSaved && address ? 'checked' : ''} ${!hasSaved ? 'disabled' : ''} />
          <div class="address-content">
            <div class="address-lines">
              <strong>Registered Address</strong>
              ${hasSaved ? `
                <div class="address-summary" style="color:#0b4f9a;font-weight:600;font-size:13px;">${escapeHtml(address.street || '')} — ${address.barangay ? escapeHtml(address.barangay) + ', ' : ''}${address.city ? escapeHtml(address.city) : ''}</div>
              ` : `
                <div class="address-text" style="color:#666;">No registered address on file</div>
              `}
            </div>
          </div>
        </label>
      </div>
    `;

    const otherHtml = `
      <div class="address-box address-add" id="address-other" tabindex="0">
        <label class="address-radio">
          <input type="radio" name="deliveryAddress" value="other" />
          <div class="address-content">
            <strong>Deliver to a different address</strong>
            <div class="address-sub">Click the arrow to add or edit an alternative delivery address</div>
            <button type="button" class="address-open-btn" aria-label="Open address form">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg">
                <path d="M9 18l6-6-6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
              </svg>
            </button>
          </div>
        </label>
      </div>
    `;

    container.innerHTML = `<div class="address-grid">${savedHtml}${otherHtml}</div>`;

    // No debug button: we display a concise address summary only.

    // Defensive DOM update: ensure address text nodes are explicitly set so they
    // appear even if innerHTML rendering behaves unexpectedly in some browsers.
    if (hasSaved) {
      const savedBox = container.querySelector('#address-saved');
      if (savedBox) {
        const summaryEl = savedBox.querySelector('.address-summary');
        if (summaryEl) {
          summaryEl.textContent = (address.street || '') + ' — ' + (address.barangay ? address.barangay + ', ' : '') + (address.city || '');
        }
      }
    }

    // Wire interactions
    const savedRadio = container.querySelector('input[value="saved"]');
    const otherRadio = container.querySelector('input[value="other"]');

    if (savedRadio && !savedRadio.disabled) {
      savedRadio.addEventListener('change', () => {
        selectedAddressType = 'saved';
        // ensure form fields remain synced
        const a = window.savedAddress || {};
        if (a.street) document.getElementById('street').value = a.street;
        if (a.city) document.getElementById('city').value = a.city;
  if (a.barangay) document.getElementById('barangay').value = a.barangay;
        // Update barangay options and vehicle info to reflect selected saved address
        if (a.city) {
          try {
            updateBarangays(a.city);
          } catch (e) { /* ignore if function missing */ }
          // allow barangay select to populate then set value and update vehicle info
          setTimeout(() => {
            const barangayEl = document.getElementById('barangay');
            if (barangayEl && a.barangay) barangayEl.value = a.barangay;
            updateVehicleInfo(window.cart || []);
          }, 80);
        } else {
          updateVehicleInfo(window.cart || []);
        }
      });
    }

    if (otherRadio) {
      otherRadio.addEventListener('change', () => {
        selectedAddressType = 'other';
        // don't open modal here — only open when user explicitly clicks the arrow button
        try { updateVehicleInfo(window.cart || []); } catch (e) {}
      });

      // clicking the whole other-box should select 'other' but not open the modal
      const otherBox = document.getElementById('address-other');
      if (otherBox) otherBox.addEventListener('click', () => {
        if (otherRadio) otherRadio.checked = true;
        selectedAddressType = 'other';
        try { updateVehicleInfo(window.cart || []); } catch (e) {}
      });

      // clicking the arrow button opens the modal to add/edit the alternative address
      const otherOpenBtn = otherBox ? otherBox.querySelector('.address-open-btn') : null;
      if (otherOpenBtn) {
        otherOpenBtn.addEventListener('click', (ev) => {
          ev.stopPropagation();
          if (otherRadio) otherRadio.checked = true;
          selectedAddressType = 'other';
          openAddressFormModal();
        });
      }
    }
    // Clicking the saved-address box should select the saved radio (but not open modal)
    const savedBox = container.querySelector('#address-saved');
    if (savedBox) {
      savedBox.addEventListener('click', (evt) => {
        const r = container.querySelector('input[value="saved"]');
        if (r && !r.disabled) {
          r.checked = true;
          selectedAddressType = 'saved';
          r.dispatchEvent(new Event('change', { bubbles: true }));
        }
      });
    }
    // Refresh vehicle info to reflect current selection immediately
    try { updateVehicleInfo(window.cart || []); } catch (e) { /* ignore */ }
  }

  function openAddressFormModal() {
    const modal = document.getElementById('addressFormModal');
    if (!modal) return;
    // Populate or clear the form depending on whether a temp address exists.
    // This prevents the form from being autofilled with the registered address
    // when the user is opening the modal to add a different address.
    const streetEl = document.getElementById('street');
    const cityEl = document.getElementById('city');
    const barangayEl = document.getElementById('barangay');

    if (window.tempAddress) {
      if (streetEl) streetEl.value = window.tempAddress.street || '';
      if (cityEl) {
        cityEl.value = window.tempAddress.city || '';
        try { updateBarangays(cityEl.value); } catch (e) {}
        if (window.tempAddress.barangay) {
          setTimeout(() => { if (barangayEl) barangayEl.value = window.tempAddress.barangay; }, 100);
        }
      }
    } else {
      // Clear to avoid showing the registered address by default
      if (streetEl) streetEl.value = '';
      if (cityEl) cityEl.value = '';
      if (barangayEl) barangayEl.innerHTML = '<option value="">Select Barangay</option>';
    }

    // show modal for user to input alternative address
    modal.classList.add('active');
  }

  // Simple HTML escape helper
  function escapeHtml(str) {
    return String(str || '').replace(/[&<>"']/g, function (s) {
      return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#39;"})[s];
    });
  }

  // Handle address form submission locally as a temporary delivery address (not stored)
  const addressForm = document.getElementById('addressForm');
  if (addressForm) {
    addressForm.addEventListener('submit', function(e) {
      e.preventDefault();
      const form = e.target;
      const temp = {
        street: form.street.value || '',
        barangay: form.barangay.value || '',
        city: form.city.value || ''
      };
      window.tempAddress = temp;
      // mark selected as 'other' and close modal (which will re-render and update vehicle info)
      selectedAddressType = 'other';
      onCloseAddressForm();
    });
  }

  function renderTempAddressSelection() {
    const container = document.getElementById('addressList');
    if (!container) return;
  const temp = window.tempAddress;
  const saved = window.savedAddress;
  // Consider a saved address present only if it has at least one non-empty component
  const hasSaved = Boolean(saved && (saved.street || saved.barangay || saved.city));
    const savedHtml = `
      <div class="address-box" id="address-saved">
        <label class="address-radio">
          <input type="radio" name="deliveryAddress" value="saved" ${selectedAddressType==='saved' ? 'checked' : ''} ${!hasSaved ? 'disabled' : ''} />
          <div class="address-content">
            <div class="address-lines">
              <strong>Registered Address</strong>
              ${hasSaved ? `
                <div class="address-summary" style="margin-top:6px;color:#0b4f9a;font-weight:600;font-size:13px;">${escapeHtml(saved.street || '')} — ${saved.barangay ? escapeHtml(saved.barangay) + ', ' : ''}${saved.city ? escapeHtml(saved.city) : ''}</div>
              ` : `<div class="address-text" style="color:#666;">No registered address on file</div>`}
            </div>
          </div>
        </label>
      </div>
    `;

    const tempHtml = temp ? `
      <div class="address-box" id="address-other">
        <label class="address-radio">
          <input type="radio" name="deliveryAddress" value="other" ${selectedAddressType==='other' ? 'checked' : ''} />
          <div class="address-content">
            <strong>Alternative Address</strong>
            <div class="address-summary" style="margin-top:6px;color:#0b4f9a;font-weight:600;font-size:13px;">${escapeHtml(temp.street || '')} — ${temp.barangay ? escapeHtml(temp.barangay) + ', ' : ''}${temp.city ? escapeHtml(temp.city) : ''}</div>
            <button type="button" class="address-open-btn" aria-label="Edit alternative address">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg">
                <path d="M9 18l6-6-6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
              </svg>
            </button>
          </div>
        </label>
      </div>
    ` : `
      <div class="address-box address-add" id="address-other">
        <label class="address-radio">
          <input type="radio" name="deliveryAddress" value="other" ${selectedAddressType==='other' ? 'checked' : ''} />
          <div class="address-content">
            <strong>Deliver to a different address</strong>
            <div class="address-sub">Click to add or edit an alternative delivery address</div>
            <button type="button" class="address-open-btn" aria-label="Add alternative address">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg">
                <path d="M9 18l6-6-6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
              </svg>
            </button>
          </div>
        </label>
      </div>
    `;

    container.innerHTML = `<div class="address-grid">${savedHtml}${tempHtml}</div>`;

    // re-wire interactions
    const otherBox = document.getElementById('address-other');
    if (otherBox) otherBox.addEventListener('click', () => {
      selectedAddressType = 'other';
      // select radio only, do not open modal
      const r = container.querySelector('input[value="other"]');
      if (r) r.checked = true;
      try { updateVehicleInfo(window.cart || []); } catch (e) {}
    });
    // arrow button opens the modal
    const otherOpenBtn = otherBox ? otherBox.querySelector('.address-open-btn') : null;
    if (otherOpenBtn) {
      otherOpenBtn.addEventListener('click', (ev) => {
        ev.stopPropagation();
        const r = container.querySelector('input[value="other"]');
        if (r) r.checked = true;
        selectedAddressType = 'other';
        openAddressFormModal();
      });
    }
    // Clicking the saved-address box should select the saved radio (but not open modal)
    const savedBox = container.querySelector('#address-saved');
    if (savedBox) {
      savedBox.addEventListener('click', (evt) => {
        const r = container.querySelector('input[value="saved"]');
        if (r && !r.disabled) {
          r.checked = true;
          selectedAddressType = 'saved';
          r.dispatchEvent(new Event('change', { bubbles: true }));
        }
      });
    }
    const savedRadio = container.querySelector('input[value="saved"]');
    if (savedRadio && !savedRadio.disabled) savedRadio.addEventListener('change', () => {
      selectedAddressType = 'saved';
      // sync selected saved address into form and update vehicle info
      const s = saved || window.savedAddress || {};
      if (s.street) document.getElementById('street').value = s.street;
      if (s.city) document.getElementById('city').value = s.city;
      try { updateBarangays(s.city); } catch (e) {}
      setTimeout(() => {
  const barangayEl = document.getElementById('barangay');
  if (barangayEl && s.barangay) barangayEl.value = s.barangay;
  updateVehicleInfo(window.cart || []);
      }, 80);
    });

    // Defensive DOM update for temp/saved rendering: update summary element
    if (hasSaved) {
      const savedBox = container.querySelector('#address-saved');
      if (savedBox) {
        const summaryEl = savedBox.querySelector('.address-summary');
        if (summaryEl) summaryEl.textContent = (saved.street || '') + ' — ' + (saved.barangay ? saved.barangay + ', ' : '') + (saved.city || '');
      }
    }
    // If there's a temp address, ensure its summary is populated too
    if (temp) {
      const otherBoxEl = container.querySelector('#address-other');
      if (otherBoxEl) {
        const otherSummary = otherBoxEl.querySelector('.address-summary');
        if (otherSummary) otherSummary.textContent = (temp.street || '') + ' — ' + (temp.barangay ? temp.barangay + ', ' : '') + (temp.city || '');
      }
    }
    // Refresh vehicle info to reflect current selection immediately
    try { updateVehicleInfo(window.cart || []); } catch (e) { /* ignore */ }
  }

// Load cart items
async function loadCart() {
  try {
    const data = await API.get('/orders/get_cart.php');
    if (data && data.cart && data.cart.length > 0) {
      // store globally for other functions
      window.cart = data.cart;
      renderCart(data.cart);
      updateVehicleInfo(data.cart);
    } else {
      // redirect to products if cart empty
      window.location.href = 'product.html';
    }
  } catch (error) {
    console.error('Error loading cart:', error);
  }
}

function renderCart(cart) {
  const cartItems = document.getElementById('cartItems');
  let total = 0;

  cartItems.innerHTML = cart.map(item => {
    const isPurchaseNew = item.order_type_name === 'Purchase New Container/s';
    const unitPrice = isPurchaseNew ? 250 : Number(item.price || 0);
    const itemTotal = unitPrice * item.quantity;
    total += itemTotal;
    return `
      <div class="cart-item">
        <div class="cart-item-details">
          <div class="cart-item-title">
            ${item.name} Container
            <div class="cart-item-subtitle">${item.water_type_name}, ${item.order_type_name}</div>
          </div>
          <div class="cart-item-qty">Quantity: <strong>${item.quantity}</strong></div>
        </div>
        <div class="cart-item-price">₱${itemTotal.toFixed(2)}${isPurchaseNew ? ' <div style="font-size:0.85em;color:#666;">(Already filled)</div>' : ''}</div>
      </div>
    `;
  }).join('');

  // Update summary fields (IDs exist in checkout.html)
  const subtotalEl = document.getElementById('subtotal');
  const totalEl = document.getElementById('total');

  if (subtotalEl) subtotalEl.textContent = '₱' + total.toFixed(2);
  // No delivery fees — total equals subtotal
  if (totalEl) totalEl.textContent = '₱' + total.toFixed(2);

  // Enable checkout button when cart is rendered
  const checkoutBtn = document.getElementById('checkoutBtn');
  if (checkoutBtn) checkoutBtn.disabled = false;
}

function updateVehicleInfo(cart) {
  const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
  // Determine city based on selected address source (saved, temp, or form)
  let city = '';
  if (selectedAddressType === 'saved' && window.savedAddress) {
    city = window.savedAddress.city || '';
  } else if (selectedAddressType === 'other' && window.tempAddress) {
    city = window.tempAddress.city || '';
  } else {
    const cityEl = document.getElementById('city');
    city = cityEl ? cityEl.value : '';
  }
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
    const btn = document.getElementById('placeOrderBtn') || document.getElementById('checkoutBtn');
    if (btn) btn.disabled = true;
  } else {
    info.innerHTML = `
      <div class="alert alert-info">
        <i class="fa fa-truck"></i>
        Your order will be delivered by ${vehicleType} (Capacity: ${capacity} containers)
      </div>
    `;
    const btn = document.getElementById('placeOrderBtn') || document.getElementById('checkoutBtn');
    if (btn) btn.disabled = false;
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

// Debug helper removed for production: debug UI was used during development and
// intentionally stripped from the checkout page per request.

function validateForm() {
  // If user selects saved address, we don't require the address fields again
  const required = [{ id: 'deliveryDate', message: 'Delivery date is required' }];
  if (selectedAddressType === 'other') {
    required.unshift(
      { id: 'street', message: 'Street address is required' },
      { id: 'city', message: 'City is required' },
      { id: 'barangay', message: 'Barangay is required' },
    );
  }

  for (const field of required) {
    const element = document.getElementById(field.id);
    if (!element || !element.value) {
      alert(field.message);
      if (element) element.focus();
      return false;
    }
  }

  // Validate contact number if present (saved address may contain it). If absent it's optional here.
  let contact = '';
  if (window.savedAddress && window.savedAddress.customer_contact) {
    contact = window.savedAddress.customer_contact;
  } else if (window.tempAddress && window.tempAddress.customer_contact) {
    contact = window.tempAddress.customer_contact;
  }

  if (contact && !contact.match(/^09\d{9}$/)) {
    alert('Please enter a valid contact number (e.g., 09XXXXXXXXX) in your profile or saved address.');
    return false;
  }

  return true;
}

function placeOrder() {
  if (!validateForm()) return;

  // Build order details based on selected address type
  let street = '';
  let city = '';
  let barangay = '';
  let contactNumber = '';
  if (selectedAddressType === 'saved' && window.savedAddress) {
    street = window.savedAddress.street || '';
    city = window.savedAddress.city || '';
    barangay = window.savedAddress.barangay || '';
    contactNumber = window.savedAddress.customer_contact || '';
  } else if (window.tempAddress) {
    street = window.tempAddress.street || '';
    city = window.tempAddress.city || '';
    barangay = window.tempAddress.barangay || '';
    contactNumber = window.tempAddress.customer_contact || '';
  } else {
    street = document.getElementById('street').value;
    city = document.getElementById('city').value;
    barangay = document.getElementById('barangay').value;
    // Contact number removed from modal — leave empty here (may come from saved profile/address)
    contactNumber = '';
  }

  const orderDetails = {
    street,
    city,
    barangay,
    contactNumber,
    deliveryDate: document.getElementById('deliveryDate').value,
    paymentMethod: selectedPaymentMethod
  };

  showOrderConfirmation(orderDetails);
}

// Handler for payment option boxes
function selectPaymentMethod(method) {
  selectedPaymentMethod = method;
  // Visually mark the selected option
  const options = document.querySelectorAll('.payment-option');
  options.forEach(opt => {
    if (opt.getAttribute('data-method') === method) {
      opt.classList.add('selected');
      opt.setAttribute('aria-pressed', 'true');
    } else {
      opt.classList.remove('selected');
      opt.setAttribute('aria-pressed', 'false');
    }
  });
}

// Make payment-option elements keyboard-activatable (Enter / Space)
function wirePaymentOptionKeys() {
  const options = document.querySelectorAll('.payment-option');
  options.forEach(opt => {
    opt.addEventListener('keydown', (ev) => {
      if (ev.key === 'Enter' || ev.key === ' ') {
        ev.preventDefault();
        const method = opt.getAttribute('data-method');
        if (method) selectPaymentMethod(method);
      }
    });
  });
}

function showOrderConfirmation(details) {
  const modal = document.getElementById('orderConfirmation');
  const orderDetails = modal.querySelector('.order-details');
  // Build an itemized view using the current cart if available
  const cart = Array.isArray(window.cart) ? window.cart : [];
  let itemsHtml = '';
  let subtotal = 0;
  if (cart.length) {
    cart.forEach(item => {
      const isPurchaseNew = item.order_type_name === 'Purchase New Container/s';
      const unitPrice = isPurchaseNew ? 250 : Number(item.price || 0);
      const itemTotal = unitPrice * (Number(item.quantity) || 0);
      subtotal += itemTotal;
      itemsHtml += `<div class="conf-item"><div><div class="name">${item.name} Container</div><div class="qty">${item.water_type_name}, ${item.order_type_name} × ${item.quantity}</div></div><div class="price">₱${itemTotal.toFixed(2)}</div></div>`;
    });
  } else {
    itemsHtml = '<div class="confirmation-note">No items in cart</div>';
  }

  const deliveryDateText = details.deliveryDate ? new Date(details.deliveryDate).toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) : 'Not specified';
  const vehicle = getVehicleType(details.city || (window.savedAddress && window.savedAddress.city) || '');

  orderDetails.innerHTML = `
    <div class="confirmation-details">
      <div class="confirmation-block">
        <h3>Delivery Address</h3>
        <div><strong>${details.street || ''}</strong></div>
        <div>${details.barangay || ''}${details.barangay && details.city ? ', ' : ''}${details.city || ''}</div>
        <div style="margin-top:8px;color:#666;">Contact: ${details.contactNumber || 'Not provided'}</div>
        <div class="confirmation-note" style="margin-top:8px;">Delivery vehicle: <strong>${vehicle}</strong></div>
      </div>

      <div class="confirmation-block">
        <h3>Order Summary</h3>
        <div class="confirmation-items">${itemsHtml}</div>
        <div class="conf-summary"><div>Subtotal</div><div>₱${subtotal.toFixed(2)}</div></div>
        <div class="conf-summary" style="margin-top:6px;"><div>Grand Total</div><div>₱${subtotal.toFixed(2)}</div></div>
        <div class="confirmation-note">Delivery Date: <strong>${deliveryDateText}</strong></div>
        <div class="confirmation-note">Payment method: <strong>${details.paymentMethod === 'cod' ? 'Cash on Delivery' : 'GCash'}</strong></div>
      </div>
    </div>
  `;

  // show modal
  modal.classList.add('active');
}

async function confirmOrder() {
  // Build order data based on selected address source
  let street = '';
  let city = '';
  let barangay = '';
  let contactNumber = '';
  if (selectedAddressType === 'saved' && window.savedAddress) {
    street = window.savedAddress.street || '';
    city = window.savedAddress.city || '';
    barangay = window.savedAddress.barangay || '';
    contactNumber = window.savedAddress.customer_contact || '';
  } else if (window.tempAddress) {
    street = window.tempAddress.street || '';
    city = window.tempAddress.city || '';
    barangay = window.tempAddress.barangay || '';
    contactNumber = window.tempAddress.customer_contact || '';
  } else {
    street = document.getElementById('street').value;
    city = document.getElementById('city').value;
    barangay = document.getElementById('barangay').value;
    // Contact number removed from modal — leave empty here (may come from saved profile/address)
    contactNumber = '';
  }

  const orderData = {
    street,
    city,
    barangay,
    contactNumber,
    deliveryDate: document.getElementById('deliveryDate').value,
    paymentMethod: selectedPaymentMethod
  };

  try {
    // Build payload expected by the API: include items and numeric ids
    const cart = Array.isArray(window.cart) ? window.cart : [];
    const items = cart.map(item => {
      const isPurchaseNew = item.order_type_name === 'Purchase New Container/s';
      const unitPrice = isPurchaseNew ? 250 : Number(item.price || 0);
      return {
        container_id: item.id,
        quantity: Number(item.quantity || 0),
        price: unitPrice
      };
    });

    // Map payment method to numeric id used by the API (assumption: 1 = COD, 2 = GCash)
    const paymentMethodId = selectedPaymentMethod === 'gcash' ? 2 : 1;

    const payload = {
      order_type: 1,
      delivery_option: 1,
      payment_method: paymentMethodId,
      items: items,
      notes: '',
      // include delivery/address info for completeness (API may ignore but helpful for server-side logging)
      delivery: {
        street: orderData.street,
        city: orderData.city,
        barangay: orderData.barangay,
        contact: orderData.contactNumber,
        deliveryDate: orderData.deliveryDate
      }
    };

    // Use centralized API helper so baseURL is applied consistently (/WRSOMS/api)
    const result = await API.post('/orders/create.php', payload);
    // API.post attaches __status and returns { success: false, message: text } for non-JSON responses
    if (result && result.success) {
      // API returns data with reference_id and total_amount. Build an order object for the receipt UI.
      const data = result.data || {};
      const orderObj = {
        reference_id: data.reference_id || data.referenceId || '',
        order_date: new Date().toISOString(),
        delivery_date: orderData.deliveryDate,
        address: `${orderData.street || ''}${orderData.barangay ? ', ' + orderData.barangay : ''}${orderData.city ? ', ' + orderData.city : ''}`,
        customer_contact: orderData.contactNumber || (window.savedAddress && window.savedAddress.customer_contact) || 'Not provided',
        vehicle_type: getVehicleType(orderData.city || (window.savedAddress && window.savedAddress.city) || ''),
        batch_number: data.batch_number || data.batchNumber || '',
        items: cart,
        total_amount: data.total_amount || data.totalAmount || 0
      };

      showReceipt(orderObj);
    } else {
      // Attempt to surface useful error information (API.post may return message or errors)
      let errMsg = 'Failed to place order. Please try again.';
      if (result) {
        if (result.message) errMsg = result.message;
        else if (result.errors && Array.isArray(result.errors)) errMsg = result.errors.join('\n');
        else if (result.__status === 404) errMsg = 'Order API not found (404). Check server path.';
      }
      alert(errMsg);
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

function closeAddressForm() {
  const modal = document.getElementById('addressFormModal');
  if (modal) modal.classList.remove('active');
}

// When closing the address modal (either via X or programmatically), keep UI in sync
// Re-render the address selectors and refresh vehicle info so the displayed vehicle
// matches the currently-selected address (saved or temp).
function onCloseAddressForm() {
  // Ensure modal is hidden
  const modal = document.getElementById('addressFormModal');
  if (modal) modal.classList.remove('active');

  // Re-render selection so the address-list reflects any tempAddress change
  try { renderTempAddressSelection(); } catch (e) { /* ignore */ }

  // Update vehicle info based on selected address
  try { updateVehicleInfo(window.cart || []); } catch (e) { /* ignore */ }
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
  // Prefer centralized logout if available
  if (typeof window.logout === 'function') {
    return window.logout();
  }

  // Fallback
  API.post('/auth/logout.php', {}).then(data => {
    if (data && data.success) {
      window.location.href = '/WRSOMS/index.html';
    } else {
      window.location.href = '/WRSOMS/index.html';
    }
  }).catch(err => {
    console.error('Logout error fallback:', err);
    window.location.href = '/WRSOMS/index.html';
  });
}

// Delivery fees removed — no calculation required