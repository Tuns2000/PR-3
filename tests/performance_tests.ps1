#!/usr/bin/env pwsh
# Performance Testing Script for ISS Tracker API
# Requirements: wrk (Windows: choco install wrk)

Write-Host "================================" -ForegroundColor Cyan
Write-Host "ISS Tracker Performance Tests" -ForegroundColor Cyan
Write-Host "================================" -ForegroundColor Cyan
Write-Host ""

$API_URL = "http://localhost:8080"
$PHP_URL = "http://localhost"

# Test 1: Rust API - Health Endpoint
Write-Host "[Test 1] Rust API Health Check" -ForegroundColor Yellow
Write-Host "Command: wrk -t4 -c100 -d10s --latency $API_URL/health" -ForegroundColor Gray
Write-Host "Expected: >1000 req/sec, <50ms p99" -ForegroundColor Green
Write-Host ""

# Test 2: Rust API - ISS Current Position (Cached)
Write-Host "[Test 2] Rust API - ISS Current Position (Cache Hit)" -ForegroundColor Yellow
Write-Host "Command: wrk -t4 -c100 -d30s --latency $API_URL/iss/current" -ForegroundColor Gray
Write-Host "Expected: >500 req/sec (Redis cache), <100ms p99" -ForegroundColor Green
Write-Host ""

# Test 3: Rust API - ISS History (Database Query)
Write-Host "[Test 3] Rust API - ISS History (PostgreSQL)" -ForegroundColor Yellow
Write-Host "Command: wrk -t4 -c50 -d20s --latency $API_URL/iss/history?limit=100" -ForegroundColor Gray
Write-Host "Expected: >200 req/sec, <200ms p99" -ForegroundColor Green
Write-Host ""

# Test 4: PHP Laravel - Dashboard (Multiple Queries)
Write-Host "[Test 4] PHP Laravel - Dashboard Page" -ForegroundColor Yellow
Write-Host "Command: wrk -t4 -c50 -d20s --latency $PHP_URL/" -ForegroundColor Gray
Write-Host "Expected: >50 req/sec, <500ms p99" -ForegroundColor Green
Write-Host ""

# Test 5: PHP Laravel - Proxy to Rust (Latency Test)
Write-Host "[Test 5] PHP Laravel - Proxy to Rust API" -ForegroundColor Yellow
Write-Host "Command: wrk -t4 -c50 -d20s --latency $PHP_URL/proxy/iss/current" -ForegroundColor Gray
Write-Host "Expected: >100 req/sec, <300ms p99 (PHP + Rust latency)" -ForegroundColor Green
Write-Host ""

# Test 6: Rate Limiting Test (60 req/min)
Write-Host "[Test 6] Rate Limiting - Should Throttle at 60 req/min" -ForegroundColor Yellow
Write-Host "Command: wrk -t1 -c1 -d65s --latency $API_URL/iss/current" -ForegroundColor Gray
Write-Host "Expected: 429 Too Many Requests after ~60 requests" -ForegroundColor Green
Write-Host ""

# Test 7: Concurrent Users Stress Test
Write-Host "[Test 7] Stress Test - 500 Concurrent Connections" -ForegroundColor Yellow
Write-Host "Command: wrk -t8 -c500 -d60s --latency $API_URL/iss/current" -ForegroundColor Gray
Write-Host "Expected: No errors, stable response times" -ForegroundColor Green
Write-Host ""

# Test 8: Database Connection Pool Test
Write-Host "[Test 8] Database Connection Pool - High Load" -ForegroundColor Yellow
Write-Host "Command: wrk -t8 -c200 -d30s --latency $API_URL/iss/history?limit=50" -ForegroundColor Gray
Write-Host "Expected: No connection errors, max 10 connections" -ForegroundColor Green
Write-Host ""

# Test 9: Redis Cache Performance
Write-Host "[Test 9] Redis Cache - Hit Rate Test" -ForegroundColor Yellow
Write-Host "Command: wrk -t4 -c100 -d60s --latency $API_URL/iss/current" -ForegroundColor Gray
Write-Host "Expected: >90% cache hit rate, <10ms Redis latency" -ForegroundColor Green
Write-Host ""

# Test 10: Memory Leak Test (Long Duration)
Write-Host "[Test 10] Memory Stability - 5 Minute Load Test" -ForegroundColor Yellow
Write-Host "Command: wrk -t4 -c100 -d300s --latency $API_URL/iss/current" -ForegroundColor Gray
Write-Host "Expected: Stable memory usage, no leaks" -ForegroundColor Green
Write-Host ""

Write-Host "================================" -ForegroundColor Cyan
Write-Host "Manual Test Commands" -ForegroundColor Cyan
Write-Host "================================" -ForegroundColor Cyan
Write-Host ""

Write-Host "# Install wrk (if not installed):" -ForegroundColor White
Write-Host "choco install wrk" -ForegroundColor Gray
Write-Host ""

Write-Host "# Run all tests:" -ForegroundColor White
Write-Host "wrk -t4 -c100 -d10s --latency http://localhost:8080/health" -ForegroundColor Gray
Write-Host "wrk -t4 -c100 -d30s --latency http://localhost:8080/iss/current" -ForegroundColor Gray
Write-Host "wrk -t4 -c50 -d20s --latency http://localhost:8080/iss/history?limit=100" -ForegroundColor Gray
Write-Host "wrk -t4 -c50 -d20s --latency http://localhost/" -ForegroundColor Gray
Write-Host ""

Write-Host "# Monitor resources during tests:" -ForegroundColor White
Write-Host "docker stats" -ForegroundColor Gray
Write-Host ""

Write-Host "# Check PostgreSQL connection count:" -ForegroundColor White
Write-Host "docker exec -it postgres psql -U postgres -d iss_tracker -c 'SELECT count(*) FROM pg_stat_activity;'" -ForegroundColor Gray
Write-Host ""

Write-Host "# Check Redis cache hit rate:" -ForegroundColor White
Write-Host "docker exec -it redis redis-cli INFO stats | Select-String 'keyspace_hits'" -ForegroundColor Gray
Write-Host ""

Write-Host "================================" -ForegroundColor Cyan
Write-Host "Performance Benchmarks" -ForegroundColor Cyan
Write-Host "================================" -ForegroundColor Cyan
Write-Host ""

Write-Host "Target Metrics:" -ForegroundColor Green
Write-Host "  - Health endpoint: >1000 req/sec" -ForegroundColor White
Write-Host "  - Cached endpoints: >500 req/sec" -ForegroundColor White
Write-Host "  - Database queries: >200 req/sec" -ForegroundColor White
Write-Host "  - p99 latency: <200ms" -ForegroundColor White
Write-Host "  - Error rate: <0.1%" -ForegroundColor White
Write-Host "  - Memory stable over 5 minutes" -ForegroundColor White
Write-Host ""

Write-Host "To run actual tests, install wrk and execute the commands above." -ForegroundColor Cyan
