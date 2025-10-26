/**
 * WaterWorld - admin_dashboard Page Scripts
 */

let originalContent = document.getElementById('mainContent').innerHTML;

        function showDashboard() {
            const mainContent = document.getElementById('mainContent');
            mainContent.innerHTML = '';
            mainContent.innerHTML = originalContent;
            document.getElementById('reportSidebar').classList.remove('active');
            document.querySelector('.sidebar li.active').classList.remove('active');
            document.querySelector('.sidebar li button[onclick="showDashboard()"]').parentElement.classList.add('active');
        }

        function toggleReportSidebar() {
            document.getElementById('reportSidebar').classList.toggle('active');
        }

        function showDailyReport(date = '<?php echo date('Y-m-d'); ?>') {
            fetch('daily_report.php?date=' + encodeURIComponent(date))
                .then(response => response.text())
                .then(data => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(data, 'text/html');
                    const content = doc.querySelector('.container').outerHTML;
                    const scripts = Array.from(doc.querySelectorAll('script')).map(script => script.textContent).join('\n');
                    document.getElementById('mainContent').innerHTML = content;

                    const scriptElement = document.createElement('script');
                    scriptElement.textContent = scripts;
                    document.getElementById('mainContent').appendChild(scriptElement);

                    document.querySelector('.sidebar li.active').classList.remove('active');
                    document.querySelector('.sidebar li button[onclick="showDailyReport()"]').parentElement.classList.add('active');

                    fetchDates();
                })
                .catch(error => {
                    console.error('Error fetching daily report:', error);
                    document.getElementById('mainContent').innerHTML = '<p>Error loading daily report. Please try again.</p>';
                });
        }

        function fetchDates() {
            fetch('daily_report.php?get_dates=true')
                .then(response => response.json())
                .then(dates => {
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
                .catch(error => console.error('Error fetching dates:', error));
        }

        const bootstrapScript = document.createElement('script');
        bootstrapScript.src = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js';
        document.body.appendChild(bootstrapScript);