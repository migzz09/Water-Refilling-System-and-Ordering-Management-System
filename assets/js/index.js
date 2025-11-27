/**
 * WaterWorld Water Station - Homepage Scripts
 */

// Check authentication status on page load
async function checkAuthStatus() {
  try {
    const result = await API.checkAuth();
    
    console.log('Auth check result:', result); // Debug log
    
    if (result.authenticated) {
      // User is logged in - show profile menu
      document.getElementById('loginBtn').style.display = 'none';
      document.getElementById('registerBtn').style.display = 'none';
      document.getElementById('userMenu').style.display = 'block';
      
      // Set user name if available
      if (result.user && result.user.username) {
        document.getElementById('userName').textContent = result.user.username;
      }
    } else {
      // User is not logged in - show login/register buttons
      console.log('User not authenticated'); // Debug log
      document.getElementById('loginBtn').style.display = 'inline-flex';
      document.getElementById('registerBtn').style.display = 'inline-flex';
      document.getElementById('userMenu').style.display = 'none';
    }
  } catch (error) {
    console.error('Error checking auth status:', error);
    // On error, show login/register buttons
    document.getElementById('loginBtn').style.display = 'inline-flex';
    document.getElementById('registerBtn').style.display = 'inline-flex';
    document.getElementById('userMenu').style.display = 'none';
  }
}

// Toggle user dropdown menu
function toggleUserDropdown() {
  const dropdown = document.getElementById('userDropdown');
  dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
}

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
  const userMenu = document.getElementById('userMenu');
  const dropdown = document.getElementById('userDropdown');
  
  if (userMenu && dropdown && !userMenu.contains(event.target)) {
    dropdown.style.display = 'none';
  }
});

// Logout is provided by shared auth.js (window.logout)

// Reveal sections on scroll
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

// Show login/register form
function showForm(formId) {
  console.log("Showing form: " + formId);
  document.getElementById(formId).classList.add('active');
}

// Hide login/register form
function hideForm(formId) {
  console.log("Hiding form: " + formId);
  document.getElementById(formId).classList.remove('active');
}

// Toggle dropdown menu
function toggleDropdown(element) {
  const dropdown = element.querySelector('.dropdown');
  dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
}

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

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
  // Use centralized auth UI initializer
  if (window.initAuthUI) {
    initAuthUI();
  } else {
    checkAuthStatus();
  }
});

// Initialize scroll reveal
window.addEventListener("scroll", revealOnScroll);
revealOnScroll();
