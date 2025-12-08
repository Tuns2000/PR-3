#!/usr/bin/env pwsh
# Run All Tests - ISS Tracker Project
# This script runs all test suites (Rust + Laravel)

Write-Host "================================" -ForegroundColor Cyan
Write-Host "ISS Tracker - Run All Tests" -ForegroundColor Cyan
Write-Host "================================" -ForegroundColor Cyan
Write-Host ""

$ErrorActionPreference = "Continue"
$totalErrors = 0

# ============================================
# 1. Rust Tests
# ============================================
Write-Host "[1/3] Running Rust Tests..." -ForegroundColor Yellow
Write-Host "-------------------------------" -ForegroundColor Gray

Push-Location "services\rust-iss"

# Check if cargo is installed
$cargoExists = Get-Command cargo -ErrorAction SilentlyContinue

if ($null -eq $cargoExists) {
    Write-Host "⚠️  Rust (cargo) not installed - skipping Rust tests" -ForegroundColor Yellow
    Write-Host "   Install from: https://rustup.rs/" -ForegroundColor Gray
} else {
    Write-Host "Running: cargo test" -ForegroundColor Gray
    cargo test --color=always
    
    if ($LASTEXITCODE -ne 0) {
        Write-Host "❌ Rust tests FAILED" -ForegroundColor Red
        $totalErrors++
    } else {
        Write-Host "✅ Rust tests PASSED" -ForegroundColor Green
    }
}

Pop-Location
Write-Host ""

# ============================================
# 2. Laravel Tests
# ============================================
Write-Host "[2/3] Running Laravel Tests..." -ForegroundColor Yellow
Write-Host "-------------------------------" -ForegroundColor Gray

# Check if Docker container is running
$phpContainer = docker ps --filter "name=php_web" --format "{{.Names}}" 2>$null

if ($phpContainer -eq "php_web") {
    Write-Host "Running: docker exec php_web php artisan test" -ForegroundColor Gray
    docker exec php_web php artisan test --color=always
    
    if ($LASTEXITCODE -ne 0) {
        Write-Host "❌ Laravel tests FAILED" -ForegroundColor Red
        $totalErrors++
    } else {
        Write-Host "✅ Laravel tests PASSED" -ForegroundColor Green
    }
} else {
    Write-Host "⚠️  PHP container not running. Skipping Laravel tests." -ForegroundColor Yellow
    Write-Host "Run: docker-compose up -d" -ForegroundColor Gray
    $totalErrors++
}

Write-Host ""

# ============================================
# 3. Test Summary
# ============================================
Write-Host "[3/3] Test Summary" -ForegroundColor Yellow
Write-Host "-------------------------------" -ForegroundColor Gray

if ($totalErrors -eq 0) {
    Write-Host "✅ ALL TESTS PASSED" -ForegroundColor Green
    Write-Host ""
    Write-Host "Total test suites: 2/2 passed" -ForegroundColor White
} else {
    Write-Host "❌ SOME TESTS FAILED" -ForegroundColor Red
    Write-Host ""
    Write-Host "Failed test suites: $totalErrors" -ForegroundColor Red
    Write-Host ""
    Write-Host "To run tests individually:" -ForegroundColor Yellow
    Write-Host "  Rust:    cd services\rust-iss && cargo test" -ForegroundColor Gray
    Write-Host "  Laravel: docker exec -it php_web php artisan test" -ForegroundColor Gray
}

Write-Host ""
Write-Host "================================" -ForegroundColor Cyan

# Exit with error code if tests failed
exit $totalErrors
