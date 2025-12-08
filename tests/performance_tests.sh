# Performance Testing Script (Bash)
# For Linux/macOS environments

#!/bin/bash

echo "================================"
echo "ISS Tracker Performance Tests"
echo "================================"
echo ""

API_URL="http://localhost:8080"
PHP_URL="http://localhost"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if wrk is installed
if ! command -v wrk &> /dev/null; then
    echo -e "${RED}wrk is not installed. Install it first:${NC}"
    echo "Ubuntu/Debian: sudo apt-get install wrk"
    echo "macOS: brew install wrk"
    echo "CentOS/RHEL: sudo yum install wrk"
    exit 1
fi

# Test 1: Health Endpoint
echo -e "${YELLOW}[Test 1] Rust API Health Check${NC}"
wrk -t4 -c100 -d10s --latency $API_URL/health
echo ""

# Test 2: ISS Current Position (Cached)
echo -e "${YELLOW}[Test 2] Rust API - ISS Current (Cached)${NC}"
wrk -t4 -c100 -d30s --latency $API_URL/iss/current
echo ""

# Test 3: ISS History (Database)
echo -e "${YELLOW}[Test 3] Rust API - ISS History (Database)${NC}"
wrk -t4 -c50 -d20s --latency "$API_URL/iss/history?limit=100"
echo ""

# Test 4: PHP Dashboard
echo -e "${YELLOW}[Test 4] PHP Laravel Dashboard${NC}"
wrk -t4 -c50 -d20s --latency $PHP_URL/
echo ""

# Test 5: Proxy Endpoint
echo -e "${YELLOW}[Test 5] PHP Proxy to Rust${NC}"
wrk -t4 -c50 -d20s --latency $PHP_URL/proxy/iss/current
echo ""

# Test 6: Stress Test
echo -e "${YELLOW}[Test 6] Stress Test - 500 Connections${NC}"
wrk -t8 -c500 -d60s --latency $API_URL/iss/current
echo ""

echo -e "${GREEN}Performance tests completed!${NC}"
echo ""
echo "Monitor resources:"
echo "docker stats"
echo ""
echo "Check PostgreSQL connections:"
echo "docker exec -it postgres psql -U postgres -d iss_tracker -c 'SELECT count(*) FROM pg_stat_activity;'"
echo ""
echo "Check Redis cache:"
echo "docker exec -it redis redis-cli INFO stats | grep keyspace_hits"
