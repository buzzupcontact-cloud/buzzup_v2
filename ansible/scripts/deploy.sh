#!/bin/bash
# BuzzUp Deployment Script
# Usage: ./deploy.sh [environment] [tags]

set -e

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ANSIBLE_DIR="$(dirname "$SCRIPT_DIR")"
DEFAULT_ENV="production"
DEFAULT_TAGS="all"

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

show_usage() {
    echo "Usage: $0 [environment] [tags]"
    echo ""
    echo "Environments:"
    echo "  production  - Deploy to production servers (default)"
    echo "  staging     - Deploy to staging servers"
    echo ""
    echo "Tags:"
    echo "  all         - Run all deployment tasks (default)"
    echo "  system      - System configuration only"
    echo "  security    - Security configuration only"
    echo "  database    - Database setup only"
    echo "  php         - PHP configuration only"
    echo "  webserver   - Apache configuration only"
    echo "  app         - Application deployment only"
    echo "  ssl         - SSL certificate setup only"
    echo ""
    echo "Examples:"
    echo "  $0                          # Deploy everything to production"
    echo "  $0 staging                  # Deploy everything to staging"
    echo "  $0 production app           # Deploy only application to production"
    echo "  $0 staging system,security  # Deploy system and security to staging"
}

check_requirements() {
    log_info "Checking requirements..."
    
    # Check if ansible is installed
    if ! command -v ansible-playbook &> /dev/null; then
        log_error "Ansible is not installed. Please install Ansible first."
        exit 1
    fi
    
    # Check if vault password file exists
    if [ ! -f "$ANSIBLE_DIR/.vault_pass" ]; then
        log_warning "Vault password file not found. You'll be prompted for the vault password."
    fi
    
    # Check if inventory file exists
    if [ ! -f "$ANSIBLE_DIR/inventory/hosts.yml" ]; then
        log_error "Inventory file not found: $ANSIBLE_DIR/inventory/hosts.yml"
        exit 1
    fi
    
    log_success "Requirements check passed"
}

validate_environment() {
    local env=$1
    case $env in
        production|staging)
            return 0
            ;;
        *)
            log_error "Invalid environment: $env"
            log_error "Valid environments: production, staging"
            exit 1
            ;;
    esac
}

run_deployment() {
    local environment=$1
    local tags=$2
    
    log_info "Starting deployment to $environment environment"
    log_info "Tags: $tags"
    
    cd "$ANSIBLE_DIR"
    
    # Build ansible-playbook command
    local cmd="ansible-playbook"
    cmd="$cmd -i inventory/hosts.yml"
    cmd="$cmd playbooks/deploy.yml"
    cmd="$cmd --limit ${environment}"
    
    if [ "$tags" != "all" ]; then
        cmd="$cmd --tags $tags"
    fi
    
    # Add vault password file if it exists
    if [ -f ".vault_pass" ]; then
        cmd="$cmd --vault-password-file .vault_pass"
    else
        cmd="$cmd --ask-vault-pass"
    fi
    
    # Add verbose output for debugging
    cmd="$cmd -v"
    
    log_info "Running: $cmd"
    
    # Execute the deployment
    if eval $cmd; then
        log_success "Deployment completed successfully!"
        
        # Run post-deployment verification
        log_info "Running post-deployment verification..."
        ansible-playbook -i inventory/hosts.yml playbooks/deploy.yml --limit $environment --tags verify -v
        
        log_success "Deployment verification completed!"
    else
        log_error "Deployment failed!"
        exit 1
    fi
}

# Main script
main() {
    local environment=${1:-$DEFAULT_ENV}
    local tags=${2:-$DEFAULT_TAGS}
    
    # Show help if requested
    if [ "$1" = "-h" ] || [ "$1" = "--help" ]; then
        show_usage
        exit 0
    fi
    
    # Validate inputs
    validate_environment "$environment"
    
    # Check requirements
    check_requirements
    
    # Confirm deployment
    log_warning "You are about to deploy to $environment environment with tags: $tags"
    read -p "Do you want to continue? (y/N): " -n 1 -r
    echo
    
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        log_info "Deployment cancelled"
        exit 0
    fi
    
    # Run the deployment
    run_deployment "$environment" "$tags"
}

# Execute main function with all arguments
main "$@"