#!/bin/bash
# Run All Tests - ISS Tracker Project
# This script runs all test suites (Rust + Laravel)

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
GRAY='\033[0;90m'
NC='\033[0m' # No Color

total_errors=0

echo -e "${CYAN}================================${NC}"
echo -e "${CYAN}ISS Tracker - Run All Tests${NC}"
echo -e "${CYAN}================================${NC}"
echo ""

# ============================================
# 1. Rust Tests
# ============================================
echo -e "${YELLOW}[1/3] Running Rust Tests...${NC}"
echo -e "${GRAY}-------------------------------${NC}"

cd services/rust-iss

# Check if cargo is installed
if ! command -v cargo &> /dev/null; then
    echo -e "${YELLOW}⚠️  Rust (cargo) not installed - skipping Rust tests${NC}"
    echo -e "${GRAY}   Install from: https://rustup.rs/${NC}"
else
    echo -e "${GRAY}Running: cargo test${NC}"
    if cargo test; then
        echo -e "${GREEN}✅ Rust tests PASSED${NC}"
    else
        echo -e "${RED}❌ Rust tests FAILED${NC}"
        ((total_errors++))
    fi
fi

cd ../..
echo ""

# ============================================
# 2. Laravel Tests
# ============================================
echo -e "${YELLOW}[2/3] Running Laravel Tests...${NC}"
echo -e "${GRAY}-------------------------------${NC}"

# Check if Docker container is running
if docker ps | grep -q "php_web"; then
    echo -e "${GRAY}Running: docker exec php_web php artisan test${NC}"
    if docker exec php_web php artisan test; then
        echo -e "${GREEN}✅ Laravel tests PASSED${NC}"
    else
        echo -e "${RED}❌ Laravel tests FAILED${NC}"
        ((total_errors++))
    fi
else
    echo -e "${YELLOW}⚠️  PHP container not running. Skipping Laravel tests.${NC}"
    echo -e "${GRAY}Run: docker-compose up -d${NC}"
    ((total_errors++))
fi

echo ""

# ============================================
# 3. Test Summary
# ============================================
echo -e "${YELLOW}[3/3] Test Summary${NC}"
echo -e "${GRAY}-------------------------------${NC}"

if [ $total_errors -eq 0 ]; then
    echo -e "${GREEN}✅ ALL TESTS PASSED${NC}"
    echo ""
    echo "Total test suites: 2/2 passed"
else
    echo -e "${RED}❌ SOME TESTS FAILED${NC}"
    echo ""
    echo -e "${RED}Failed test suites: $total_errors${NC}"
    echo ""
    echo -e "${YELLOW}To run tests individually:${NC}"
    echo -e "${GRAY}  Rust:    cd services/rust-iss && cargo test${NC}"
    echo -e "${GRAY}  Laravel: docker exec -it php_web php artisan test${NC}"
fi

echo ""
echo -e "${CYAN}================================${NC}"

# Exit with error code if tests failed
exit $total_errors
