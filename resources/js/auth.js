import './bootstrap';

const signUpButton = document.getElementById('signUp');
const signInButton = document.getElementById('signIn');
const container = document.getElementById('container');

// Temporarily disable transition
container.style.transition = 'none';

// Load the state from localStorage
if (localStorage.getItem('right-panel-active') === 'true') {
    container.classList.add("right-panel-active");
} else {
    container.classList.remove("right-panel-active");
}

container.offsetHeight;

// Re-enable transition
container.style.transition = '';

signUpButton.addEventListener('click', () => {
    container.classList.add("right-panel-active");
    localStorage.setItem('right-panel-active', 'true');
});

signInButton.addEventListener('click', () => {
    container.classList.remove("right-panel-active");
    localStorage.setItem('right-panel-active', 'false');
});