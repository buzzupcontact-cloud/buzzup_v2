# BuzzUp Ansible Deployment

This Ansible project automates the deployment of the BuzzUp website to Ubuntu 22.04 servers on Azure Cloud.

## Features

- **Complete LAMP Stack**: Apache, MySQL, PHP 8.1
- **Security Hardening**: UFW firewall, Fail2ban, SSH hardening
- **SSL/TLS**: Automatic Let's Encrypt certificate management
- **Performance Optimization**: Caching, compression, optimized configurations
- **Monitoring**: Log rotation, system monitoring
- **Backup**: Automated database and file backups

## Prerequisites

- Ubuntu 22.04 VPS on Azure Cloud
- SSH access to the server
- Domain name pointing to your server
- Ansible installed on your local machine

## Quick Start

### 1. Initial Setup

Run the setup script to install Ansible and configure the environment:

```bash
cd ansible
chmod +x scripts/setup.sh
./scripts/setup.sh
```

### 2. Configure Inventory

Edit `inventory/hosts.yml` and update:
- `YOUR_VPS_IP_ADDRESS` with your actual server IP
- `server_name` with your domain name
- `ssl_email` with your email address

### 3. Configure Secrets

Edit the vault file with your passwords:

```bash
ansible-vault edit group_vars/vault.yml --vault-password-file .vault_pass
```

Update all password placeholders with secure passwords.

### 4. Copy SSH Key

Copy your SSH public key to the server:

```bash
ssh-copy-id ubuntu@YOUR_SERVER_IP
```

### 5. Test Connection

Test the connection to your server:

```bash
ansible all -i inventory/hosts.yml -m ping
```

### 6. Deploy

Run the deployment:

```bash
chmod +x scripts/deploy.sh
./scripts/deploy.sh production
```

## Project Structure

```
ansible/
├── inventory/
│   └── hosts.yml              # Server inventory
├── group_vars/
│   ├── all.yml               # Global variables
│   └── vault.yml             # Encrypted secrets
├── roles/
│   ├── system/               # System configuration
│   ├── security/             # Security hardening
│   ├── mysql/                # MySQL database
│   ├── php/                  # PHP configuration
│   ├── apache/               # Apache web server
│   ├── application/          # Application deployment
│   └── ssl/                  # SSL certificate management
├── playbooks/
│   ├── deploy.yml            # Main deployment playbook
│   └── site.yml              # Site-wide operations
├── scripts/
│   ├── setup.sh              # Initial setup script
│   └── deploy.sh             # Deployment script
├── ansible.cfg               # Ansible configuration
└── README.md                 # This file
```

## Deployment Options

### Deploy Everything
```bash
./scripts/deploy.sh production
```

### Deploy Specific Components
```bash
./scripts/deploy.sh production system,security
./scripts/deploy.sh production app
./scripts/deploy.sh production ssl
```

### Deploy to Staging
```bash
./scripts/deploy.sh staging
```

## Available Tags

- `system` - System configuration and packages
- `security` - Security hardening (firewall, fail2ban, SSH)
- `database` - MySQL installation and configuration
- `php` - PHP installation and configuration
- `webserver` - Apache installation and configuration
- `app` - Application deployment
- `ssl` - SSL certificate setup
- `verify` - Post-deployment verification

## Maintenance Tasks

### Run System Maintenance
```bash
ansible-playbook -i inventory/hosts.yml playbooks/site.yml --tags maintenance
```

### Security Audit
```bash
ansible-playbook -i inventory/hosts.yml playbooks/site.yml --tags security,audit
```

### Create Backup
```bash
ansible-playbook -i inventory/hosts.yml playbooks/site.yml --tags backup
```

## Configuration Files

### Inventory Configuration

The `inventory/hosts.yml` file contains server definitions and variables:

```yaml
webservers:
  hosts:
    buzzup-prod:
      ansible_host: YOUR_VPS_IP_ADDRESS
      server_name: buzzup.com
      mysql_database: buzzup_db
      # ... other variables
```

### Vault Configuration

The `group_vars/vault.yml` file contains encrypted secrets:

```yaml
vault_mysql_root_password: "secure_password"
vault_mysql_password: "secure_password"
vault_jwt_secret: "secure_jwt_secret"
```

## Security Features

- **Firewall**: UFW configured to allow only necessary ports
- **Intrusion Detection**: Fail2ban monitors and blocks suspicious activity
- **SSH Hardening**: Disabled root login, password authentication
- **SSL/TLS**: Automatic Let's Encrypt certificates with strong ciphers
- **File Permissions**: Secure file and directory permissions
- **Security Headers**: HTTP security headers configured

## Performance Optimizations

- **Caching**: Browser caching headers configured
- **Compression**: Gzip compression enabled
- **PHP OPcache**: Enabled for better PHP performance
- **Database**: Optimized MySQL configuration
- **Log Rotation**: Automatic log cleanup

## Monitoring and Logging

- **System Logs**: Centralized logging with rotation
- **Application Logs**: PHP error logging configured
- **Access Logs**: Apache access and error logs
- **Security Logs**: Fail2ban and authentication logs

## Backup Strategy

- **Database Backups**: Daily MySQL dumps
- **File Backups**: Website files archived
- **Configuration Backups**: Server configuration backed up
- **Retention**: 7-day backup retention policy

## Troubleshooting

### Connection Issues
```bash
# Test SSH connection
ssh ubuntu@YOUR_SERVER_IP

# Test Ansible connection
ansible all -i inventory/hosts.yml -m ping
```

### Deployment Failures
```bash
# Run with verbose output
ansible-playbook -i inventory/hosts.yml playbooks/deploy.yml -vvv

# Check specific service
ansible webservers -i inventory/hosts.yml -m systemd -a "name=apache2 state=started"
```

### SSL Certificate Issues
```bash
# Check certificate status
ansible webservers -i inventory/hosts.yml -m shell -a "certbot certificates"

# Renew certificates manually
ansible webservers -i inventory/hosts.yml -m shell -a "certbot renew --dry-run"
```

## GitHub Actions Integration

This Ansible project is designed to work with GitHub Actions for CI/CD. Create a workflow file in your main repository:

```yaml
name: Deploy to Production
on:
  push:
    branches: [main]
jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Setup Ansible
        run: |
          sudo apt-get update
          sudo apt-get install -y ansible
      - name: Deploy
        run: |
          cd ansible
          echo "${{ secrets.VAULT_PASSWORD }}" > .vault_pass
          echo "${{ secrets.SSH_PRIVATE_KEY }}" > ~/.ssh/id_rsa
          chmod 600 ~/.ssh/id_rsa
          ./scripts/deploy.sh production
```

## Support

For issues and questions:
1. Check the logs: `/var/log/ansible.log`
2. Review service status: `systemctl status apache2 mysql php8.1-fpm`
3. Check application logs: `/var/www/html/buzzup/logs/`

## License

This Ansible configuration is part of the BuzzUp project and follows the same licensing terms.