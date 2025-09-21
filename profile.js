// Profile Page JavaScript
class ProfileManager {
    constructor() {
        this.currentUser = null;
        this.tickets = [];
        this.init();
    }

    init() {
        this.checkAuthentication();
        this.bindEvents();
        this.loadUserData();
        this.loadTickets();
    }

    checkAuthentication() {
        const token = localStorage.getItem('auth_token');
        if (!token) {
            window.location.href = 'login.html';
            return;
        }

        // Validate token
        this.validateToken(token);
    }

    async validateToken(token) {
        try {
            const response = await fetch('php/validate_token.php', {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json'
                }
            });

            const result = await response.json();
            
            if (!result.success) {
                localStorage.removeItem('auth_token');
                localStorage.removeItem('user_data');
                window.location.href = 'login.html';
                return;
            }

            this.currentUser = result.data.user;
            this.updateUserDisplay();
        } catch (error) {
            console.error('Token validation error:', error);
            this.logout();
        }
    }

    bindEvents() {
        // Support form submission
        const supportForm = document.getElementById('supportForm');
        if (supportForm) {
            supportForm.addEventListener('submit', (e) => this.handleSupportSubmission(e));
        }

        // Edit profile form
        const editProfileForm = document.getElementById('editProfileForm');
        if (editProfileForm) {
            editProfileForm.addEventListener('submit', (e) => this.handleProfileEdit(e));
        }

        // Change password form
        const changePasswordForm = document.getElementById('changePasswordForm');
        if (changePasswordForm) {
            changePasswordForm.addEventListener('submit', (e) => this.handlePasswordChange(e));
        }

        // Tab switching
        const tabButtons = document.querySelectorAll('[data-bs-toggle="pill"]');
        tabButtons.forEach(button => {
            button.addEventListener('shown.bs.tab', (e) => {
                const target = e.target.getAttribute('data-bs-target');
                if (target === '#tickets') {
                    this.loadTickets();
                }
            });
        });
    }

    updateUserDisplay() {
        if (!this.currentUser) return;

        // Update user info in the profile card
        document.getElementById('userName').textContent = this.currentUser.name;
        document.getElementById('userEmail').textContent = this.currentUser.email;
        
        // Update member since
        const memberSince = new Date(this.currentUser.created_at || Date.now()).getFullYear();
        document.getElementById('memberSince').textContent = memberSince;

        // Update form fields for editing
        document.getElementById('editFirstName').value = this.currentUser.first_name || '';
        document.getElementById('editLastName').value = this.currentUser.last_name || '';
        document.getElementById('editEmail').value = this.currentUser.email || '';
        document.getElementById('editPhone').value = this.currentUser.phone || '';
        document.getElementById('editCompany').value = this.currentUser.company || '';
    }

    async loadUserData() {
        try {
            const token = localStorage.getItem('auth_token');
            const response = await fetch('php/get_user_profile.php', {
                method: 'GET',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json'
                }
            });

            const result = await response.json();
            
            if (result.success) {
                this.currentUser = result.data;
                this.updateUserDisplay();
            }
        } catch (error) {
            console.error('Error loading user data:', error);
        }
    }

    async loadTickets() {
        try {
            const token = localStorage.getItem('auth_token');
            const response = await fetch('php/get_user_tickets.php', {
                method: 'GET',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json'
                }
            });

            const result = await response.json();
            
            if (result.success) {
                this.tickets = result.data;
                this.displayTickets();
                this.updateTicketsCount();
            } else {
                this.displayNoTickets();
            }
        } catch (error) {
            console.error('Error loading tickets:', error);
            this.displayTicketsError();
        }
    }

    displayTickets() {
        const ticketsList = document.getElementById('ticketsList');
        
        if (this.tickets.length === 0) {
            this.displayNoTickets();
            return;
        }

        const ticketsHtml = this.tickets.map(ticket => `
            <div class="ticket-item" data-ticket-id="${ticket.id}">
                <div class="ticket-header">
                    <div class="ticket-info">
                        <h6 class="ticket-title">${ticket.subject}</h6>
                        <p class="ticket-description">${ticket.description.substring(0, 100)}...</p>
                    </div>
                    <div class="ticket-meta">
                        <span class="badge bg-${this.getPriorityColor(ticket.priority)}">${ticket.priority}</span>
                        <span class="badge bg-${this.getStatusColor(ticket.status)}">${ticket.status}</span>
                    </div>
                </div>
                <div class="ticket-footer">
                    <small class="text-muted">
                        <i class="fas fa-calendar me-1"></i>
                        ${new Date(ticket.created_at).toLocaleDateString()}
                    </small>
                    <button class="btn btn-sm btn-outline-primary" onclick="profileManager.viewTicket(${ticket.id})">
                        <i class="fas fa-eye me-1"></i>View Details
                    </button>
                </div>
            </div>
        `).join('');

        ticketsList.innerHTML = ticketsHtml;
    }

    displayNoTickets() {
        const ticketsList = document.getElementById('ticketsList');
        ticketsList.innerHTML = `
            <div class="text-center py-5">
                <i class="fas fa-ticket-alt fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No Support Tickets</h5>
                <p class="text-muted">You haven't submitted any support tickets yet.</p>
                <button class="btn btn-primary" onclick="showSupportTab()">
                    <i class="fas fa-plus me-2"></i>Create New Ticket
                </button>
            </div>
        `;
    }

    displayTicketsError() {
        const ticketsList = document.getElementById('ticketsList');
        ticketsList.innerHTML = `
            <div class="text-center py-5">
                <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                <h5 class="text-warning">Error Loading Tickets</h5>
                <p class="text-muted">Unable to load your support tickets. Please try again.</p>
                <button class="btn btn-outline-primary" onclick="profileManager.loadTickets()">
                    <i class="fas fa-refresh me-2"></i>Retry
                </button>
            </div>
        `;
    }

    updateTicketsCount() {
        document.getElementById('ticketsCount').textContent = this.tickets.length;
    }

    getPriorityColor(priority) {
        const colors = {
            'urgent': 'danger',
            'high': 'warning',
            'medium': 'info',
            'low': 'secondary'
        };
        return colors[priority] || 'secondary';
    }

    getStatusColor(status) {
        const colors = {
            'open': 'primary',
            'in_progress': 'warning',
            'resolved': 'success',
            'closed': 'secondary'
        };
        return colors[status] || 'secondary';
    }

    async handleSupportSubmission(event) {
        event.preventDefault();

        const formData = {
            subject: document.getElementById('supportSubject').value,
            priority: document.getElementById('supportPriority').value,
            title: document.getElementById('supportTitle').value,
            message: document.getElementById('supportMessage').value
        };

        // Show loading state
        const submitBtn = event.target.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Sending...';
        submitBtn.disabled = true;

        try {
            const token = localStorage.getItem('auth_token');
            const response = await fetch('php/create_ticket.php', {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(formData)
            });

            const result = await response.json();

            if (result.success) {
                this.showSuccess('Support ticket created successfully!');
                document.getElementById('supportForm').reset();
                this.loadTickets(); // Refresh tickets list
                
                // Switch to tickets tab
                const ticketsTab = document.getElementById('tickets-tab');
                ticketsTab.click();
            } else {
                this.showError(result.message || 'Failed to create support ticket');
            }
        } catch (error) {
            console.error('Support submission error:', error);
            this.showError('Connection error. Please try again.');
        } finally {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }
    }

    async handleProfileEdit(event) {
        event.preventDefault();

        const formData = {
            firstName: document.getElementById('editFirstName').value,
            lastName: document.getElementById('editLastName').value,
            email: document.getElementById('editEmail').value,
            phone: document.getElementById('editPhone').value,
            company: document.getElementById('editCompany').value
        };

        try {
            const token = localStorage.getItem('auth_token');
            const response = await fetch('php/update_profile.php', {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(formData)
            });

            const result = await response.json();

            if (result.success) {
                this.showSuccess('Profile updated successfully!');
                this.currentUser = { ...this.currentUser, ...formData };
                this.updateUserDisplay();
                
                // Close modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('editProfileModal'));
                modal.hide();
            } else {
                this.showError(result.message || 'Failed to update profile');
            }
        } catch (error) {
            console.error('Profile update error:', error);
            this.showError('Connection error. Please try again.');
        }
    }

    async handlePasswordChange(event) {
        event.preventDefault();

        const currentPassword = document.getElementById('currentPassword').value;
        const newPassword = document.getElementById('newPassword').value;
        const confirmPassword = document.getElementById('confirmNewPassword').value;

        if (newPassword !== confirmPassword) {
            this.showError('New passwords do not match!');
            return;
        }

        const formData = {
            currentPassword,
            newPassword
        };

        try {
            const token = localStorage.getItem('auth_token');
            const response = await fetch('php/change_password.php', {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(formData)
            });

            const result = await response.json();

            if (result.success) {
                this.showSuccess('Password changed successfully!');
                document.getElementById('changePasswordForm').reset();
                
                // Close modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('changePasswordModal'));
                modal.hide();
            } else {
                this.showError(result.message || 'Failed to change password');
            }
        } catch (error) {
            console.error('Password change error:', error);
            this.showError('Connection error. Please try again.');
        }
    }

    viewTicket(ticketId) {
        // This would open a modal or navigate to ticket details
        // For now, just show an alert
        this.showInfo(`Viewing ticket #${ticketId} - Feature coming soon!`);
    }

    showSuccess(message) {
        this.showToast(message, 'success');
    }

    showError(message) {
        this.showToast(message, 'error');
    }

    showInfo(message) {
        this.showToast(message, 'info');
    }

    showToast(message, type) {
        // Remove existing toast
        const existingToast = document.querySelector('.profile-toast');
        if (existingToast) {
            existingToast.remove();
        }

        // Create toast
        const toast = document.createElement('div');
        toast.className = `profile-toast toast-${type}`;
        toast.innerHTML = `
            <div class="toast-content">
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
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
            backgroundColor: type === 'success' ? '#10B981' : type === 'error' ? '#EF4444' : '#3B82F6',
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

    logout() {
        localStorage.removeItem('auth_token');
        localStorage.removeItem('user_data');
        window.location.href = 'login.html';
    }
}

// Global functions
function editProfile() {
    const modal = new bootstrap.Modal(document.getElementById('editProfileModal'));
    modal.show();
}

function changePassword() {
    const modal = new bootstrap.Modal(document.getElementById('changePasswordModal'));
    modal.show();
}

function saveProfile() {
    const form = document.getElementById('editProfileForm');
    form.dispatchEvent(new Event('submit'));
}

function updatePassword() {
    const form = document.getElementById('changePasswordForm');
    form.dispatchEvent(new Event('submit'));
}

function showSupportTab() {
    const supportTab = document.getElementById('support-tab');
    supportTab.click();
}

function viewActivity() {
    profileManager.showInfo('Activity log feature coming soon!');
}

function logout() {
    profileManager.logout();
}

// Initialize profile manager
let profileManager;
document.addEventListener('DOMContentLoaded', () => {
    profileManager = new ProfileManager();
});