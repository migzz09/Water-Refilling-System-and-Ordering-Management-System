/**
 * WaterWorld Water Station - Order Placement Page Scripts
 */

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

        function updateBarangays() {
            const citySelect = document.getElementById('city');
            const barangaySelect = document.getElementById('barangay');
            const cities = <?php echo json_encode($ncr_cities); ?>;
            const selectedCity = citySelect.value;

            barangaySelect.innerHTML = '<option value="">Select Barangay</option>';

            if (selectedCity && cities[selectedCity]) {
                cities[selectedCity].forEach(barangay => {
                    const option = document.createElement('option');
                    option.value = barangay;
                    option.textContent = barangay;
                    barangaySelect.appendChild(option);
                });
            }
        }

        function toggleAddressFields() {
            const addressOption = document.querySelector('input[name="address_option"]:checked').value;
            const fields = ['first_name', 'last_name', 'customer_contact', 'street', 'city', 'barangay', 'province'];
            const userData = {
                first_name: <?php echo json_encode($user_address['first_name']); ?>,
                last_name: <?php echo json_encode($user_address['last_name']); ?>,
                customer_contact: <?php echo json_encode($user_address['customer_contact']); ?>,
                street: <?php echo json_encode($user_address['street']); ?>,
                city: <?php echo json_encode($user_address['city']); ?>,
                barangay: <?php echo json_encode($user_address['barangay']); ?>,
                province: <?php echo json_encode($user_address['province']); ?>
            };

            fields.forEach(field => {
                const element = document.getElementById(field);
                if (addressOption === 'account' && userData[field]) {
                    element.value = userData[field];
                    if (field === 'city' || field === 'barangay') {
                        element.disabled = true;
                    } else if (field !== 'middle_name') {
                        element.readOnly = true;
                    }
                } else {
                    if (field !== 'province' && field !== 'middle_name') {
                        element.value = '';
                        element.readOnly = false;
                        element.disabled = false;
                    }
                }
            });

            if (addressOption === 'account' && userData['city']) {
                updateBarangays();
                document.getElementById('barangay').value = userData['barangay'] || '';
            }
        }

        window.onload = function() {
            toggleAddressFields();
        };