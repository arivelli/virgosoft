#!/usr/bin/env bash
set -euo pipefail

# Virgosoft Project - CA Installation Script
# Installs the local CA certificate in various systems

SCRIPT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
PROJECT_DIR=$(cd "${SCRIPT_DIR}/.." && pwd)
CA_CERT="${PROJECT_DIR}/etc/ssl/ca/certs/ca.cert.pem"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Print colored output
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if CA certificate exists
check_ca_cert() {
    if [ ! -f "${CA_CERT}" ]; then
        print_error "CA certificate not found at ${CA_CERT}"
        print_status "Please run SSL setup first: make ssl-setup"
        exit 1
    fi
}

# Install CA in Linux system certificates
install_linux_ca() {
    print_status "Installing CA in Linux system certificates..."
    
    if [ "$EUID" -ne 0 ]; then
        print_warning "This command requires sudo privileges"
        sudo -k # Reset sudo timestamp
    fi
    
    # Copy to system certificates directory
    sudo cp "${CA_CERT}" "/usr/local/share/ca-certificates/${PROJECT_NAME:-virgosoft}-local-ca.crt"
    
    # Update certificates
    sudo update-ca-certificates
    
    print_success "CA installed in Linux system certificates"
}

# Install CA in Firefox
install_firefox_ca() {
    print_status "Installing CA in Firefox..."
    
    # Get Firefox profile directory
    FIREFOX_DIR=""
    
    if [ -d "$HOME/.mozilla/firefox" ]; then
        FIREFOX_DIR="$HOME/.mozilla/firefox"
    elif [ -d "$HOME/snap/firefox/common/.mozilla/firefox" ]; then
        FIREFOX_DIR="$HOME/snap/firefox/common/.mozilla/firefox"
    else
        print_warning "Firefox not found in standard locations"
        return 1
    fi
    
    # Find default profile
    DEFAULT_PROFILE=$(find "${FIREFOX_DIR}" -name "*.default-release" -type d | head -1)
    
    if [ -z "${DEFAULT_PROFILE}" ]; then
        print_warning "Firefox default profile not found"
        return 1
    fi
    
    # Copy CA certificate to Firefox profile
    mkdir -p "${DEFAULT_PROFILE}/certificates"
    cp "${CA_CERT}" "${DEFAULT_PROFILE}/certificates/${PROJECT_NAME:-virgosoft}-local-ca.pem"
    
    print_success "CA installed in Firefox"
    print_warning "You may need to restart Firefox and manually trust the certificate"
    print_status "Firefox profile: ${DEFAULT_PROFILE}"
}

# Install CA in Chrome/Chromium
install_chrome_ca() {
    print_status "Installing CA in Chrome/Chromium..."
    
    # Chrome/Chromium NSS database directory
    CHROME_DIR="$HOME/.pki/nssdb"
    
    if [ ! -d "${CHROME_DIR}" ]; then
        print_warning "Chrome/Chromium NSS database not found"
        return 1
    fi
    
    # Use certutil to add certificate
    if command -v certutil >/dev/null 2>&1; then
        certutil -A -n "${CA_NAME:-virgosoft Local CA}" -t "C,," -d "${CHROME_DIR}" -i "${CA_CERT}"
        print_success "CA installed in Chrome/Chromium"
    else
        print_warning "certutil not found. Please install libnss3-tools:"
        print_status "sudo apt-get install libnss3-tools"
        return 1
    fi
}

# Install CA in Postman
install_postman_ca() {
    print_status "Installing CA in Postman..."
    
    # Postman CA directory
    POSTMAN_CA_DIR="$HOME/.config/Postman/certificates"
    
    if [ ! -d "${POSTMAN_CA_DIR}" ]; then
        print_warning "Postman certificates directory not found"
        print_status "Creating Postman certificates directory..."
        mkdir -p "${POSTMAN_CA_DIR}"
    fi
    
    # Copy CA certificate
    cp "${CA_CERT}" "${POSTMAN_CA_DIR}/${PROJECT_NAME:-virgosoft}-local-ca.crt"
    
    print_success "CA installed in Postman"
    print_warning "You may need to restart Postman and enable SSL certificate verification"
}

# Install CA in Docker containers
install_docker_ca() {
    print_status "Installing CA in Docker containers..."
    
    # Check if docker compose is available
    if ! command -v docker >/dev/null 2>&1; then
        print_warning "Docker not available"
        return 1
    fi
    
    # Copy CA to container for PHP
    if docker compose exec api bash -c "mkdir -p /usr/local/share/ca-certificates" 2>/dev/null; then
        docker compose exec -T api sh -c "cat > /usr/local/share/ca-certificates/${PROJECT_NAME:-virgosoft}-local-ca.crt" < "${CA_CERT}"
        docker compose exec api update-ca-certificates
        print_success "CA installed in PHP container"
    else
        print_warning "Could not install CA in PHP container (containers not running?)"
    fi
}

# Create CA installation bundle
create_ca_bundle() {
    print_status "Creating CA installation bundle..."
    
    BUNDLE_DIR="${PROJECT_DIR}/ca-bundle"
    mkdir -p "${BUNDLE_DIR}"
    
    # Copy CA certificate
    cp "${CA_CERT}" "${BUNDLE_DIR}/${PROJECT_NAME:-virgosoft}-local-ca.crt"
    
    # Create installation instructions
    cat > "${BUNDLE_DIR}/README.md" << 'EOF'
# ${CA_NAME:-virgosoft Local CA} Installation Bundle

This bundle contains the ${CA_NAME:-virgosoft Local CA} certificate for SSL development.

## Files
- `${PROJECT_NAME:-virgosoft}-local-ca.crt` - CA certificate file

## Installation Instructions

### Linux System
```bash
sudo cp ${PROJECT_NAME:-virgosoft}-local-ca.crt /usr/local/share/ca-certificates/
sudo update-ca-certificates
```

### Firefox
1. Open Firefox
2. Go to Settings > Privacy & Security > Certificates > View Certificates
3. Click "Authorities" tab
4. Click "Import" and select `${PROJECT_NAME:-virgosoft}-local-ca.crt`
5. Check "Trust this CA to identify websites" and click OK

### Chrome/Chromium
```bash
certutil -A -n "${CA_NAME:-virgosoft Local CA}" -t "C,," -d ~/.pki/nssdb -i ${PROJECT_NAME:-virgosoft}-local-ca.crt
```

### Postman
1. Copy `${PROJECT_NAME:-virgosoft}-local-ca.crt` to `~/.config/Postman/certificates/`
2. Restart Postman
3. Enable SSL certificate verification in settings

### Manual Installation
- Import the certificate in your browser/system as a trusted root CA
- The certificate is valid for 10 years

## Domain
This CA is valid for:
- ${DOMAIN:-virgosoft.local.xima.com.ar}
- ${API_DOMAIN:-api.virgosoft.local.xima.com.ar}
EOF
    
    print_success "CA bundle created in ${BUNDLE_DIR}"
}

# Display installation summary
display_summary() {
    print_status "CA Installation Summary:"
    echo ""
    echo "✅ CA Certificate: ${CA_CERT}"
    echo ""
    echo "Installation methods available:"
    echo "  make install-ca-linux    # Install in Linux system"
    echo "  make install-ca-firefox  # Install in Firefox"
    echo "  make install-ca-chrome   # Install in Chrome/Chromium"
    echo "  make install-ca-postman  # Install in Postman"
    echo "  make install-ca-docker   # Install in Docker containers"
    echo "  make install-ca-all      # Install in all systems"
    echo "  make bundle-ca           # Create installation bundle"
    echo ""
    echo "After installation:"
    echo "1. Setup SSL configuration: make setup-ssl"
    echo "2. Restart services: make restart"
    echo "3. Test SSL: make test-ssl"
}

# Main execution
main() {
    local action=${1:-all}
    
    print_status "Installing ${CA_NAME:-virgosoft Local CA} certificate..."
    echo ""
    
    check_ca_cert
    
    case "${action}" in
        linux)
            install_linux_ca
            ;;
        firefox)
            install_firefox_ca
            ;;
        chrome)
            install_chrome_ca
            ;;
        postman)
            install_postman_ca
            ;;
        docker)
            install_docker_ca
            ;;
        bundle)
            create_ca_bundle
            ;;
        all)
            install_linux_ca
            install_firefox_ca
            install_chrome_ca
            install_postman_ca
            install_docker_ca
            create_ca_bundle
            ;;
        *)
            print_error "Unknown action: ${action}"
            echo ""
            echo "Available actions:"
            echo "  linux    - Install in Linux system certificates"
            echo "  firefox  - Install in Firefox"
            echo "  chrome   - Install in Chrome/Chromium"
            echo "  postman  - Install in Postman"
            echo "  docker   - Install in Docker containers"
            echo "  bundle   - Create installation bundle"
            echo "  all      - Install in all systems (default)"
            exit 1
            ;;
    esac
    
    display_summary
    echo ""
    print_success "CA installation completed! 🚀"
}

# Run main function
main "$@"
