#!/bin/bash
# BuzzUp Ansible Setup Script
# This script sets up the Ansible environment and prepares for deployment

set -e

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ANSIBLE_DIR="$(dirname "$SCRIPT_DIR")"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Functions
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

install_ansible() {
    log_info "Installing Ansible..."
    
    if command -v ansible &> /dev/null; then
        log_success "Ansible is already installed"
        ansible --version
        return 0
    fi
    
    # Detect OS and install Ansible accordingly
    if [[ "$OSTYPE" == "linux-gnu"* ]]; then
        # Ubuntu/Debian
        if command -v apt-get &> /dev/null; then
            sudo apt-get update
            sudo apt-get install -y software-properties-common
            sudo add-apt-repository --yes --update ppa:ansible/ansible
            sudo apt-get install -y ansible
        # CentOS/RHEL/Fedora
        elif command -v yum &> /dev/null; then
            sudo yum install -y epel-release
            sudo yum install -y ansible
        elif command -v dnf &> /dev/null; then
            sudo dnf install -y ansible
        else
            log_error "Unsupported Linux distribution"
            exit 1
        fi
    elif [[ "$OSTYPE" == "darwin"* ]]; then
        # macOS
        if command -v brew &> /dev/null; then
            brew install ansible
        else
            log_error "Homebrew not found. Please install Homebrew first."
            exit 1
        fi
    else
        log_error "Unsupported operating system: $OSTYPE"
        exit 1
    fi
    
    log_success "Ansible installed successfully"
    ansible --version
}

setup_ssh_key() {
    log_info "Setting up SSH key..."
    
    local ssh_key_path="$HOME/.ssh/id_rsa"
    
    if [ -f "$ssh_key_path" ]; then
        log_success "SSH key already exists at $ssh_key_path"
    else
        log_info "Generating new SSH key..."
        ssh-keygen -t rsa -b 4096 -f "$ssh_key_path" -N ""
        log_success "SSH key generated at $ssh_key_path"
    fi
    
    log_info "Your public key (copy this to your server's ~/.ssh/authorized_keys):"
    echo "----------------------------------------"
    cat "$ssh_key_path.pub"
    echo "----------------------------------------"
    
    log_warning "Make sure to copy the above public key to your server's ~/.ssh/authorized_keys file"
}

setup_vault() {
    log_info "Setting up Ansible Vault..."
    
    local vault_pass_file="$ANSIBLE_DIR/.vault_pass"
    
    if [ -f "$vault_pass_file" ]; then
        log_success "Vault password file already exists"
    else
        log_info "Creating vault password file..."
        read -s -p "Enter a password for Ansible Vault: " vault_password
        echo
        echo "$vault_password" > "$vault_pass_file"
        chmod 600 "$vault_pass_file"
        log_success "Vault password file created"
    fi
    
    # Encrypt the vault file if it's not already encrypted
    local vault_file="$ANSIBLE_DIR/group_vars/vault.yml"
    if [ -f "$vault_file" ]; then
        if ! ansible-vault view "$vault_file" --vault-password-file "$vault_pass_file" &> /dev/null; then
            log_info "Encrypting vault file..."
            ansible-vault encrypt "$vault_file" --vault-password-file "$vault_pass_file"
            log_success "Vault file encrypted"
        else
            log_success "Vault file is already encrypted"
        fi
    fi
}

configure_inventory() {
    log_info "Configuring inventory..."
    
    local inventory_file="$ANSIBLE_DIR/inventory/hosts.yml"
    
    if [ -f "$inventory_file" ]; then
        log_warning "Please update the inventory file with your server details:"
        log_warning "File: $inventory_file"
        log_warning "Update the following:"
        log_warning "  - YOUR_VPS_IP_ADDRESS: Replace with your actual server IP"
        log_warning "  - YOUR_STAGING_IP_ADDRESS: Replace with your staging server IP (if applicable)"
        log_warning "  - project_repo: Replace with your actual GitHub repository URL"
        log_warning "  - server_name: Replace with your actual domain name"
        log_warning "  - ssl_email: Replace with your actual email address"
    else
        log_error "Inventory file not found: $inventory_file"
        exit 1
    fi
}

test_connection() {
    log_info "Testing connection to servers..."
    
    cd "$ANSIBLE_DIR"
    
    if ansible all -i inventory/hosts.yml -m ping --vault-password-file .vault_pass; then
        log_success "Connection test successful!"
    else
        log_error "Connection test failed. Please check:"
        log_error "  1. Server IP addresses in inventory/hosts.yml"
        log_error "  2. SSH key is properly set up on the server"
        log_error "  3. Server is accessible and running"
        exit 1
    fi
}

show_next_steps() {
    log_success "Ansible setup completed!"
    echo
    log_info "Next steps:"
    echo "1. Update inventory file: $ANSIBLE_DIR/inventory/hosts.yml"
    echo "   - Replace YOUR_VPS_IP_ADDRESS with your actual server IP"
    echo "   - Update server_name with your domain"
    echo "   - Update ssl_email with your email"
    echo
    echo "2. Update vault file: $ANSIBLE_DIR/group_vars/vault.yml"
    echo "   - Edit with: ansible-vault edit group_vars/vault.yml --vault-password-file .vault_pass"
    echo "   - Update all password placeholders"
    echo
    echo "3. Copy your SSH public key to the server:"
    echo "   ssh-copy-id ubuntu@YOUR_SERVER_IP"
    echo
    echo "4. Test the connection:"
    echo "   cd $ANSIBLE_DIR && ansible all -i inventory/hosts.yml -m ping"
    echo
    echo "5. Run the deployment:"
    echo "   ./scripts/deploy.sh production"
    echo
}

# Main script
main() {
    log_info "Starting BuzzUp Ansible setup..."
    
    # Change to ansible directory
    cd "$ANSIBLE_DIR"
    
    # Install Ansible
    install_ansible
    
    # Setup SSH key
    setup_ssh_key
    
    # Setup Ansible Vault
    setup_vault
    
    # Configure inventory
    configure_inventory
    
    # Show next steps
    show_next_steps
}

# Execute main function
main "$@"