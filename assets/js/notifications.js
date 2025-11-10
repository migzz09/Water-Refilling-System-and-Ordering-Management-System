/*
  Notifications client:
   - Shows bell only when server returns authenticated response (200 + success)
   - Uses credentials: 'include' for session cookie auth
   - Polls every 30s while logged in
   - Marks unread as read when opening panel
*/

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
    const menu = document.getElementById('notificationsMenu');
    if (!menu) return;

    // Show bell immediately if we detect a client-side login indicator (prevents bell disappearing)
    if (hasSessionCookie()) {
        menu.style.display = 'block';
    } else {
        menu.style.display = 'none';
    }

    // Now verify with server and fetch notifications
    checkNotifications();
    startNotificationPolling();

    document.addEventListener('click', (e) => {
        const dropdown = document.getElementById('notificationsDropdown');
        const menu = document.getElementById('notificationsMenu');
        if (!dropdown || !menu) return;
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
        const res = await fetch('/wrsoms/api/notifications.php', {
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

function updateNotificationsList(items) {
    const list = document.getElementById('notificationsList');
    if (!list) return;
    if (!items.length) {
        list.innerHTML = '<div class="empty-notifications">No notifications</div>';
        return;
    }

    list.innerHTML = items.map(n => {
        const unread = Number(n.is_read) === 0 ? 'unread' : '';
        const message = escapeHtml(n.message || `Update for ${n.reference_id || ''}`);
        const time = formatNotificationDate(n.created_at || new Date().toISOString());
        return `
            <div class="notification-item ${unread}" data-id="${n.notification_id}" data-ref="${n.reference_id || ''}" onclick="handleNotificationClick(event)">
                <div class="notification-message">${message}</div>
                <div class="notification-time">${time}</div>
            </div>
        `;
    }).join('');
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
        await fetch('/wrsoms/api/notifications.php', {
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
        await fetch('/wrsoms/api/notifications.php', {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'mark_read', notification_id: notificationId })
        });
    } catch (err) {
        console.error('markSingleAsRead error', err);
    }
}

function handleNotificationClick(e) {
    const el = e.currentTarget || e.target.closest('.notification-item');
    if (!el) return;
    const id = el.getAttribute('data-id');
    const ref = el.getAttribute('data-ref');
    if (id) {
        el.classList.remove('unread');
        markSingleAsRead(Number(id)).then(() => checkNotifications());
    }
    if (ref) {
        window.location.href = `/wrsoms/pages/order-tracking.html?ref=${encodeURIComponent(ref)}`;
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

function createNotificationElement(notification) {
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