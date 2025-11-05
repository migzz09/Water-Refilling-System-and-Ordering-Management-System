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
                            let transactionType = '';
                            switch (transaction.order_type_id) {
                                case 1:
                                    transactionType = 'Refill';
                                    break;
                                case 2:
                                    transactionType = 'Buy Container';
                                    break;
                                case 3:
                                    transactionType = 'Refill and Buy Container';
                                    break;
                                default:
                                    transactionType = 'Unknown';
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
            let receiptTypeText = '';
            switch (transaction.order_type_id) {
                case 1:
                    receiptTypeText = 'Refill';
                    break;
                case 2:
                    receiptTypeText = 'Buy Container';
                    break;
                case 3:
                    receiptTypeText = 'Refill and Buy Container';
                    break;
                default:
                    receiptTypeText = 'Unknown';
            }
            receiptType.textContent = receiptTypeText;
            receiptAmount.textContent = `₱${parseFloat(transaction.total_amount).toFixed(2)}`;
            receiptDeliveryDate.textContent = new Date(transaction.delivery_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
            receiptContainerType.textContent = transaction.container_type;
            receiptQuantity.textContent = transaction.quantity;
            receiptContainerPrice.textContent = `₱${parseFloat(transaction.container_price).toFixed(2)}`;
            receiptSubtotal.textContent = `₱${parseFloat(transaction.subtotal).toFixed(2)}`;
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