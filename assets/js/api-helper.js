/**
 * API Helper Functions
 * Common utilities for making API calls
 */

const API = {
    baseURL: (function() {
        // If running on localhost and path includes /WRSOMS/, use /WRSOMS/api
        if (window.location.pathname.startsWith('/WRSOMS/')) {
            return '/WRSOMS/api';
        }
        // Otherwise, use /api for production
        return '/api';
    })(),

    /**
     * Make a GET request
     */
    async get(endpoint) {
        try {
            const response = await fetch(`${this.baseURL}${endpoint}`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'same-origin' // Ensure session cookies are sent
            });
            return await response.json();
        } catch (error) {
            console.error('API GET Error:', error);
            throw error;
        }
    },

    /**
     * Make a POST request
     */
    async post(endpoint, data) {
        try {
            const response = await fetch(`${this.baseURL}${endpoint}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'same-origin', // Ensure session cookies are sent
                body: JSON.stringify(data)
            });
            // Try to parse JSON; if response is not JSON, fallback to text
            const contentType = response.headers.get('content-type') || '';
            if (contentType.includes('application/json')) {
                const json = await response.json();
                // Attach HTTP status for callers
                json.__status = response.status;
                return json;
            } else {
                const text = await response.text();
                const payload = { success: false, message: text || 'Unexpected response', __status: response.status };
                // Log text response for debugging
                console.error('API POST non-JSON response:', text);
                return payload;
            }
        } catch (error) {
            console.error('API POST Error:', error);
            throw error;
        }
    },

    /**
     * Make a PUT request
     */
    async put(endpoint, data) {
        try {
            const response = await fetch(`${this.baseURL}${endpoint}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            });
            return await response.json();
        } catch (error) {
            console.error('API PUT Error:', error);
            throw error;
        }
    },

    /**
     * Make a DELETE request
     */
    async delete(endpoint) {
        try {
            const response = await fetch(`${this.baseURL}${endpoint}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json'
                }
            });
            return await response.json();
        } catch (error) {
            console.error('API DELETE Error:', error);
            throw error;
        }
    },

    /**
     * Check if user is authenticated
     */
    async checkAuth() {
        return await this.get('/auth/session.php');
    },

    /**
     * Redirect to login if not authenticated
     */
    async requireAuth() {
        const result = await this.checkAuth();
        if (!result.authenticated) {
            window.location.href = '/WRSOMS/pages/login.html';
            return false;
        }
        return true;
    }
};

/**
 * Display error messages in a container
 */
function displayErrors(errors, containerId = 'error-container', listId = 'error-list') {
    const container = document.getElementById(containerId);
    const list = document.getElementById(listId);
    
    if (!container || !list) return;

    list.innerHTML = '';
    
    if (Array.isArray(errors)) {
        errors.forEach(error => {
            const li = document.createElement('li');
            li.textContent = error;
            list.appendChild(li);
        });
    } else {
        const li = document.createElement('li');
        li.textContent = errors;
        list.appendChild(li);
    }
    
    container.style.display = 'block';
}

/**
 * Display success message
 */
function displaySuccess(message, containerId = 'success-container', messageId = 'success-message') {
    const container = document.getElementById(containerId);
    const messageEl = document.getElementById(messageId);
    
    if (!container || !messageEl) return;

    messageEl.textContent = message;
    container.style.display = 'block';
}

/**
 * Hide all messages
 */
function hideMessages() {
    const errorContainer = document.getElementById('error-container');
    const successContainer = document.getElementById('success-container');
    
    if (errorContainer) errorContainer.style.display = 'none';
    if (successContainer) successContainer.style.display = 'none';
}

/**
 * Show loading state on button
 */
function setButtonLoading(button, isLoading, originalText = 'Submit') {
    if (isLoading) {
        button.disabled = true;
        button.setAttribute('data-original-text', button.value || button.textContent);
        if (button.tagName === 'INPUT') {
            button.value = 'Loading...';
        } else {
            button.textContent = 'Loading...';
        }
    } else {
        button.disabled = false;
        const original = button.getAttribute('data-original-text') || originalText;
        if (button.tagName === 'INPUT') {
            button.value = original;
        } else {
            button.textContent = original;
        }
    }
}
