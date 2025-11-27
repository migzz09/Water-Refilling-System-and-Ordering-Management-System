/*
  Notifications client:
   - Shows bell only when server returns authenticated response (200 + success)
   - Uses credentials: 'include' for session cookie auth
   - Polls every 30s while logged in
   - Marks unread as read when opening panel
*/

console.log('🔔🔔🔔 NOTIFICATIONS.JS V16 - EVENT DELEGATION - LOADED AT:', new Date().toISOString());

/*
  Improved behavior:
   - Show bell immediately if a client-side login indicator exists (PHP session cookie OR localStorage account id).
   - Then verify server auth; hide if server returns 401.
*/
let notificationCheckInterval = null;

function hasSessionCookie() {
    // common PHP session cookie names: PHPSESSID, but can vary; check for PHPSESSID
    return /\bPHPSESSID=[^;]+/.test(document.cookie) || !!localStorage.getItem('account_id') || !!localStorage.getItem('user_token');
}

function initNotifications() {
    console.log('🔔 initNotifications() called');
    const menu = document.getElementById('notificationsMenu');
    if (!menu) {
        console.error('❌ notificationsMenu element not found!');
        return;
    }
    
    console.log('✅ notificationsMenu element found:', menu);

    // Show bell immediately if we detect a client-side login indicator (prevents bell disappearing)
    const hasSession = hasSessionCookie();
    console.log('Has session cookie?', hasSession);
    
    if (hasSession) {
        menu.style.display = 'block';
        console.log('✅ Showing notification bell (session detected)');
    } else {
        menu.style.display = 'none';
        console.log('⚠️ Hiding notification bell (no session)');
    }

    // Now verify with server and fetch notifications
    checkNotifications();
    startNotificationPolling();

    document.addEventListener('click', (e) => {
        const dropdown = document.getElementById('notificationsDropdown');
        const menu = document.getElementById('notificationsMenu');
        if (!dropdown || !menu) return;
        
        // Don't close if clicking on a notification item (let the item handler run first)
        if (e.target.closest('.notification-item')) {
            return;
        }
        
        if (dropdown.style.display === 'block' && !menu.contains(e.target)) {
            dropdown.style.display = 'none';
        }
    });
}

function startNotificationPolling() {
    if (notificationCheckInterval) clearInterval(notificationCheckInterval);
    notificationCheckInterval = setInterval(checkNotifications, 30000);
}

function stopNotificationPolling() {
    if (notificationCheckInterval) {
        clearInterval(notificationCheckInterval);
        notificationCheckInterval = null;
    }
}

async function checkNotifications() {
    try {
        const res = await fetch('/api/notifications.php', {
            method: 'GET',
            credentials: 'include',
            headers: { 'Accept': 'application/json' }
        });

        if (res.status === 401) {
            // not logged in server-side -> hide bell and stop polling
            const menu = document.getElementById('notificationsMenu');
            if (menu) menu.style.display = 'none';
            stopNotificationPolling();
            return;
        }

        // If server returns non-JSON or error, keep the bell visible only if client knows user is logged in
        const data = await res.json().catch(() => null);
        if (!data || !data.success) {
            // keep client-side visible if we still detect session cookie; otherwise hide
            const menu = document.getElementById('notificationsMenu');
            if (!hasSessionCookie() && menu) menu.style.display = 'none';
            return;
        }

        // authenticated: ensure bell visible and update UI
        const menu = document.getElementById('notificationsMenu');
        if (menu) menu.style.display = 'block';

        updateNotificationsBadge(data.unread_count || 0);
        updateNotificationsList(data.notifications || []);
    } catch (err) {
        console.error('Notifications: fetch error', err);
        // network issue: do not hide bell if client-side indicates user is logged in
        const menu = document.getElementById('notificationsMenu');
        if (!hasSessionCookie() && menu) menu.style.display = 'none';
    }
}

function updateNotificationsBadge(count) {
    const badge = document.getElementById('notificationsBadge');
    if (!badge) return;
    if (count > 0) {
        badge.style.display = 'flex';
        badge.textContent = count > 99 ? '99+' : String(count);
    } else {
        badge.style.display = 'none';
    }
}

// Set up event delegation ONCE when page loads
let notificationListDelegationSet = false;

function setupNotificationListDelegation() {
    if (notificationListDelegationSet) {
        console.log('⚠️ Event delegation already set up, skipping');
        return;
    }
    
    const list = document.getElementById('notificationsList');
    if (!list) {
        console.error('❌ Cannot set up event delegation - notificationsList not found');
        return;
    }
    
    console.log('✅ SETTING UP EVENT DELEGATION ON NOTIFICATIONS LIST');
    console.log('List element:', list);
    
    // Use event delegation - listen on parent, handle clicks on children
    list.addEventListener('click', async function(e) {
        console.log('🖱️🖱️🖱️ CLICK DETECTED ON NOTIFICATIONS LIST 🖱️🖱️🖱️');
        console.log('Click target:', e.target);
        console.log('Click target classList:', e.target.classList);
        console.log('Click target tagName:', e.target.tagName);
        console.log('Click currentTarget:', e.currentTarget);
        
        const notificationItem = e.target.closest('.notification-item');
        console.log('Closest .notification-item:', notificationItem);
        
        if (!notificationItem) {
            console.log('⚠️ Click was not on a notification-item, ignoring');
            return;
        }
        
        console.log('✅✅✅ CLICK WAS ON NOTIFICATION-ITEM:', notificationItem);
        console.log('Clicked notification ref:', notificationItem.getAttribute('data-ref'));
        
        // Prevent default and stop propagation
        e.preventDefault();
        e.stopPropagation();
        
        // Call the handler with a pseudo-event that has currentTarget
        await handleNotificationClickV2({
            currentTarget: notificationItem,
            preventDefault: () => {},
            stopPropagation: () => {}
        });
    }, true); // Use capture phase!
    
    notificationListDelegationSet = true;
    console.log('✅ Event delegation set up successfully');
    
    // TEST: Try to directly attach click to each item as well
    setTimeout(() => {
        const items = list.querySelectorAll('.notification-item');
        console.log('🧪 TEST: Attaching direct click handlers to', items.length, 'items');
        items.forEach((item, i) => {
            // Remove ALL existing event listeners by cloning
            const newItem = item.cloneNode(true);
            item.parentNode.replaceChild(newItem, item);
            
            // Now attach to the clean element
            newItem.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                console.log('🧪🧪🧪 DIRECT CLICK HANDLER FIRED for item', i);
                alert('CLICK DETECTED on notification ' + i + '\nRef: ' + newItem.getAttribute('data-ref') + '\n\nClick OK to continue to handler...');
                
                // Now call the actual handler
                handleNotificationClickV2({
                    currentTarget: newItem,
                    preventDefault: () => {},
                    stopPropagation: () => {}
                });
                
                return false;
            }, true);
            console.log('🧪 Attached to item', i);
        });
        console.log('🧪 All direct handlers attached');
    }, 500);
}

function updateNotificationsList(items) {
    const list = document.getElementById('notificationsList');
    if (!list) return;
    if (!items.length) {
        list.innerHTML = '<div class="empty-notifications">No notifications</div>';
        return;
    }

    console.log('Updating notifications list with', items.length, 'items');
    
    list.innerHTML = items.map(n => {
        const unread = Number(n.is_read) === 0 ? 'unread' : '';
        const message = escapeHtml(n.message || `Update for ${n.reference_id || ''}`);
        const time = formatNotificationDate(n.created_at || new Date().toISOString());
        return `
            <div class="notification-item ${unread}" data-id="${n.notification_id}" data-ref="${n.reference_id || ''}" style="background: yellow !important; cursor: pointer !important; border: 2px solid red !important;">
                <div class="notification-message">${message}</div>
                <div class="notification-time">${time}</div>
            </div>
        `;
    }).join('');
    
    // Set up event delegation if not already done
    setupNotificationListDelegation();
}

function toggleNotifications() {
    const dropdown = document.getElementById('notificationsDropdown');
    if (!dropdown) return;
    const isOpen = dropdown.style.display === 'block';
    dropdown.style.display = isOpen ? 'none' : 'block';
    if (!isOpen) {
        // user opened panel -> mark all as read (server) and clear badge (UI)
        markAllAsRead().then(() => {
            updateNotificationsBadge(0);
            const els = document.querySelectorAll('.notification-item.unread');
            els.forEach(el => el.classList.remove('unread'));
            // refresh list to get latest read flags
            checkNotifications();
        });
    }
}

async function markAllAsRead() {
    try {
        await fetch('/api/notifications.php', {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'mark_all_read' })
        });
    } catch (err) {
        console.error('markAllAsRead error', err);
    }
}

async function markSingleAsRead(notificationId) {
    try {
        await fetch('/api/notifications.php', {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'mark_read', notification_id: notificationId })
        });
    } catch (err) {
        console.error('markSingleAsRead error', err);
    }
}

async function handleNotificationClickV2(e) {
    window.NOTIF_DEBUG = true; // Global debug flag
    console.log('=== NEW HANDLER V2 CALLED ===');
    
    const el = e.currentTarget || e.target.closest('.notification-item');
    if (!el) {
        console.error('Error: No element found');
        return;
    }
    const id = el.getAttribute('data-id');
    const ref = el.getAttribute('data-ref');
    
    console.log('Notification clicked V2:', { id, ref });
    
    if (id) {
        el.classList.remove('unread');
        markSingleAsRead(Number(id)).then(() => checkNotifications());
    }
    
    if (ref) {
        console.log('Checking order status for ref:', ref);
        
        // First, try to track the order (check if it exists in active orders)
        try {
            const trackResponse = await fetch(`/api/orders/track.php?reference_id=${encodeURIComponent(ref)}`, {
                method: 'GET',
                credentials: 'include',
                headers: { 'Accept': 'application/json' }
            });
            
            console.log('Track API response status:', trackResponse.status);
            const trackData = await trackResponse.json();
            console.log('Track API data:', trackData);
            
            // Check if order was found successfully
            if (trackResponse.ok && trackData.success && trackData.order) {
                // Order exists in active orders - check if completed
                const statusId = parseInt(trackData.order.order_status_id);
                console.log('Order status ID:', statusId);
                
                if (statusId === 3) {
                    // Status 3 = Completed, redirect to transaction history
                    console.log('Order is completed, redirecting to transaction history');
                    window.location.href = `/WRSOMS/pages/usertransaction-history.html?ref=${encodeURIComponent(ref)}`;
                } else {
                    // Order is not completed yet (pending/in progress), redirect to tracking
                    console.log('Order is not completed, redirecting to tracking page');
                    window.location.href = `/WRSOMS/pages/order-tracking.html?ref=${encodeURIComponent(ref)}`;
                }
                return;
            }
            
            // If order not found (success=false or no order data), it might be archived
            // Redirect to transaction history
            console.log('Order not found in active orders, redirecting to transaction history (might be archived)');
            window.location.href = `/WRSOMS/pages/usertransaction-history.html?ref=${encodeURIComponent(ref)}`;
            
        } catch (error) {
            console.error('Error checking order status:', error);
            // On error, default to transaction history
            window.location.href = `/WRSOMS/pages/usertransaction-history.html?ref=${encodeURIComponent(ref)}`;
        }
    }
}

function formatNotificationDate(dateStr) {
    const d = new Date(dateStr);
    const now = new Date();
    const diff = now - d;
    if (diff < 60000) return 'Just now';
    if (diff < 3600000) return Math.floor(diff / 60000) + 'm ago';
    if (diff < 86400000) return Math.floor(diff / 3600000) + 'h ago';
    if (diff < 604800000) return Math.floor(diff / 86400000) + 'd ago';
    return d.toLocaleString();
}

function escapeHtml(s) {
    if (!s) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}

function createNotificationElement_OLD_UNUSED(notification) {
    console.error('❌ OLD CODE CALLED - createNotificationElement should not be used!');
    const div = document.createElement('div');
    div.className = 'notification-item' + (notification.is_read ? ' read' : '');
    div.innerHTML = `
        <div class="notification-content" data-ref="${notification.reference_id}">
            <p>${notification.message}</p>
            <small>${formatTimestamp(notification.created_at)}</small>
        </div>
    `;

    // Add click handler for tracking
    div.querySelector('.notification-content').addEventListener('click', async function() {
        const refId = this.dataset.ref;
        if (refId) {
            try {
                // Mark as read
                await markNotificationRead(notification.notification_id);
                
                // Fetch tracking details
                const response = await fetch(`api/orders/track.php?reference_id=${refId}`);
                const result = await response.json();
                
                if (result.success) {
                    // Show tracking modal
                    showTrackingDetails(result.data);
                } else {
                    alert('Order not found');
                }
            } catch (err) {
                console.error('Tracking error:', err);
                alert('Error loading tracking information');
            }
        }
    });

    return div;
}

function showTrackingDetails(order) {
    // Create/update modal
    let modal = document.getElementById('tracking-modal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'tracking-modal';
        modal.className = 'modal';
        document.body.appendChild(modal);
    }

    // Build tracking details HTML
    const html = `
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Order #${order.reference_id}</h2>
            <div class="tracking-details">
                <p class="status"><strong>Status:</strong> ${order.user_message}</p>
                <p><strong>Order Type:</strong> ${order.order_type_display}</p>
                <p><strong>Order Date:</strong> ${new Date(order.order_date).toLocaleString()}</p>
                
                ${order.batch ? `
                    <h3>Delivery Information</h3>
                    <p><strong>Vehicle:</strong> ${order.batch.vehicle} (${order.batch.vehicle_type})</p>
                    <p><strong>Staff:</strong> ${order.batch.employees || 'Not assigned'}</p>
                ` : ''}
                
                ${order.payment ? `
                    <h3>Payment Information</h3>
                    <p><strong>Payment Status:</strong> ${order.payment.payment_status}</p>
                    <p><strong>Method:</strong> ${order.payment.method_name}</p>
                    <p><strong>Amount:</strong> ₱${parseFloat(order.payment.amount_paid).toFixed(2)}</p>
                ` : ''}
            </div>
        </div>
    `;
    
    modal.innerHTML = html;

    // Add modal styles
    if (!document.getElementById('modal-styles')) {
        const style = document.createElement('style');
        style.id = 'modal-styles';
        style.textContent = `
            .modal {
                display: block;
                position: fixed;
                z-index: 1000;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0,0,0,0.4);
            }
            .modal-content {
                background-color: #fefefe;
                margin: 15% auto;
                padding: 20px;
                border: 1px solid #888;
                width: 80%;
                max-width: 600px;
                border-radius: 8px;
                position: relative;
            }
            .close {
                color: #aaa;
                float: right;
                font-size: 28px;
                font-weight: bold;
                cursor: pointer;
            }
            .close:hover {
                color: black;
            }
            .tracking-details {
                margin-top: 20px;
            }
            .tracking-details h3 {
                margin-top: 15px;
                color: #444;
            }
            .tracking-details .status {
                font-size: 1.1em;
                color: #2196F3;
            }
        `;
        document.head.appendChild(style);
    }

    // Add close handlers
    modal.querySelector('.close').onclick = () => modal.style.display = 'none';
    window.onclick = (event) => {
        if (event.target === modal) modal.style.display = 'none';
    };
}

// Initialize on DOM ready and on custom login/logout events
document.addEventListener('DOMContentLoaded', initNotifications);
document.addEventListener('login', initNotifications);
document.addEventListener('logout', () => {
    const menu = document.getElementById('notificationsMenu');
    if (menu) menu.style.display = 'none';
    stopNotificationPolling();
});

// Check if DOM is already loaded (in case script loads after DOMContentLoaded)
if (document.readyState === 'loading') {
    console.log('⏳ DOM still loading, waiting for DOMContentLoaded...');
} else {
    console.log('✅ DOM already loaded, calling initNotifications() immediately');
    initNotifications();
}

// DEBUGGING: Global click interceptor to see ALL clicks
document.addEventListener('click', function(e) {
    const notifItem = e.target.closest('.notification-item');
    if (notifItem) {
        console.log('🚨🚨🚨 GLOBAL CLICK INTERCEPTOR - NOTIFICATION CLICKED 🚨🚨🚨');
        console.log('Target:', e.target);
        console.log('Notification item:', notifItem);
        console.log('Ref:', notifItem.getAttribute('data-ref'));
        console.log('Event phase:', e.eventPhase); // 1=capture, 2=target, 3=bubble
    }
}, true); // Capture phase - this should fire FIRST

// Debug: Log that the new version is loaded
console.log('=== NOTIFICATIONS.JS V15 LOADED (Event Delegation) ===');
console.log('handleNotificationClickV2 defined:', typeof handleNotificationClickV2);