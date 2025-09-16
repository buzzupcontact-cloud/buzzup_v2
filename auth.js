// Authentication JavaScript
class AuthManager {
    constructor() {
        this.init();
    }

    init() {
        this.bindEvents();
        this.checkAuthStatus();
    }

    bindEvents() {
        // Login form
        const loginForm = document.getElementById('loginFormElement');
        if (loginForm) {
            loginForm.addEventListener('submit', (e) => this.handleLogin(e));
        }

        // Register form
        const registerForm = document.getElementById('registerFormElement');
        if (registerForm) {
            registerForm.addEventListener('submit', (e) => this.handleRegister(e));
        }

        // Password strength checker
        const passwordField = document.getElementById('registerPassword');
        if (passwordField) {
            passwordField.addEventListener('input', (e) => this.checkPasswordStrength(e));
        }

        // Confirm password validation
        const confirmPasswordField = document.getElementById('confirmPassword');
        if (confirmPasswordField) {
            confirmPasswordField.addEventListener('input', (e) => this.validatePasswordConfirmation(e));
        }

        // Email validation
        const emailFields = document.querySelectorAll('input[type="email"]');
        emailFields.forEach(field => {
            field.addEventListener('blur', (e) => this.validateEmail(e));
        });
    }

    async handleLogin(event) {
        event.preventDefault();
        
        const formData = new FormData(event.target);
        const loginData = {
            email: formData.get('email'),
            password: formData.get('password')
        };

        // Show loading state
        this.showLoading(event.target.querySelector('button[type="submit"]'));

        try {
            const response = await this.makeRequest('php/login.php', 'POST', loginData);
            
            if (response.success) {
                this.showSuccess('Login successful! Redirecting...');
                
                // Store auth token
                localStorage.setItem('auth_token', response.token);
                localStorage.setItem('user_data', JSON.stringify(response.user));
                
                // Redirect to dashboard or home
                setTimeout(() => {
                    window.location.href = response.redirect || 'dashboard.html';
                }, 1500);
            } else {
                this.showError(response.message || 'Login failed. Please try again.');
            }
        } catch (error) {
            this.showError('Connection error. Please try again.');
            console.error('Login error:', error);
        } finally {
            this.hideLoading(event.target.querySelector('button[type="submit"]'));
        }
    }

    async handleRegister(event) {
        event.preventDefault();
        
        const formData = new FormData(event.target);
        const registerData = {
            firstName: formData.get('firstName'),
            lastName: formData.get('lastName'),
            email: formData.get('email'),
            password: formData.get('password'),
            confirmPassword: formData.get('confirmPassword')
        };

        // Validate passwords match
        if (registerData.password !== registerData.confirmPassword) {
            this.showError('Passwords do not match!');
            return;
        }

        // Validate password strength
        if (!this.isPasswordStrong(registerData.password)) {
            this.showError('Password must be at least 8 characters long and contain uppercase, lowercase, numbers, and special characters.');
            return;
        }

        // Show loading state
        this.showLoading(event.target.querySelector('button[type="submit"]'));

        try {
            const response = await this.makeRequest('php/register.php', 'POST', registerData);
            
            if (response.success) {
                this.showSuccess('Registration successful! Please check your email to verify your account.');
                
                // Switch to login form after 2 seconds
                setTimeout(() => {
                    switchToLogin();
                }, 2000);
            } else {
                this.showError(response.message || 'Registration failed. Please try again.');
            }
        } catch (error) {
            this.showError('Connection error. Please try again.');
            console.error('Registration error:', error);
        } finally {
            this.hideLoading(event.target.querySelector('button[type="submit"]'));
        }
    }

    async makeRequest(url, method = 'GET', data = null) {
        const options = {
            method: method,
            headers: {
                'Content-Type': 'application/json',
            }
        };

        if (data) {
            options.body = JSON.stringify(data);
        }

        // Add auth token if available
        const token = localStorage.getItem('auth_token');
        if (token) {
            options.headers['Authorization'] = `Bearer ${token}`;
        }

        const response = await fetch(url, options);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        return await response.json();
    }

    checkAuthStatus() {
        const token = localStorage.getItem('auth_token');
        const currentPage = window.location.pathname;
        
        // If on login page and already authenticated, redirect to dashboard
        if (token && currentPage.includes('login.html')) {
            this.validateToken().then(isValid => {
                if (isValid) {
                    window.location.href = 'dashboard.html';
                }
            });
        }
    }

    async validateToken() {
        const token = localStorage.getItem('auth_token');
        if (!token) return false;

        try {
            const response = await this.makeRequest('php/validate_token.php', 'POST');
            return response.success;
        } catch (error) {
            console.error('Token validation error:', error);
            this.logout();
            return false;
        }
    }

    logout() {
        localStorage.removeItem('auth_token');
        localStorage.removeItem('user_data');
        window.location.href = 'login.html';
    }

    validateEmail(event) {
        const email = event.target.value;
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        const isValid = emailRegex.test(email);
        
        this.setFieldValidation(event.target, isValid, isValid ? '' : 'Please enter a valid email address');
        
        return isValid;
    }

    checkPasswordStrength(event) {
        const password = event.target.value;
        const strength = this.calculatePasswordStrength(password);
        
        // Remove existing strength indicator
        let strengthIndicator = event.target.parentNode.querySelector('.password-strength');
        if (strengthIndicator) {
            strengthIndicator.remove();
        }

        if (password.length > 0) {
            // Create strength indicator
            strengthIndicator = document.createElement('div');
            strengthIndicator.className = 'password-strength';
            strengthIndicator.innerHTML = `
                <div class="strength-bar">
                    <div class="strength-fill strength-${strength.level}" style="width: ${strength.percentage}%"></div>
                </div>
                <small class="strength-text">${strength.text}</small>
            `;
            
            event.target.parentNode.appendChild(strengthIndicator);
        }

        return strength.level >= 3; // Require at least "Good" strength
    }

    calculatePasswordStrength(password) {
        let score = 0;
        let feedback = [];

        // Length check
        if (password.length >= 8) score += 1;
        else feedback.push('at least 8 characters');

        // Uppercase check
        if (/[A-Z]/.test(password)) score += 1;
        else feedback.push('uppercase letters');

        // Lowercase check
        if (/[a-z]/.test(password)) score += 1;
        else feedback.push('lowercase letters');

        // Number check
        if (/\d/.test(password)) score += 1;
        else feedback.push('numbers');

        // Special character check
        if (/[^A-Za-z0-9]/.test(password)) score += 1;
        else feedback.push('special characters');

        const levels = [
            { level: 0, text: 'Very Weak', percentage: 20 },
            { level: 1, text: 'Weak', percentage: 40 },
            { level: 2, text: 'Fair', percentage: 60 },
            { level: 3, text: 'Good', percentage: 80 },
            { level: 4, text: 'Strong', percentage: 100 }
        ];

        const result = levels[Math.min(score, 4)];
        
        if (feedback.length > 0) {
            result.text += ` (needs: ${feedback.join(', ')})`;
        }

        return result;
    }

    isPasswordStrong(password) {
        const strength = this.calculatePasswordStrength(password);
        return strength.level >= 3;
    }

    validatePasswordConfirmation(event) {
        const confirmPassword = event.target.value;
        const originalPassword = document.getElementById('registerPassword').value;
        const isValid = confirmPassword === originalPassword && confirmPassword.length > 0;
        
        this.setFieldValidation(event.target, isValid, isValid ? '' : 'Passwords do not match');
        
        return isValid;
    }

    setFieldValidation(field, isValid, message) {
        // Remove existing validation message
        let validationMessage = field.parentNode.querySelector('.validation-message');
        if (validationMessage) {
            validationMessage.remove();
        }

        // Update field styling
        field.classList.remove('is-valid', 'is-invalid');
        field.classList.add(isValid ? 'is-valid' : 'is-invalid');

        // Add validation message if invalid
        if (!isValid && message) {
            validationMessage = document.createElement('div');
            validationMessage.className = 'validation-message text-danger mt-1';
            validationMessage.innerHTML = `<small>${message}</small>`;
            field.parentNode.appendChild(validationMessage);
        }
    }

    showLoading(button) {
        button.disabled = true;
        button.innerHTML = `<i class="fas fa-spinner fa-spin me-2"></i>Processing...`;
    }

    hideLoading(button) {
        button.disabled = false;
        if (button.id === 'loginFormElement') {
            button.innerHTML = `<i class="fas fa-sign-in-alt me-2"></i>Sign In`;
        } else {
            button.innerHTML = `<i class="fas fa-user-plus me-2"></i>Create Account`;
        }
    }

    showSuccess(message) {
        this.showToast(message, 'success');
    }

    showError(message) {
        this.showToast(message, 'error');
    }

    showToast(message, type) {
        // Remove existing toast
        const existingToast = document.querySelector('.auth-toast');
        if (existingToast) {
            existingToast.remove();
        }

        // Create toast
        const toast = document.createElement('div');
        toast.className = `auth-toast toast-${type}`;
        toast.innerHTML = `
            <div class="toast-content">
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                <span>${message}</span>
            </div>
        `;

        // Style the toast
        Object.assign(toast.style, {
            position: 'fixed',
            top: '20px',
            right: '20px',
            padding: '15px 20px',
            borderRadius: '8px',
            color: 'white',
            backgroundColor: type === 'success' ? '#10B981' : '#EF4444',
            boxShadow: '0 4px 15px rgba(0, 0, 0, 0.2)',
            zIndex: '10000',
            transform: 'translateX(100%)',
            transition: 'transform 0.3s ease-in-out',
            maxWidth: '300px',
            wordWrap: 'break-word'
        });

        document.body.appendChild(toast);

        // Animate in
        setTimeout(() => {
            toast.style.transform = 'translateX(0)';
        }, 100);

        // Remove after 5 seconds
        setTimeout(() => {
            toast.style.transform = 'translateX(100%)';
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.remove();
                }
            }, 300);
        }, 5000);
    }
}

// Social login handlers (placeholder)
function loginWithGoogle() {
    console.log('Google login not implemented yet');
    // Implement Google OAuth here
}

function loginWithFacebook() {
    console.log('Facebook login not implemented yet');
    // Implement Facebook OAuth here
}

// Initialize auth manager when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new AuthManager();
});

// Add CSS for validation and password strength
const authStyles = `
<style>
.password-strength {
    margin-top: 8px;
}

.strength-bar {
    height: 4px;
    background-color: rgba(255, 255, 255, 0.1);
    border-radius: 2px;
    overflow: hidden;
    margin-bottom: 4px;
}

.strength-fill {
    height: 100%;
    border-radius: 2px;
    transition: width 0.3s ease;
}

.strength-0 { background-color: #EF4444; }
.strength-1 { background-color: #F59E0B; }
.strength-2 { background-color: #EAB308; }
.strength-3 { background-color: #22C55E; }
.strength-4 { background-color: #10B981; }

.strength-text {
    color: var(--text-muted);
    font-size: 0.85rem;
}

.form-control.is-valid {
    border-color: #22C55E !important;
}

.form-control.is-invalid {
    border-color: #EF4444 !important;
}

.validation-message {
    font-size: 0.85rem;
}

.toast-content {
    display: flex;
    align-items: center;
    gap: 10px;
}

.toast-content i {
    font-size: 1.2rem;
}
</style>
`;

document.head.insertAdjacentHTML('beforeend', authStyles);