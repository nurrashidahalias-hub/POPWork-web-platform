// ============================================
// REGISTRATION FORM HANDLER WITH AJAX
// File: register.js
// Include this in your register.html
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('registerForm');
    const submitBtn = form.querySelector('.submit-btn');
    
    // Create message box element
    const messageBox = document.createElement('div');
    messageBox.className = 'message-box';
    messageBox.id = 'messageBox';
    
    // Insert message box at the top of form
    const formTitle = document.querySelector('.form-title');
    formTitle.parentNode.insertBefore(messageBox, formTitle.nextSibling);
    
    // Handle form submission with AJAX
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        // Hide any existing messages
        hideMessage();
        
        // Disable submit button and show loading state
        submitBtn.disabled = true;
        submitBtn.classList.add('loading');
        const originalText = submitBtn.textContent;
        submitBtn.textContent = '';
        
        try {
            // Create FormData from the form
            const formData = new FormData(form);
            
            // Send AJAX request
            const response = await fetch('register_handler.php', {
                method: 'POST',
                body: formData
            });
            
            // Parse JSON response
            const data = await response.json();
            
            if (data.success) {
                // Show success message
                showMessage(data.message, 'success');
                
                // Clear form after successful registration
                form.reset();
                
                // Scroll to message
                messageBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
                
                // Optionally redirect to login after a delay
                setTimeout(function() {
                    if (confirm('Registration successful! Would you like to go to the login page?')) {
                        window.location.href = 'login.html';
                    }
                }, 3000);
                
            } else {
                // Show error message
                showMessage(data.message, 'error');
                messageBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            
        } catch (error) {
            console.error('Error:', error);
            showMessage('An unexpected error occurred. Please try again.', 'error');
            messageBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
            
        } finally {
            // Re-enable submit button
            submitBtn.disabled = false;
            submitBtn.classList.remove('loading');
            submitBtn.textContent = originalText;
        }
    });
    
    // Password strength indicator (existing code)
    const passwordInput = document.getElementById('password');
    const strengthBar = document.getElementById('strengthBar');
    const strengthText = document.getElementById('strengthText');

    if (passwordInput && strengthBar && strengthText) {
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;

            if (password.length >= 8) strength++;
            if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^a-zA-Z0-9]/)) strength++;

            strengthBar.className = 'password-strength-bar';
            
            if (strength === 0 || strength === 1) {
                strengthBar.classList.add('weak');
                strengthText.textContent = 'Weak password';
                strengthText.style.color = '#e74c3c';
            } else if (strength === 2 || strength === 3) {
                strengthBar.classList.add('medium');
                strengthText.textContent = 'Medium strength';
                strengthText.style.color = '#f39c12';
            } else {
                strengthBar.classList.add('strong');
                strengthText.textContent = 'Strong password ✓';
                strengthText.style.color = '#27ae60';
            }
        });
    }

    // Password confirmation validation
    const confirmPassword = document.getElementById('confirm_password');
    if (confirmPassword && passwordInput) {
        confirmPassword.addEventListener('input', function() {
            if (this.value !== passwordInput.value) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
    }

    // Role-based section display
    const roleSelect = document.getElementById('role');
    const workerSection = document.getElementById('workerSection');
    const employerSection = document.getElementById('employerSection');

    if (roleSelect && workerSection && employerSection) {
        roleSelect.addEventListener('change', function() {
            const companyNameInput = document.getElementById('company_name');
            
            if (this.value === 'worker') {
                workerSection.classList.remove('hidden');
                employerSection.classList.add('hidden');
                if (companyNameInput) companyNameInput.removeAttribute('required');
            } else if (this.value === 'employer') {
                workerSection.classList.add('hidden');
                employerSection.classList.remove('hidden');
                if (companyNameInput) companyNameInput.setAttribute('required', 'required');
            } else {
                workerSection.classList.add('hidden');
                employerSection.classList.add('hidden');
                if (companyNameInput) companyNameInput.removeAttribute('required');
            }
        });
    }

    // Toggle university field based on student checkbox
    const isStudentCheckbox = document.getElementById('is_student');
    const universityField = document.getElementById('universityField');
    const universitySelect = document.getElementById('university');

    if (isStudentCheckbox && universityField && universitySelect) {
        isStudentCheckbox.addEventListener('change', function() {
            if (this.checked) {
                universityField.classList.remove('hidden');
                universitySelect.setAttribute('required', 'required');
            } else {
                universityField.classList.add('hidden');
                universitySelect.removeAttribute('required');
                universitySelect.value = '';
            }
        });
    }
});

// ============================================
// HELPER FUNCTIONS
// ============================================

function showMessage(message, type) {
    const messageBox = document.getElementById('messageBox');
    if (!messageBox) return;
    
    messageBox.className = 'message-box show ' + type;
    messageBox.innerHTML = `
        <div class="message-icon">${type === 'success' ? '✓' : type === 'error' ? '✗' : 'ℹ'}</div>
        <div class="message-text">${message}</div>
    `;
}

function hideMessage() {
    const messageBox = document.getElementById('messageBox');
    if (messageBox) {
        messageBox.classList.remove('show');
    }
}

// Password visibility toggle
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const toggle = input.parentElement.querySelector('.password-toggle');
    
    if (input.type === 'password') {
        input.type = 'text';
        toggle.textContent = '🙈';
    } else {
        input.type = 'password';
        toggle.textContent = '👁️';
    }
}

// File name display function
function displayFileName(inputId, displayId) {
    const input = document.getElementById(inputId);
    const display = document.getElementById(displayId);
    
    if (input.files && input.files[0]) {
        display.textContent = '✓ ' + input.files[0].name;
    } else {
        display.textContent = '';
    }
}