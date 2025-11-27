// Toggle container visibility (Launch/Hide)
function toggleContainerVisibility(containerId, isVisible) {
    fetch('/WRSOMS/api/common/containers.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ container_id: String(containerId), is_visible: String(isVisible) })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            loadInventoryData();
        } else {
            alert('Failed to update visibility: ' + (res.message || 'Unknown error'));
        }
    })
    .catch(err => {
        alert('Error updating visibility: ' + err.message);
    });
}
/**
 * WaterWorld - admin_dashboard Page Scripts
 */

// Store original content on window to avoid duplicate declarations across bundled scripts
if (typeof window.originalContent === 'undefined') {
    const mainEl = document.getElementById && document.getElementById('mainContent');
    window.originalContent = mainEl ? mainEl.innerHTML : '';
}

        function showDashboard() {
            const mainContent = document.getElementById('mainContent');
            if (!mainContent) return;
            mainContent.innerHTML = '';
            mainContent.innerHTML = window.originalContent;
            document.getElementById('reportSidebar').classList.remove('active');
            const activeLi = document.querySelector('.sidebar li.active');
            if (activeLi) activeLi.classList.remove('active');
            const btn = document.querySelector('.sidebar li button[onclick="showDashboard()"]');
            if (btn && btn.parentElement) btn.parentElement.classList.add('active');
            
            // Reload dashboard data after resetting HTML
            setTimeout(() => {
                if (typeof loadDashboard === 'function') {
                    loadDashboard();
                }
            }, 50);
        }

        function toggleReportSidebar() {
            document.getElementById('reportSidebar').classList.toggle('active');
        }

        function showDailyReport(date) {
            // Default to today's date if not provided
            if (!date) {
                const today = new Date();
                const yyyy = today.getFullYear();
                const mm = String(today.getMonth() + 1).padStart(2, '0');
                const dd = String(today.getDate()).padStart(2, '0');
                date = `${yyyy}-${mm}-${dd}`;
            }
            // Render the full report inline in the dashboard using the admin API
            function ensureChartJsLoaded() {
                return new Promise((resolve, reject) => {
                    if (typeof window.Chart !== 'undefined') return resolve();
                    const src = 'https://cdn.jsdelivr.net/npm/chart.js@4.3.0/dist/chart.umd.min.js';
                    if (document.querySelector(`script[src="${src}"]`)) {
                        const s = document.querySelector(`script[src="${src}"]`);
                        s.addEventListener('load', () => resolve());
                        s.addEventListener('error', () => reject(new Error('Chart.js failed to load')));
                        return;
                    }
                    const script = document.createElement('script');
                    script.src = src;
                    script.onload = () => resolve();
                    script.onerror = () => reject(new Error('Chart.js failed to load'));
                    document.body.appendChild(script);
                });
            }

            function renderCharts(labels, revenueData, ordersData, paymentsByMethod) {
                // Revenue line
                const revCtx = document.getElementById('revenueLine').getContext('2d');
                if (window._chartRevenue) window._chartRevenue.destroy();
                window._chartRevenue = new Chart(revCtx, {
                    type: 'line',
                    data: { labels: labels, datasets: [{ label: 'Revenue (₱)', data: revenueData, tension:0.3, fill:true, borderColor:'#007bff', backgroundColor:'rgba(0,123,255,0.2)' }] },
                    options: { responsive:true, plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true, ticks:{callback:v=>'₱'+v}}} }
                });

                // Orders bar
                const ordCtx = document.getElementById('ordersBar').getContext('2d');
                if (window._chartOrders) window._chartOrders.destroy();
                window._chartOrders = new Chart(ordCtx, {
                    type: 'bar',
                    data: { labels: labels, datasets: [{ label:'Orders', data: ordersData, backgroundColor:'#28a745' }] },
                    options: { responsive:true, plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true}} }
                });

                // Payments pie
                const payCtx = document.getElementById('paymentsPie').getContext('2d');
                if (window._chartPayments) window._chartPayments.destroy();
                const pieLabels = (paymentsByMethod || []).map(p=>p.method_name || 'Method');
                const pieData = (paymentsByMethod || []).map(p=>parseFloat(p.total) || 0);
                window._chartPayments = new Chart(payCtx, {
                    type:'pie',
                    data:{ labels:pieLabels, datasets:[{ data:pieData, backgroundColor:['#007bff','#ffc107','#28a745','#dc3545','#6f42c1'] }] },
                    options:{ responsive:true, plugins:{legend:{position:'bottom'}} }
                });
            }

            function populateReport(date) {
                const apiUrl = '/WRSOMS/api/admin/daily_report.php' + (date ? ('?date=' + encodeURIComponent(date)) : '');
                fetch(apiUrl, { credentials: 'same-origin' })
                    .then(r => r.json())
                    .then(res => {
                        if (!res || !res.success) throw new Error(res && res.message ? res.message : 'Failed to fetch report');
                        const data = res.data || {};
                        // Populate stats
                        document.getElementById('reportDate').textContent = data.selected_date || '';
                        const stats = data.stats || {};
                        document.getElementById('totalRevenue').textContent = '₱ ' + (stats.total_revenue || 0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2});
                        document.getElementById('ordersToday').textContent = (stats.orders_today || 0) + ' orders';
                        document.getElementById('ordersStat').textContent = (stats.orders_today || 0);
                        document.getElementById('newCustomers').textContent = (stats.new_customers_today || 0) + ' new customers';
                        document.getElementById('newCustomersStat').textContent = (stats.new_customers_today || 0);
                        document.getElementById('completedPayments').textContent = '₱ ' + (stats.completed_payments_today || 0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2});

                        // Recent orders
                        const recentBody = document.getElementById('recentOrdersBody');
                        const recent = data.recent_orders || [];
                        recentBody.innerHTML = '';
                        if (recent.length === 0) {
                            recentBody.innerHTML = '<tr><td colspan="4" class="text-center small-muted">No recent orders</td></tr>';
                        } else {
                            recent.forEach(o => {
                                const tr = document.createElement('tr');
                                tr.innerHTML = `<td><strong>${o.reference_id}</strong></td><td>${o.customer_name || 'Guest'}</td><td>${o.order_date}</td><td class="text-end">₱ ${Number(o.total_amount).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})}</td>`;
                                recentBody.appendChild(tr);
                            });
                        }

                        // Top products
                        const topBody = document.getElementById('topProductsBody');
                        const top = data.top_products || [];
                        topBody.innerHTML = '';
                        if (top.length === 0) {
                            topBody.innerHTML = '<tr><td colspan="3" class="text-center small-muted">No sales in last 30 days</td></tr>';
                        } else {
                            top.forEach(tp => {
                                const tr = document.createElement('tr');
                                tr.innerHTML = `<td>${tp.container_type}</td><td class="text-end">${parseInt(tp.qty||0)}</td><td class="text-end">₱ ${Number(tp.revenue||0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})}</td>`;
                                topBody.appendChild(tr);
                            });
                        }

                        // Charts
                        const charts = data.charts || {};
                        renderCharts(charts.labels || [], charts.revenue_data || [], charts.orders_data || [], charts.payments_by_method || []);

                        // Prev/Next buttons
                        const prevBtn = document.getElementById('prevDayBtn');
                        const nextBtn = document.getElementById('nextDayBtn');
                        prevBtn.href = '#'; nextBtn.href = '#';
                        prevBtn.onclick = (e) => { e.preventDefault(); if (data.prev_date) populateReport(data.prev_date); };
                        nextBtn.onclick = (e) => { e.preventDefault(); if (data.next_date) populateReport(data.next_date); };

                        // populate date list in sidebar
                        const reportDatesUl = document.getElementById('reportDates');
                        if (reportDatesUl && Array.isArray(data.available_dates)) {
                            reportDatesUl.innerHTML = '';
                            data.available_dates.forEach(d => {
                                const li = document.createElement('li');
                                const a = document.createElement('a');
                                a.href = '#'; a.textContent = d;
                                a.onclick = (ev) => { ev.preventDefault(); populateReport(d); toggleReportSidebar(); };
                                li.appendChild(a); reportDatesUl.appendChild(li);
                            });
                        }

                    })
                    .catch(err => {
                        console.error('Failed to load report data:', err);
                        document.getElementById('mainContent').innerHTML = '<div class="container py-4"><p style="color:#ef4444;">Failed to load report data. ' + (err.message || '') + '</p></div>';
                    });
            }

            // Update sidebar active state and inject the full report HTML skeleton into mainContent
            if (typeof updateActiveNav === 'function') {
                try { updateActiveNav('showDailyReport()'); } catch (e) { console.error('updateActiveNav failed:', e); }
            }
            const mainContent = document.getElementById('mainContent');
            if (mainContent) {
                mainContent.innerHTML = `
        <div class="container py-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-0">Daily Sales Report</h2>
                    <div class="small-muted">Overview for <span id="reportDate">-</span></div>
                </div>
                <div>
                    <button type="button" onclick="toggleReportSidebar()" class="btn btn-outline-primary me-2" title="Date Selector">📅</button>
                    <a id="prevDayBtn" href="#" class="btn btn-outline-primary">⬅ Previous Day</a>
                    <a id="nextDayBtn" href="#" class="btn btn-outline-primary">Next Day ➡</a>
                </div>
            </div>
            <div class="row g-3 mb-4">
                <div class="col-md-3"><div class="card p-3"><div class="small-muted">Total Revenue</div><div class="stat" id="totalRevenue">₱ 0.00</div><div class="stat-sub mt-1" id="ordersToday">0 orders</div></div></div>
                <div class="col-md-3"><div class="card p-3"><div class="small-muted">Orders</div><div class="stat" id="ordersStat">0</div><div class="stat-sub mt-1" id="newCustomers">0 new customers</div></div></div>
                <div class="col-md-3"><div class="card p-3"><div class="small-muted">New Customers</div><div class="stat" id="newCustomersStat">0</div><div class="stat-sub mt-1">Registered</div></div></div>
                <div class="col-md-3"><div class="card p-3"><div class="small-muted">Completed Payments</div><div class="stat" id="completedPayments">₱ 0.00</div><div class="stat-sub mt-1">Paid status</div></div></div>
            </div>
            <div class="row g-3 mb-4">
                <div class="col-lg-7"><div class="card p-3"><h6 class="mb-3">Revenue — Last 7 days</h6><canvas id="revenueLine"></canvas></div></div>
                <div class="col-lg-5"><div class="card p-3 mb-3"><h6 class="mb-3">Orders — Last 7 days</h6><canvas id="ordersBar"></canvas></div><div class="card p-3"><h6 class="mb-3">Payments by Method — Last 30 days</h6><canvas id="paymentsPie" style="height:240px"></canvas></div></div>
            </div>
            <div class="row g-3"><div class="col-lg-7"><div class="card p-3"><h6 class="mb-3">Recent Orders</h6><div class="table-responsive"><table class="table"><thead class="small-muted"><tr><th>Reference</th><th>Customer</th><th>Date</th><th class="text-end">Total</th></tr></thead><tbody id="recentOrdersBody"><tr><td colspan="4" class="text-center small-muted">Loading...</td></tr></tbody></table></div></div></div><div class="col-lg-5"><div class="card p-3"><h6 class="mb-3">Top Selling Containers (30 days)</h6><div class="table-responsive"><table class="table"><thead class="small-muted"><tr><th>Container</th><th class="text-end">Qty</th><th class="text-end">Revenue</th></tr></thead><tbody id="topProductsBody"><tr><td colspan="3" class="text-center small-muted">Loading...</td></tr></tbody></table></div></div></div></div>
        </div>`;
            }

            // Ensure Chart.js is loaded then populate
            ensureChartJsLoaded().then(() => populateReport(date)).catch(err => {
                console.error('Chart.js load error:', err);
                document.getElementById('mainContent').innerHTML = '<div class="container py-4"><p style="color:#ef4444;">Chart library failed to load.</p></div>';
            });
        }

        function fetchDates() {
            fetch('/WRSOMS/api/admin/daily_report.php?get_dates=true')
                    .then(response => response.json())
                    .then(res => {
                        if (!res.success) return;
                        const dates = res.data.available_dates || [];
                        const ul = document.getElementById('reportDates');
                        ul.innerHTML = '';
                        dates.forEach(d => {
                            const li = document.createElement('li');
                            const a = document.createElement('a');
                            a.href = '#';
                            a.textContent = d;
                            a.onclick = () => showDailyReport(d);
                            li.appendChild(a);
                            ul.appendChild(li);
                        });
                    })
                    .catch(error => console.error('Error fetching dates (API):', error));
        }

        // Ensure Bootstrap bundle is only added once
        (function ensureBootstrap() {
            const src = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js';
            if (!document.querySelector(`script[src="${src}"]`)) {
                const bootstrapScript = document.createElement('script');
                bootstrapScript.src = src;
                document.body.appendChild(bootstrapScript);
                // store reference to avoid redeclaration warnings
                window.bootstrapScript = bootstrapScript;
            } else if (typeof window.bootstrapScript === 'undefined') {
                // if script exists but we didn't set window ref earlier, set it now
                window.bootstrapScript = document.querySelector(`script[src="${src}"]`);
            }
        })();

        // Expose toggle function for inline handlers
        if (typeof window !== 'undefined') {
            window.toggleContainerVisibility = typeof toggleContainerVisibility === 'function' ? toggleContainerVisibility : (window.toggleContainerVisibility || function(){});
        }

        // --- Water Types management (admin) ---
        function showWaterTypesModal() {
            // create modal if not exists
            if (!document.getElementById('waterTypesModal')) {
                const modal = document.createElement('div');
                modal.id = 'waterTypesModal';
                modal.style = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;z-index:9999;';
                modal.innerHTML = `
                    <div style="position:relative;background:#fff;padding:20px;border-radius:8px;max-width:640px;width:90%;">
                        <button onclick="closeWaterTypesModal()" aria-label="Close" style="position:absolute;right:12px;top:12px;border:none;background:transparent;font-size:22px;line-height:1;cursor:pointer;">&times;</button>
                        <h4 style="margin-top:0;">Manage Water Types</h4>
                        <div id="waterTypesList" style="max-height:300px;overflow:auto;margin-bottom:12px;"></div>
                        <form id="waterTypeForm" onsubmit="saveWaterType(event)">
                            <input type="hidden" id="wt_id" value="">
                            <div style="margin-bottom:8px;"><label style="display:block;font-weight:600;">Type name</label><input id="wt_name" required style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px;"></div>
                            <div style="margin-bottom:8px;"><label style="display:block;font-weight:600;">Description</label><textarea id="wt_desc" rows="3" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px;"></textarea></div>
                            <div style="display:flex;gap:8px;justify-content:flex-end;"><button type="button" onclick="closeWaterTypesModal()" class="btn btn-secondary">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
                        </form>
                    </div>`;
                document.body.appendChild(modal);
            }
            document.getElementById('waterTypesModal').style.display = 'flex';
            loadWaterTypes();
        }

        function closeWaterTypesModal() {
            const m = document.getElementById('waterTypesModal');
            if (m) m.style.display = 'none';
        }

        function loadWaterTypes() {
            const list = document.getElementById('waterTypesList');
            if (!list) return;
            list.innerHTML = 'Loading...';
            fetch('/WRSOMS/api/common/water_types.php', { credentials: 'same-origin' })
                .then(r => r.json())
                .then(items => {
                    list.innerHTML = '';
                    if (!Array.isArray(items) || items.length === 0) { list.innerHTML = '<div class="small-muted">No water types yet.</div>'; return; }
                    items.forEach(wt => {
                        const row = document.createElement('div');
                        row.style = 'padding:8px;border-bottom:1px solid #f1f1f1;display:flex;justify-content:space-between;align-items:center;gap:12px;';
                        row.innerHTML = `<div style="flex:1;"><strong>${escapeHtml(wt.type_name)}</strong><div class="small-muted">${escapeHtml(wt.description||'')}</div></div>
                            <div style="display:flex;gap:8px;"><button class="btn btn-sm btn-info" onclick="editWaterType(${wt.water_type_id})">Edit</button><button class="btn btn-sm btn-danger" onclick="deleteWaterType(${wt.water_type_id})">Delete</button></div>`;
                        list.appendChild(row);
                    });
                })
                .catch(err => { list.innerHTML = '<div style="color:#ef4444;">Failed to load water types</div>'; console.error(err); });
        }

        function editWaterType(id) {
            // fetch single type from common list
            fetch('/WRSOMS/api/common/water_types.php', { credentials: 'same-origin' })
                .then(r => r.json())
                .then(items => {
                    const wt = items.find(x => Number(x.water_type_id) === Number(id));
                    if (!wt) return showAdminToast('Water type not found', 'error');
                    document.getElementById('wt_id').value = wt.water_type_id;
                    document.getElementById('wt_name').value = wt.type_name;
                    document.getElementById('wt_desc').value = wt.description || '';
                });
        }

        function saveWaterType(e) {
            e.preventDefault();
            const id = document.getElementById('wt_id').value;
            const name = document.getElementById('wt_name').value.trim();
            const desc = document.getElementById('wt_desc').value.trim();
            if (!name) return showAdminToast('Type name required', 'error');
            const payload = { type_name: name, description: desc };
            if (id) payload.water_type_id = id;

            fetch('/WRSOMS/api/admin/water_types.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    showAdminToast('Saved', 'success');
                    document.getElementById('wt_id').value = '';
                    document.getElementById('wt_name').value = '';
                    document.getElementById('wt_desc').value = '';
                    loadWaterTypes();
                } else showAdminToast('Save failed: ' + (res.message||'Unknown'), 'error');
            })
            .catch(err => showAdminToast('Save failed: ' + err.message, 'error'));
        }

        function deleteWaterType(id) {
            if (!confirm('Delete this water type?')) return;
            fetch('/WRSOMS/api/admin/water_types.php', {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ water_type_id: id })
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) { showAdminToast('Deleted', 'success'); loadWaterTypes(); } else showAdminToast('Delete failed: '+(res.message||'Unknown'),'error');
            })
            .catch(err => showAdminToast('Delete failed: ' + err.message, 'error'));
        }
