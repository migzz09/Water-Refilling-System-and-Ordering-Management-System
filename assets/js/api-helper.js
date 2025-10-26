/**
 * API Helper Functions
 * Common utilities for making API calls
 */

const API = {
    baseURL: '/WRSOMS/api',

    /**
     * Make a GET request
     */
    async get(endpoint) {
        try {
            const response = await fetch(`${this.baseURL}${endpoint}`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json'
                }
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
                body: JSON.stringify(data)
            });
            return await response.json();
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
