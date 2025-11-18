/**
 * WaterWorld - usertransaction_history Page Scripts
 */

const searchInput = document.getElementById('searchInput');
        const transactionList = document.querySelector('.transaction-list');
        const receiptModal = document.getElementById('receipt-modal');
        const receiptType = document.getElementById('receipt-type');
        const receiptAmount = document.getElementById('receipt-amount');
        const receiptDeliveryDate = document.getElementById('receipt-delivery-date');
        const receiptContainerType = document.getElementById('receipt-container-type');
        const receiptQuantity = document.getElementById('receipt-quantity');
        const receiptContainerPrice = document.getElementById('receipt-container-price');
        const receiptSubtotal = document.getElementById('receipt-subtotal');
        const receiptTotal = document.getElementById('receipt-total');
        const receiptStatus = document.getElementById('receipt-status');

        searchInput.addEventListener('input', function() {
            const searchQuery = this.value.trim();
            fetch(`/WRSOMS/api/orders/transaction_history.php?search=${encodeURIComponent(searchQuery)}`)
                .then(response => response.json())
                .then(data => {
                    transactionList.innerHTML = '';
                    if (data.transactions.length === 0) {
                        transactionList.innerHTML = '<p>No delivered transactions found.</p>';
                    } else {
                        let currentDate = '';
                        data.transactions.forEach(transaction => {
                            const transactionDate = new Date(transaction.order_date).toISOString().split('T')[0];
                            const dateHeader = new Date(transaction.order_date).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
                            const time = new Date(transaction.order_date).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }).toLowerCase();
                            
                            // Determine transaction type based on items
                            let hasRefill = false;
                            let hasPurchase = false;
                            let transactionType = '';
                            
                            if (transaction.items && transaction.items.length > 0) {
                                transaction.items.forEach(item => {
                                    if (item.order_type_id == 1) hasRefill = true;
                                    if (item.order_type_id == 2) hasPurchase = true;
                                });
                                
                                if (hasRefill && hasPurchase) {
                                    transactionType = 'Refill and Purchase Container/s';
                                } else if (hasPurchase) {
                                    transactionType = 'Purchase Container/s';
                                } else if (hasRefill) {
                                    transactionType = 'Refill';
                                } else {
                                    transactionType = 'Order';
                                }
                            } else {
                                transactionType = 'Order';
                            }
                            
                            if (currentDate !== transactionDate) {
                                transactionList.innerHTML += `<div class="date-header">${dateHeader}</div>`;
                                currentDate = transactionDate;
                            }
                            
                            const item = document.createElement('div');
                            item.className = 'transaction-item';
                            item.onclick = () => showReceipt(JSON.stringify(transaction));
                            item.innerHTML = `
                                <div class="transaction-details">
                                    <div class="transaction-type">${transactionType}</div>
                                    <div class="transaction-time">${time}</div>
                                </div>
                                <div class="transaction-amount">₱${parseFloat(transaction.total_amount).toFixed(2)}</div>
                            `;
                            transactionList.appendChild(item);
                        });
                    }
                })
                .catch(error => console.error('Error fetching data:', error));
        });

        function showReceipt(transactionData) {
            const transaction = JSON.parse(transactionData);
            
            // Determine receipt type based on items
            let hasRefill = false;
            let hasPurchase = false;
            
            if (transaction.items && transaction.items.length > 0) {
                transaction.items.forEach(item => {
                    if (item.order_type_id == 1) hasRefill = true;
                    if (item.order_type_id == 2) hasPurchase = true;
                });
            }
            
            let receiptTypeText = '';
            if (hasRefill && hasPurchase) {
                receiptTypeText = 'Refill and Purchase Container/s';
            } else if (hasPurchase) {
                receiptTypeText = 'Purchase Container/s';
            } else if (hasRefill) {
                receiptTypeText = 'Refill';
            } else {
                receiptTypeText = 'Order';
            }
            
            receiptType.textContent = receiptTypeText;
            receiptAmount.textContent = `₱${parseFloat(transaction.total_amount).toFixed(2)}`;
            receiptDeliveryDate.textContent = new Date(transaction.delivery_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
            
            // Display all items
            if (transaction.items && transaction.items.length > 0) {
                // Show first item in summary fields
                const firstItem = transaction.items[0];
                receiptContainerType.textContent = firstItem.container_type || 'N/A';
                receiptQuantity.textContent = firstItem.quantity || '0';
                receiptContainerPrice.textContent = `₱${parseFloat(firstItem.container_price || 0).toFixed(2)}`;
                receiptSubtotal.textContent = `₱${parseFloat(firstItem.subtotal || 0).toFixed(2)}`;
                
                // If multiple items, show them all (you can enhance this further)
                if (transaction.items.length > 1) {
                    // Create an items list section
                    const itemsSection = document.createElement('div');
                    itemsSection.className = 'receipt-items-details';
                    itemsSection.innerHTML = '<h4>Order Items:</h4>';
                    
                    transaction.items.forEach(item => {
                        const itemDiv = document.createElement('div');
                        itemDiv.className = 'receipt-item';
                        const itemType = item.order_type_id == 1 ? 'Refill' : 'Purchase';
                        itemDiv.innerHTML = `
                            <div><strong>${itemType}:</strong> ${item.container_type} x ${item.quantity}</div>
                            <div>₱${parseFloat(item.subtotal).toFixed(2)}</div>
                        `;
                        itemsSection.appendChild(itemDiv);
                    });
                    
                    // Insert after the standard receipt fields
                    const receiptContent = receiptModal.querySelector('.modal-content');
                    const existingItemsSection = receiptContent.querySelector('.receipt-items-details');
                    if (existingItemsSection) {
                        existingItemsSection.remove();
                    }
                    receiptContent.insertBefore(itemsSection, receiptContent.querySelector('.btn'));
                }
            } else {
                // Fallback for old format
                receiptContainerType.textContent = transaction.container_type || 'N/A';
                receiptQuantity.textContent = transaction.quantity || '0';
                receiptContainerPrice.textContent = `₱${parseFloat(transaction.container_price || 0).toFixed(2)}`;
                receiptSubtotal.textContent = `₱${parseFloat(transaction.subtotal || 0).toFixed(2)}`;
            }
            
            receiptTotal.textContent = `₱${parseFloat(transaction.total_amount).toFixed(2)}`;
            receiptStatus.textContent = transaction.delivery_status;
            transactionList.style.display = 'none';
            receiptModal.style.display = 'flex';
        }

        function goBack() {
            receiptModal.style.display = 'none';
            transactionList.style.display = 'flex';
        }

        function closeReceipt() {
            receiptModal.style.display = 'none';
            transactionList.style.display = 'flex';
        }

        // Close receipt when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target === receiptModal) {
                closeReceipt();
            }
        });

        // Add close functionality with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && receiptModal.style.display === 'flex') {
                closeReceipt();
            }
        });

        // Reveal on scroll animation
        const sections = document.querySelectorAll("section");
        const revealOnScroll = () => {
            const triggerBottom = window.innerHeight * 0.85;
            sections.forEach(section => {
                const sectionTop = section.getBoundingClientRect().top;
                if (sectionTop < triggerBottom) {
                    section.classList.add("show");
                }
            });
        };
        window.addEventListener("scroll", revealOnScroll);
        revealOnScroll();