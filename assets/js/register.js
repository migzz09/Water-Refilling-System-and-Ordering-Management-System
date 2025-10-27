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
    const citySelect = document.getElementById('city');
    const barangaySelect = document.getElementById('barangay');
    
    // Initially disable barangay dropdown
    if (barangaySelect) {
        barangaySelect.disabled = true;
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
    const citySelect = document.getElementById('city');
    const barangaySelect = document.getElementById('barangay');
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

    const formData = {
        username: document.getElementById('username').value.trim(),
        password: document.getElementById('password').value.trim(),
        email: document.getElementById('email').value.trim(),
        contact: document.getElementById('contact').value.trim(),
        first_name: document.getElementById('first_name').value.trim(),
        last_name: document.getElementById('last_name').value.trim(),
        street: document.getElementById('street').value.trim(),
        barangay: document.getElementById('barangay').value,
        city: document.getElementById('city').value,
        province: 'Metro Manila'
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

// Toggle password visibility
function togglePassword() {
    const passwordInput = document.getElementById('password');
    const passwordToggle = document.querySelector('.password-toggle');
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        passwordToggle.innerHTML = `
            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
            <circle cx="12" cy="12" r="3"/>
            <line x1="3" y1="3" x2="21" y2="21" stroke="currentColor" stroke-width="2"/>
        `;
    } else {
        passwordInput.type = 'password';
        passwordToggle.innerHTML = `
            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
            <circle cx="12" cy="12" r="3"/>
        `;
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
            timerElement.textContent = `Resend OTP in ${timeLeft}s`;
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
