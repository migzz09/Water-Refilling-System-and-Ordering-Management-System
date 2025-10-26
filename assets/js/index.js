/**
 * WaterWorld Water Station - Homepage Scripts
 */

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

// Initialize scroll reveal
window.addEventListener("scroll", revealOnScroll);
revealOnScroll();
