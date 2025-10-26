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
                body: JSON.stringify(formData)
            });

            const result = await response.json();

            if (result.success) {
                // Login successful
                successMessage.textContent = result.message;
                successContainer.style.display = 'block';
                
                // Redirect to homepage after short delay
                setTimeout(() => {
                    window.location.href = '../index.html';
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
