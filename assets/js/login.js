/**
 * Login Page JavaScript
 * Handles login form submission via API
 */

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('login-form');
    const errorContainer = document.getElementById('error-container');
    const errorList = document.getElementById('error-list');
    const successContainer = document.getElementById('success-container');
    const successMessage = document.getElementById('success-message');
    const submitBtn = document.getElementById('submit-btn');

    // Handle form submission
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        // Hide previous messages
        errorContainer.style.display = 'none';
        successContainer.style.display = 'none';
        errorList.innerHTML = '';

        // Disable submit button
        submitBtn.disabled = true;
        submitBtn.value = 'Logging in...';

        // Get form data
        const formData = {
            username: document.getElementById('username').value.trim(),
            password: document.getElementById('password').value.trim()
        };

        try {
            // Make API call
            const response = await fetch('/WRSOMS/api/auth/login.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'same-origin', // Ensure cookies are sent/received
                body: JSON.stringify(formData)
            });

            const result = await response.json();
            
            // Debug: Log response
            console.log('Login response:', result);

            if (result.success) {
                // Login successful
                successMessage.textContent = result.message;
                successContainer.style.display = 'block';
                
                // Check if user is admin or if redirect parameter is set
                const urlParams = new URLSearchParams(window.location.search);
                const redirect = urlParams.get('redirect');
                const isAdmin = result.data && (result.data.is_admin === 1 || result.data.is_admin === '1');
                
                // Debug logging
                console.log('Login response:', result);
                console.log('is_admin value:', result.data?.is_admin);
                console.log('is_admin type:', typeof result.data?.is_admin);
                console.log('isAdmin check result:', isAdmin);
                
                // Determine redirect destination
                let redirectUrl = '../index.html';
                if (isAdmin || redirect === 'admin') {
                    redirectUrl = '/WRSOMS/pages/admin/admin-dashboard.html';
                    console.log('Redirecting to admin dashboard');
                } else {
                    console.log('Redirecting to index');
                }
                
                // Redirect after short delay
                setTimeout(() => {
                    window.location.href = redirectUrl;
                }, 1000);
            } else {
                // Login failed
                displayErrors(result.errors || [result.message]);
                submitBtn.disabled = false;
                submitBtn.value = 'Login';
            }
        } catch (error) {
            console.error('Login error:', error);
            displayErrors(['An error occurred. Please try again.']);
            submitBtn.disabled = false;
            submitBtn.value = 'Login';
        }
    });

    // Display error messages
    function displayErrors(errors) {
        errorList.innerHTML = '';
        errors.forEach(error => {
            const li = document.createElement('li');
            li.textContent = error;
            errorList.appendChild(li);
        });
        errorContainer.style.display = 'block';
    }

    // Check for success message in URL (from registration)
    const urlParams = new URLSearchParams(window.location.search);
    const successParam = urlParams.get('success');
    if (successParam) {
        successMessage.textContent = successParam;
        successContainer.style.display = 'block';
    }
});
