#!/usr/bin/env bash
set -euo pipefail

# Virgosoft Project - SSL Setup Script
# Creates local CA and SSL certificates for development

SCRIPT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
PROJECT_DIR=$(cd "${SCRIPT_DIR}/.." && pwd)
SSL_DIR="${PROJECT_DIR}/etc/ssl"
CA_DIR="${SSL_DIR}/ca"
CERTS_DIR="${SSL_DIR}/certs"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Load environment variables
if [ -f "${PROJECT_DIR}/.env" ]; then
    source "${PROJECT_DIR}/.env"
fi

# Configuration with environment variable fallbacks
DOMAIN="${DOMAIN:-virgosoft.local.xima.com.ar}"
API_DOMAIN="${API_DOMAIN:-api.virgosoft.local.xima.com.ar}"
CA_NAME="${CA_NAME:-virgosoft Local CA}"
COUNTRY="${COUNTRY:-AR}"
STATE="${STATE:-Buenos Aires}"
LOCALITY="${LOCALITY:-Buenos Aires}"
ORGANIZATION="${ORGANIZATION:-virgosoft Project}"
ORGANIZATIONAL_UNIT="${ORGANIZATIONAL_UNIT:-Development}"

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

# Create directories
create_directories() {
    print_status "Creating SSL directories..."
    mkdir -p "${CA_DIR}/private" "${CA_DIR}/certs" "${CERTS_DIR}"
    chmod 700 "${CA_DIR}/private"
    chmod 755 "${CA_DIR}/certs" "${CERTS_DIR}"
    print_success "SSL directories created"
}

# Create CA private key
create_ca_key() {
    print_status "Creating CA private key..."
    if [ -f "${CA_DIR}/private/ca.key.pem" ]; then
        print_warning "CA private key already exists"
        return 0
    fi
    
    openssl genpkey -algorithm RSA \
        -pkeyopt rsa_keygen_bits:4096 \
        -out "${CA_DIR}/private/ca.key.pem"
    
    chmod 400 "${CA_DIR}/private/ca.key.pem"
    print_success "CA private key created"
}

# Create CA certificate
create_ca_cert() {
    print_status "Creating CA certificate..."
    if [ -f "${CA_DIR}/certs/ca.cert.pem" ]; then
        print_warning "CA certificate already exists"
        return 0
    fi
    
    openssl req -x509 -new -nodes \
        -key "${CA_DIR}/private/ca.key.pem" \
        -sha256 -days 3650 \
        -out "${CA_DIR}/certs/ca.cert.pem" \
        -subj "/C=${COUNTRY}/ST=${STATE}/L=${LOCALITY}/O=${ORGANIZATION}/OU=${ORGANIZATIONAL_UNIT}/CN=${CA_NAME}"
    
    chmod 444 "${CA_DIR}/certs/ca.cert.pem"
    print_success "CA certificate created"
}

# Create domain private key
create_domain_key() {
    local domain=$1
    print_status "Creating private key for ${domain}..."
    
    openssl genpkey -algorithm RSA \
        -pkeyopt rsa_keygen_bits:2048 \
        -out "${CERTS_DIR}/${domain}.key.pem"
    
    chmod 400 "${CERTS_DIR}/${domain}.key.pem"
    print_success "Private key for ${domain} created"
}

# Create CSR for domain
create_csr() {
    local domain=$1
    print_status "Creating CSR for ${domain}..."
    
    openssl req -new \
        -key "${CERTS_DIR}/${domain}.key.pem" \
        -out "${CERTS_DIR}/${domain}.csr.pem" \
        -subj "/C=${COUNTRY}/ST=${STATE}/L=${LOCALITY}/O=${ORGANIZATION}/OU=${ORGANIZATIONAL_UNIT}/CN=${domain}"
    
    print_success "CSR for ${domain} created"
}

# Create domain certificate
create_domain_cert() {
    local domain=$1
    local san_dns=$2
    print_status "Creating certificate for ${domain}..."
    
    # Create ext file for SAN
    cat > "${CERTS_DIR}/${domain}.ext.cnf" << EOF
authorityKeyIdentifier=keyid,issuer
basicConstraints=CA:FALSE
keyUsage = digitalSignature, nonRepudiation, keyEncipherment, dataEncipherment
subjectAltName = @alt_names

[alt_names]
DNS.1 = ${domain}
DNS.2 = ${san_dns}
EOF

    # Sign the certificate
    openssl x509 -req -in "${CERTS_DIR}/${domain}.csr.pem" \
        -CA "${CA_DIR}/certs/ca.cert.pem" \
        -CAkey "${CA_DIR}/private/ca.key.pem" \
        -CAcreateserial \
        -out "${CERTS_DIR}/${domain}.cert.pem" \
        -days 365 \
        -sha256 -extfile "${CERTS_DIR}/${domain}.ext.cnf"
    
    chmod 444 "${CERTS_DIR}/${domain}.cert.pem"
    print_success "Certificate for ${domain} created"
}

# Verify certificates
verify_certificates() {
    print_status "Verifying certificates..."
    
    # Verify domain certificate against CA
    openssl verify -CAfile "${CA_DIR}/certs/ca.cert.pem" \
        "${CERTS_DIR}/${DOMAIN}.cert.pem"
    
    openssl verify -CAfile "${CA_DIR}/certs/ca.cert.pem" \
        "${CERTS_DIR}/${API_DOMAIN}.cert.pem"
    
    print_success "All certificates verified"
}

# Display certificate info
display_info() {
    print_status "Certificate Information:"
    echo ""
    echo "CA Certificate: ${CA_DIR}/certs/ca.cert.pem"
    echo "Domain Certificate: ${CERTS_DIR}/${DOMAIN}.cert.pem"
    echo "API Certificate: ${CERTS_DIR}/${API_DOMAIN}.cert.pem"
    echo ""
    echo "CA Certificate Info:"
    openssl x509 -noout -text -in "${CA_DIR}/certs/ca.cert.pem" | grep -A 2 "Subject:"
    echo ""
    echo "Domain Certificate Info:"
    openssl x509 -noout -text -in "${CERTS_DIR}/${DOMAIN}.cert.pem" | grep -A 2 "Subject:"
    echo ""
    echo "Next steps:"
    echo "1. Install CA certificate: make install-ca"
    echo "2. Update Nginx configuration: make setup-ssl"
    echo "3. Restart services: make restart"
    echo "4. Test SSL: make test-ssl"
}

# Main execution
main() {
    print_status "Setting up SSL certificates for Virgosoft Project..."
    echo ""
    
    # Check if OpenSSL is available
    if ! command -v openssl >/dev/null 2>&1; then
        print_error "OpenSSL is not available. Please install OpenSSL."
        exit 1
    fi
    
    create_directories
    create_ca_key
    create_ca_cert
    
    # Create certificates for main domain
    create_domain_key "${DOMAIN}"
    create_csr "${DOMAIN}"
    create_domain_cert "${DOMAIN}" "www.${DOMAIN}"
    
    # Create certificates for API domain
    create_domain_key "${API_DOMAIN}"
    create_csr "${API_DOMAIN}"
    create_domain_cert "${API_DOMAIN}" "${DOMAIN}"
    
    verify_certificates
    display_info
    
    echo ""
    print_success "SSL setup completed! 🚀"
}

# Run main function
main "$@"
