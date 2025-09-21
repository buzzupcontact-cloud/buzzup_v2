// Admin Dashboard JavaScript
class AdminManager {
    constructor() {
        this.tickets = [];
        this.users = [];
        this.stats = {};
        this.currentTicket = null;
        this.init();
    }

    init() {
        this.checkAdminAuthentication();
        this.bindEvents();
        this.loadDashboardData();
        this.initializeCharts();
    }

    checkAdminAuthentication() {
        const token = localStorage.getItem('auth_token');
        if (!token) {
            window.location.href = 'login.html';
            return;
        }

        // Validate admin token
        this.validateAdminToken(token);
    }

    async validateAdminToken(token) {
        try {
            const response = await fetch('php/validate_admin_token.php', {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json'
                }
            });

            const result = await response.json();
            
            if (!result.success || !result.data.user.roles.includes('admin')) {
                localStorage.removeItem('auth_token');
                localStorage.removeItem('user_data');
                window.location.href = 'login.html';
                return;
            }

            this.currentAdmin = result.data.user;
        } catch (error) {
            console.error('Admin token validation error:', error);
            this.logout();
        }
    }

    bindEvents() {
        // Reply form submission
        const replyForm = document.getElementById('replyForm');
        if (replyForm) {
            replyForm.addEventListener('submit', (e) => this.handleReplySubmission(e));
        }

        // Settings form
        const settingsForm = document.getElementById('settingsForm');
        if (settingsForm) {
            settingsForm.addEventListener('submit', (e) => this.handleSettingsUpdate(e));
        }

        // Filter events
        document.getElementById('statusFilter').addEventListener('change', () => this.filterTickets());
        document.getElementById('priorityFilter').addEventListener('change', () => this.filterTickets());
    }

    async loadDashboardData() {
        await Promise.all([
            this.loadTickets(),
            this.loadUsers(),
            this.loadStats()
        ]);
    }

    async loadTickets() {
        try {
            const token = localStorage.getItem('auth_token');
            const response = await fetch('php/admin_get_tickets.php', {
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
                this.updateTicketStats();
            } else {
                this.displayTicketsError();
            }
        } catch (error) {
            console.error('Error loading tickets:', error);
            this.displayTicketsError();
        }
    }

    async loadUsers() {
        try {
            const token = localStorage.getItem('auth_token');
            const response = await fetch('php/admin_get_users.php', {
                method: 'GET',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json'
                }
            });

            const result = await response.json();
            
            if (result.success) {
                this.users = result.data;
                this.displayUsers();
                this.updateUserStats();
            }
        } catch (error) {
            console.error('Error loading users:', error);
        }
    }

    async loadStats() {
        try {
            const token = localStorage.getItem('auth_token');
            const response = await fetch('php/admin_get_stats.php', {
                method: 'GET',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json'
                }
            });

            const result = await response.json();
            
            if (result.success) {
                this.stats = result.data;
                this.updateStatsDisplay();
            }
        } catch (error) {
            console.error('Error loading stats:', error);
        }
    }

    displayTickets() {
        const tbody = document.getElementById('ticketsTableBody');
        
        if (this.tickets.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center py-4">
                        <i class="fas fa-ticket-alt fa-2x text-muted mb-3"></i>
                        <p class="text-muted">No tickets found</p>
                    </td>
                </tr>
            `;
            return;
        }

        const ticketsHtml = this.tickets.map(ticket => `
            <tr>
                <td>#${ticket.id}</td>
                <td>
                    <div class="user-info">
                        <strong>${ticket.user_name}</strong>
                        <br>
                        <small class="text-muted">${ticket.user_email}</small>
                    </div>
                </td>
                <td>
                    <div class="ticket-subject">
                        <strong>${ticket.subject}</strong>
                        <br>
                        <small class="text-muted">${ticket.description.substring(0, 50)}...</small>
                    </div>
                </td>
                <td>
                    <span class="badge bg-${this.getPriorityColor(ticket.priority)}">${ticket.priority}</span>
                </td>
                <td>
                    <span class="badge bg-${this.getStatusColor(ticket.status)}">${ticket.status}</span>
                </td>
                <td>
                    <small>${new Date(ticket.created_at).toLocaleDateString()}</small>
                </td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" onclick="adminManager.viewTicket(${ticket.id})">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn btn-outline-success" onclick="adminManager.updateTicketStatus(${ticket.id}, 'resolved')">
                            <i class="fas fa-check"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');

        tbody.innerHTML = ticketsHtml;
    }

    displayUsers() {
        const tbody = document.getElementById('usersTableBody');
        
        if (this.users.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center py-4">
                        <i class="fas fa-users fa-2x text-muted mb-3"></i>
                        <p class="text-muted">No users found</p>
                    </td>
                </tr>
            `;
            return;
        }

        const usersHtml = this.users.map(user => `
            <tr>
                <td>#${user.id}</td>
                <td>${user.first_name} ${user.last_name}</td>
                <td>${user.email}</td>
                <td>
                    <span class="badge bg-${user.status === 'active' ? 'success' : 'secondary'}">${user.status}</span>
                </td>
                <td>
                    <small>${new Date(user.created_at).toLocaleDateString()}</small>
                </td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" onclick="adminManager.viewUser(${user.id})">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn btn-outline-warning" onclick="adminManager.toggleUserStatus(${user.id})">
                            <i class="fas fa-toggle-${user.status === 'active' ? 'on' : 'off'}"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');

        tbody.innerHTML = usersHtml;
    }

    updateTicketStats() {
        const total = this.tickets.length;
        const pending = this.tickets.filter(t => t.status === 'open' || t.status === 'in_progress').length;
        const resolved = this.tickets.filter(t => t.status === 'resolved' || t.status === 'closed').length;

        document.getElementById('totalTickets').textContent = total;
        document.getElementById('pendingTickets').textContent = pending;
        document.getElementById('resolvedTickets').textContent = resolved;
    }

    updateUserStats() {
        document.getElementById('totalUsers').textContent = this.users.length;
    }

    updateStatsDisplay() {
        if (this.stats.totalTickets !== undefined) {
            document.getElementById('totalTickets').textContent = this.stats.totalTickets;
        }
        if (this.stats.pendingTickets !== undefined) {
            document.getElementById('pendingTickets').textContent = this.stats.pendingTickets;
        }
        if (this.stats.resolvedTickets !== undefined) {
            document.getElementById('resolvedTickets').textContent = this.stats.resolvedTickets;
        }
        if (this.stats.totalUsers !== undefined) {
            document.getElementById('totalUsers').textContent = this.stats.totalUsers;
        }
    }

    filterTickets() {
        const statusFilter = document.getElementById('statusFilter').value;
        const priorityFilter = document.getElementById('priorityFilter').value;

        let filteredTickets = this.tickets;

        if (statusFilter) {
            filteredTickets = filteredTickets.filter(ticket => ticket.status === statusFilter);
        }

        if (priorityFilter) {
            filteredTickets = filteredTickets.filter(ticket => ticket.priority === priorityFilter);
        }

        // Temporarily store original tickets and display filtered ones
        const originalTickets = this.tickets;
        this.tickets = filteredTickets;
        this.displayTickets();
        this.tickets = originalTickets;
    }

    async viewTicket(ticketId) {
        try {
            const token = localStorage.getItem('auth_token');
            const response = await fetch(`php/admin_get_ticket_details.php?id=${ticketId}`, {
                method: 'GET',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json'
                }
            });

            const result = await response.json();
            
            if (result.success) {
                this.currentTicket = result.data;
                this.displayTicketModal();
            } else {
                this.showError('Failed to load ticket details');
            }
        } catch (error) {
            console.error('Error loading ticket details:', error);
            this.showError('Connection error. Please try again.');
        }
    }

    displayTicketModal() {
        const ticket = this.currentTicket;
        
        // Update ticket details
        const ticketDetails = document.getElementById('ticketDetails');
        ticketDetails.innerHTML = `
            <div class="row">
                <div class="col-md-6">
                    <h6>Ticket Information</h6>
                    <p><strong>ID:</strong> #${ticket.id}</p>
                    <p><strong>Subject:</strong> ${ticket.subject}</p>
                    <p><strong>Priority:</strong> <span class="badge bg-${this.getPriorityColor(ticket.priority)}">${ticket.priority}</span></p>
                    <p><strong>Status:</strong> <span class="badge bg-${this.getStatusColor(ticket.status)}">${ticket.status}</span></p>
                </div>
                <div class="col-md-6">
                    <h6>User Information</h6>
                    <p><strong>Name:</strong> ${ticket.user_name}</p>
                    <p><strong>Email:</strong> ${ticket.user_email}</p>
                    <p><strong>Created:</strong> ${new Date(ticket.created_at).toLocaleString()}</p>
                </div>
            </div>
            <div class="mt-3">
                <h6>Description</h6>
                <p>${ticket.description}</p>
            </div>
        `;

        // Update conversation messages
        this.displayConversationMessages();

        // Set current status in dropdown
        document.getElementById('ticketStatus').value = ticket.status;

        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('ticketModal'));
        modal.show();
    }

    displayConversationMessages() {
        const messagesContainer = document.getElementById('conversationMessages');
        
        if (!this.currentTicket.messages || this.currentTicket.messages.length === 0) {
            messagesContainer.innerHTML = `
                <div class="text-center py-3">
                    <p class="text-muted">No messages yet</p>
                </div>
            `;
            return;
        }

        const messagesHtml = this.currentTicket.messages.map(message => `
            <div class="message ${message.is_admin ? 'admin-message' : 'user-message'}">
                <div class="message-header">
                    <strong>${message.sender_name}</strong>
                    <small class="text-muted">${new Date(message.created_at).toLocaleString()}</small>
                </div>
                <div class="message-content">
                    ${message.message}
                </div>
            </div>
        `).join('');

        messagesContainer.innerHTML = messagesHtml;
    }

    async handleReplySubmission(event) {
        event.preventDefault();

        const message = document.getElementById('replyMessage').value;
        const status = document.getElementById('ticketStatus').value;

        if (!message.trim()) {
            this.showError('Please enter a reply message');
            return;
        }

        const formData = {
            ticketId: this.currentTicket.id,
            message: message,
            status: status
        };

        // Show loading state
        const submitBtn = event.target.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Sending...';
        submitBtn.disabled = true;

        try {
            const token = localStorage.getItem('auth_token');
            const response = await fetch('php/admin_reply_ticket.php', {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(formData)
            });

            const result = await response.json();

            if (result.success) {
                this.showSuccess('Reply sent successfully!');
                document.getElementById('replyMessage').value = '';
                
                // Refresh ticket details
                await this.viewTicket(this.currentTicket.id);
                
                // Refresh tickets list
                await this.loadTickets();
            } else {
                this.showError(result.message || 'Failed to send reply');
            }
        } catch (error) {
            console.error('Reply submission error:', error);
            this.showError('Connection error. Please try again.');
        } finally {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }
    }

    async updateTicketStatus(ticketId, status) {
        try {
            const token = localStorage.getItem('auth_token');
            const response = await fetch('php/admin_update_ticket_status.php', {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ ticketId, status })
            });

            const result = await response.json();

            if (result.success) {
                this.showSuccess('Ticket status updated successfully!');
                await this.loadTickets();
            } else {
                this.showError(result.message || 'Failed to update ticket status');
            }
        } catch (error) {
            console.error('Status update error:', error);
            this.showError('Connection error. Please try again.');
        }
    }

    async toggleUserStatus(userId) {
        try {
            const token = localStorage.getItem('auth_token');
            const response = await fetch('php/admin_toggle_user_status.php', {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ userId })
            });

            const result = await response.json();

            if (result.success) {
                this.showSuccess('User status updated successfully!');
                await this.loadUsers();
            } else {
                this.showError(result.message || 'Failed to update user status');
            }
        } catch (error) {
            console.error('User status update error:', error);
            this.showError('Connection error. Please try again.');
        }
    }

    viewUser(userId) {
        this.showInfo(`Viewing user #${userId} - Feature coming soon!`);
    }

    displayTicketsError() {
        const tbody = document.getElementById('ticketsTableBody');
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center py-4">
                    <i class="fas fa-exclamation-triangle fa-2x text-warning mb-3"></i>
                    <p class="text-warning">Error loading tickets</p>
                    <button class="btn btn-outline-primary btn-sm" onclick="adminManager.loadTickets()">
                        <i class="fas fa-refresh me-1"></i>Retry
                    </button>
                </td>
            </tr>
        `;
    }

    initializeCharts() {
        // Initialize Chart.js charts
        this.initTicketStatusChart();
        this.initTicketPriorityChart();
    }

    initTicketStatusChart() {
        const ctx = document.getElementById('ticketStatusChart');
        if (!ctx) return;

        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Open', 'In Progress', 'Resolved', 'Closed'],
                datasets: [{
                    data: [0, 0, 0, 0], // Will be updated with real data
                    backgroundColor: [
                        '#3B82F6',
                        '#F59E0B',
                        '#10B981',
                        '#6B7280'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
    }

    initTicketPriorityChart() {
        const ctx = document.getElementById('ticketPriorityChart');
        if (!ctx) return;

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Low', 'Medium', 'High', 'Urgent'],
                datasets: [{
                    label: 'Tickets',
                    data: [0, 0, 0, 0], // Will be updated with real data
                    backgroundColor: [
                        '#6B7280',
                        '#3B82F6',
                        '#F59E0B',
                        '#EF4444'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
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

    refreshData() {
        this.showInfo('Refreshing data...');
        this.loadDashboardData();
    }

    exportData() {
        this.showInfo('Export feature coming soon!');
    }

    handleSettingsUpdate(event) {
        event.preventDefault();
        this.showSuccess('Settings updated successfully!');
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
        const existingToast = document.querySelector('.admin-toast');
        if (existingToast) {
            existingToast.remove();
        }

        // Create toast
        const toast = document.createElement('div');
        toast.className = `admin-toast toast-${type}`;
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
function refreshData() {
    adminManager.refreshData();
}

function exportData() {
    adminManager.exportData();
}

function logout() {
    adminManager.logout();
}

// Initialize admin manager
let adminManager;
document.addEventListener('DOMContentLoaded', () => {
    adminManager = new AdminManager();
});