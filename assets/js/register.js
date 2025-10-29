/**
 * WaterWorld Water Station - Registration Page Scripts (API Version)
 */

// Load NCR cities data from API
let ncrCities = {};

async function loadCities() {
    try {
        const result = await API.get('/common/cities.php');
        if (result.success) {
            ncrCities = result.data;
            populateCityDropdown();
        }
    } catch (error) {
        console.error('Error loading cities:', error);
    }
}

// Populate city dropdown
function populateCityDropdown() {
    const citySelect = document.getElementById('register_city');
    
    // Initially clear any existing options except the first one
    while (citySelect.options.length > 1) {
        citySelect.remove(1);
    }
    
    // Populate cities
    Object.keys(ncrCities).forEach(city => {
        const option = document.createElement('option');
        option.value = city;
        option.textContent = city;
        citySelect.appendChild(option);
    });
}

// Update barangay dropdown based on selected city
function updateBarangays() {
    const citySelect = document.getElementById('register_city');
    const barangaySelect = document.getElementById('register_barangay');
    const selectedCity = citySelect.value;

    // Clear barangay dropdown
    barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
    
    // Disable barangay if no city selected
    if (!selectedCity) {
        barangaySelect.disabled = true;
        return;
    }
    
    // Enable and populate barangays for selected city only
    barangaySelect.disabled = false;
    if (ncrCities[selectedCity]) {
        ncrCities[selectedCity].forEach(barangay => {
            const option = document.createElement('option');
            option.value = barangay;
            option.textContent = barangay;
            barangaySelect.appendChild(option);
        });
    }
}

// Handle registration form submission
async function handleRegistration(e) {
    e.preventDefault();
    hideMessages();
    
    const form = e.target;
    const submitBtn = form.querySelector('[type="submit"]');
    setButtonLoading(submitBtn, true);

    // Collect values from the register form (IDs in the HTML are prefixed with 'register_')
    const formData = {
        username: (document.getElementById('register_username') || {}).value ? document.getElementById('register_username').value.trim() : '',
        password: (document.getElementById('register_password') || {}).value ? document.getElementById('register_password').value.trim() : '',
        email: (document.getElementById('register_email') || {}).value ? document.getElementById('register_email').value.trim() : '',
        contact: (document.getElementById('register_contact') || {}).value ? document.getElementById('register_contact').value.trim() : '',
        first_name: (document.getElementById('register_first_name') || {}).value ? document.getElementById('register_first_name').value.trim() : '',
        last_name: (document.getElementById('register_last_name') || {}).value ? document.getElementById('register_last_name').value.trim() : '',
        street: (document.getElementById('register_street') || {}).value ? document.getElementById('register_street').value.trim() : '',
        barangay: document.getElementById('register_barangay') ? document.getElementById('register_barangay').value : '',
        city: document.getElementById('register_city') ? document.getElementById('register_city').value : '',
        province: document.getElementById('register_province') ? document.getElementById('register_province').value : 'Metro Manila'
    };

    try {
        const result = await API.post('/auth/register.php', formData);
        
        if (result.success) {
            displaySuccess(result.message);
            // Store email for OTP verification
            sessionStorage.setItem('registration_email', formData.email);
            // Show OTP form
            setTimeout(() => showOTPForm(formData.email), 1000);
        } else {
            displayErrors(result.errors || [result.message]);
        }
    } catch (error) {
        displayErrors(['Registration failed. Please try again.']);
    } finally {
        setButtonLoading(submitBtn, false);
    }
}

// Handle OTP form submission
async function handleOTPVerification(e) {
    e.preventDefault();
    hideOTPMessages();
    
    const form = e.target;
    const submitBtn = form.querySelector('[type="submit"]');
    setButtonLoading(submitBtn, true);
    
    // Get OTP from hidden field
    const otp = document.getElementById('otp-hidden').value;
    
    if (!otp || otp.length !== 6) {
        displayErrors(['Please enter complete 6-digit OTP.'], 'otp-error-container', 'otp-error-list');
        setButtonLoading(submitBtn, false);
        return;
    }
    
    try {
        const result = await API.post('/auth/verify-otp.php', { otp });
        
        if (result.success) {
            displaySuccess(result.message, 'otp-success-container', 'otp-success-message');
            // Redirect to login after success
            setTimeout(() => {
                window.location.href = 'login.html';
            }, 2000);
        } else {
            displayErrors(result.errors || [result.message], 'otp-error-container', 'otp-error-list');
        }
    } catch (error) {
        displayErrors(['OTP verification failed. Please try again.'], 'otp-error-container', 'otp-error-list');
    } finally {
        setButtonLoading(submitBtn, false);
    }
}

// Hide OTP messages
function hideOTPMessages() {
    const errorContainer = document.getElementById('otp-error-container');
    const successContainer = document.getElementById('otp-success-container');
    
    if (errorContainer) errorContainer.style.display = 'none';
    if (successContainer) successContainer.style.display = 'none';
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    loadCities();
    const regForm = document.getElementById('registration-form');
    if (regForm) {
        regForm.addEventListener('submit', handleRegistration);
    }
    
    const otpForm = document.getElementById('form-element');
    if (otpForm) {
        otpForm.addEventListener('submit', handleOTPVerification);
    }
});

// Toggle password visibility for any password field
function togglePassword(button) {
    const passwordInput = button.parentElement.querySelector('input');
    const icon = button.querySelector('i');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        icon.className = 'fa fa-eye-slash';
    } else {
        passwordInput.type = 'password';
        icon.className = 'fa fa-eye';
    }
}

// Show OTP form modal
function showOTPForm(email) {
    console.log("Showing OTP form");
    const otpEmailSpan = document.getElementById('otp-email');
    if (otpEmailSpan && email) {
        otpEmailSpan.textContent = email;
    } else if (otpEmailSpan) {
        const storedEmail = sessionStorage.getItem('registration_email');
        otpEmailSpan.textContent = storedEmail || '';
    }
    document.getElementById('otp-form').classList.add('active');
    startOTPTimer();
}

// Close OTP form modal
function closeOTPForm() {
    console.log("Closing OTP form");
    document.getElementById('otp-form').classList.remove('active');
}

// Move to next OTP input
function moveToNext(input, index) {
    if (input.value.length === 1 && index < 5) {
        document.getElementsByClassName('otp-digit')[index + 1].focus();
    }
    combineOTP();
}

// Move to previous OTP input on backspace
function moveToPrev(input, index) {
    if (event.key === 'Backspace' && input.value === '' && index > 0) {
        document.getElementsByClassName('otp-digit')[index - 1].focus();
    }
    combineOTP();
}

// Combine all OTP digits into hidden field
function combineOTP() {
    const digits = document.getElementsByClassName('otp-digit');
    let otp = '';
    for (let i = 0; i < digits.length; i++) {
        if (!digits[i].value.match(/[0-9]/)) {
            digits[i].value = '';
        }
        otp += digits[i].value;
    }
    document.getElementById('otp-hidden').value = otp;
}

// Start OTP countdown timer
function startOTPTimer() {
    let timeLeft = 60;
    const timerElement = document.getElementById('otp-timer');
    const resendButton = document.getElementById('resend-otp');
    resendButton.disabled = true;

    const timer = setInterval(() => {
        if (timeLeft <= 0) {
            clearInterval(timer);
            timerElement.textContent = 'You can now resend OTP';
            resendButton.disabled = false;
        } else {
            timerElement.textContent = 'Resend OTP in ' + timeLeft + 's';
            timeLeft--;
        }
    }, 1000);

    // Resend OTP handler
    resendButton.onclick = async () => {
        if (!resendButton.disabled) {
            resendButton.disabled = true;
            const email = sessionStorage.getItem('registration_email');
            
            if (!email) {
                timerElement.textContent = 'No email found. Please register again.';
                return;
            }
            
            try {
                // Get stored registration data and resend
                const result = await API.post('/auth/resend-otp.php', { email });
                
                if (result.success) {
                    timerElement.textContent = 'OTP resent successfully!';
                    startOTPTimer(); // Restart timer
                } else {
                    timerElement.textContent = 'Failed to resend OTP.';
                    resendButton.disabled = false;
                }
            } catch (error) {
                console.error('Resend OTP error:', error);
                timerElement.textContent = 'Failed to resend OTP. Please try again.';
                resendButton.disabled = false;
            }
        }
    };
}
