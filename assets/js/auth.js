/**
 * Shared auth utilities
 * - initAuthUI() : checks session and updates header UI
 * - logout() : calls logout endpoint and redirects to home
 * Relies on global `API` from `api-helper.js` (must be included before this file).
 */

/* eslint-disable no-undef */

async function initAuthUI() {
  try {
    const result = await API.checkAuth();

    const loginBtn = document.getElementById('loginBtn');
    const registerBtn = document.getElementById('registerBtn');
    const userMenu = document.getElementById('userMenu');
    const userNameEl = document.getElementById('userName');

    if (result && result.authenticated) {
      if (loginBtn) loginBtn.style.display = 'none';
      if (registerBtn) registerBtn.style.display = 'none';
      if (userMenu) userMenu.style.display = 'block';

      // Support multiple response shapes
      const username = (result.user && result.user.username) ? result.user.username : (result.username || '');
      if (userNameEl && username) userNameEl.textContent = username;
    } else {
      if (loginBtn) loginBtn.style.display = 'inline-flex';
      if (registerBtn) registerBtn.style.display = 'inline-flex';
      if (userMenu) userMenu.style.display = 'none';
    }
  } catch (err) {
    console.error('initAuthUI error:', err);
    // Fallback to showing login/register
    const loginBtn = document.getElementById('loginBtn');
    const registerBtn = document.getElementById('registerBtn');
    const userMenu = document.getElementById('userMenu');
    if (loginBtn) loginBtn.style.display = 'inline-flex';
    if (registerBtn) registerBtn.style.display = 'inline-flex';
    if (userMenu) userMenu.style.display = 'none';
  }
}

async function logout() {
  try {
    // Use API helper so baseURL is respected
    const result = await API.post('/auth/logout.php', {});
    // If success or not, redirect to root index
    window.location.href = '/WRSOMS/index.html';
  } catch (err) {
    console.error('Logout error (auth.js):', err);
    window.location.href = '/WRSOMS/index.html';
  }
}

// Expose globally for inline onclick handlers
window.initAuthUI = initAuthUI;
window.logout = logout;
