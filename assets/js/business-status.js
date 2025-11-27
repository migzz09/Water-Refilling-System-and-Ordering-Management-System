// Format time as HH:MM AM/PM
function formatTime(timeStr) {
    if (!timeStr) return '';
    // Accepts 'HH:MM' or 'HH:MM:SS' or Date object
    let hours, minutes;
    if (typeof timeStr === 'string') {
        const parts = timeStr.split(':');
        hours = parseInt(parts[0], 10);
        minutes = parseInt(parts[1], 10);
    } else if (timeStr instanceof Date) {
        hours = timeStr.getHours();
        minutes = timeStr.getMinutes();
    } else {
        return timeStr;
    }
    const ampm = hours >= 12 ? 'PM' : 'AM';
    hours = hours % 12;
    hours = hours ? hours : 12;
    minutes = minutes < 10 ? '0' + minutes : minutes;
    return `${hours}:${minutes} ${ampm}`;
}
/**
 * Business Status Banner
 * Shows warnings when business is closed or past cutoff time
 */

async function checkBusinessStatus() {
    try {
           // ...existing code...
        const response = await fetch('/api/common/check_order_cutoff.php');
        const data = await response.json();
        
        console.log('📊 Business status data:', data);
            // ...existing code...
        
        if (!data.success) {
            console.error('Failed to check business status');
            return;
        }

        displayBusinessStatusBanner(data);
    } catch (error) {
        console.error('Error checking business status:', error);
    }
}

function displayBusinessStatusBanner(statusData) {
    console.log('🎨 Displaying banner with status:', statusData);
    
    // Remove existing banner if any
    const existingBanner = document.getElementById('businessStatusBanner');
    if (existingBanner) {
        console.log('🗑️ Removing existing banner');
        existingBanner.remove();
    }

    // Debug: log can_place_order and reason
    // If can place order and within cutoff, no banner needed
    if (statusData.can_place_order && statusData.reason === 'within_cutoff') {
        return;
    }

        // ...existing code...

    // Determine banner type and message
    let bannerClass = '';
    let icon = '';
    let title = '';
    let message = '';

    switch (statusData.reason) {
        case 'business_closed':
            bannerClass = 'status-banner-closed';
            icon = 'fa-times-circle';
            title = "We're Closed Today";
            message = 'We are not accepting orders today. Please visit us on our next business day.';
            break;
        case 'before_opening':
            bannerClass = 'status-banner-warning';
            icon = 'fa-clock-o';
            title = 'Not Yet Open';
            message = statusData.message;
            break;
        case 'after_closing':
            bannerClass = 'status-banner-closed';
            icon = 'fa-moon-o';
            title = 'Closed for the Day';
            message = 'We have closed for today. Please place your order tomorrow during business hours.';
            break;
        case 'after_cutoff':
            bannerClass = 'status-banner-cutoff';
            icon = 'fa-exclamation-triangle';
            title = 'Order Cutoff Time Has Passed';
            message = `Today's order cutoff time (${formatTime(statusData.cutoff_time)}) has passed. Orders are no longer being accepted for today.`;
            break;
        case 'no_cutoff':
            // Business open, no cutoff enabled
            return;
        default:
            return;
    }

    // Remove any existing banner
    const oldBanner = document.getElementById('businessStatusBanner');
    if (oldBanner) {
        oldBanner.remove();
    }
    // Create banner HTML
    const banner = document.createElement('div');
    banner.id = 'businessStatusBanner';
    banner.className = `business-status-banner ${bannerClass}`;
    banner.innerHTML = `
        <div class="banner-content">
            <div class="banner-icon">
                <i class="fa ${icon}"></i>
            </div>
            <div class="banner-text">
                <div class="banner-title">${title}</div>
                <div class="banner-message">${message}</div>
                ${statusData.business_hours && statusData.business_hours.is_open ? 
                    `<div class="banner-hours">Business Hours: ${formatTime(statusData.business_hours.open_time)} - ${formatTime(statusData.business_hours.close_time)}</div>` : 
                    ''}
            </div>
        </div>
    `;
    // Append banner to body
    document.body.prepend(banner);
    }

    // DEBUG: Always show a test banner to confirm visibility
    document.addEventListener('DOMContentLoaded', async function() {
        await checkBusinessStatus();
        // Refresh every 2 minutes
        setInterval(checkBusinessStatus, 120000);
    });
// DEBUG: Always show a test banner to confirm visibility
document.addEventListener('DOMContentLoaded', async function() {
    await checkBusinessStatus();
    // Refresh every 2 minutes
    setInterval(checkBusinessStatus, 120000);
});

// Add fallback banner if API fails
function showFallbackBanner(message) {
    const nav = document.querySelector('nav.navbar');
    const banner = document.createElement('div');
    banner.className = 'business-status-banner';
    banner.style.background = '#d32f2f';
    banner.style.color = '#fff';
    banner.style.padding = '16px';
    banner.style.textAlign = 'center';
    banner.style.fontWeight = 'bold';
    banner.style.fontSize = '1.2rem';
    banner.style.marginTop = '100px';
    banner.textContent = message;
    if (nav) {
        if (nav.nextSibling) {
            nav.parentNode.insertBefore(banner, nav.nextSibling);
        } else {
            nav.parentNode.appendChild(banner);
        }
    } else {
        document.body.insertBefore(banner, document.body.firstChild);
    }
}
