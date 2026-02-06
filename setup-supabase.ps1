# Hospital Management System - PostgreSQL/Supabase Migration
# Quick Setup Script

Write-Host "============================================" -ForegroundColor Cyan
Write-Host "Hospital Management - Supabase Migration" -ForegroundColor Cyan
Write-Host "============================================" -ForegroundColor Cyan
Write-Host ""

# Check if .env exists
if (-Not (Test-Path ".env")) {
    Write-Host "❌ .env file not found!" -ForegroundColor Red
    Write-Host ""
    Write-Host "Creating .env from template..." -ForegroundColor Yellow
    
    if (Test-Path ".env.example") {
        Copy-Item ".env.example" ".env"
        Write-Host "✓ Created .env file from .env.example" -ForegroundColor Green
    } else {
        Write-Host "Please create a .env file with your Supabase credentials" -ForegroundColor Yellow
        Write-Host "See .env.supabase.template for reference" -ForegroundColor Yellow
        exit 1
    }
}

Write-Host "Current .env configuration:" -ForegroundColor Cyan
Write-Host "--------------------------------------------"
Get-Content .env
Write-Host "--------------------------------------------"
Write-Host ""

# Ask user to confirm credentials
$response = Read-Host "Have you updated .env with your Supabase credentials? (y/n)"
if ($response -ne "y") {
    Write-Host ""
    Write-Host "Please update your .env file with Supabase credentials:" -ForegroundColor Yellow
    Write-Host "  1. Go to Supabase Dashboard → Settings → Database" -ForegroundColor White
    Write-Host "  2. Copy your connection details" -ForegroundColor White
    Write-Host "  3. Update .env file with:" -ForegroundColor White
    Write-Host "     DB_CONNECTION=pgsql" -ForegroundColor Gray
    Write-Host "     DB_HOST=db.xxxxx.supabase.co" -ForegroundColor Gray
    Write-Host "     DB_PASSWORD=your-password" -ForegroundColor Gray
    Write-Host ""
    exit 0
}

Write-Host ""
Write-Host "Step 1: Testing PostgreSQL connection..." -ForegroundColor Cyan
php backend/test_pdo.php

if ($LASTEXITCODE -ne 0) {
    Write-Host ""
    Write-Host "❌ Connection test failed!" -ForegroundColor Red
    Write-Host "Please check your Supabase credentials in .env" -ForegroundColor Yellow
    exit 1
}

Write-Host ""
Write-Host "Step 2: Initializing PostgreSQL schema..." -ForegroundColor Cyan
php backend/init_db.php

if ($LASTEXITCODE -ne 0) {
    Write-Host ""
    Write-Host "❌ Schema initialization failed!" -ForegroundColor Red
    exit 1
}

Write-Host ""
# Check if SQLite database exists
if (Test-Path "backend/data/database.sqlite") {
    Write-Host "SQLite database found!" -ForegroundColor Green
    $migrate = Read-Host "Do you want to migrate existing data to PostgreSQL? (y/n)"
    
    if ($migrate -eq "y") {
        Write-Host ""
        Write-Host "Step 3: Migrating data from SQLite..." -ForegroundColor Cyan
        php backend/migrate_to_postgres.php
    } else {
        Write-Host ""
        Write-Host "⚠ Skipping data migration" -ForegroundColor Yellow
    }
} else {
    Write-Host "No SQLite database found - starting fresh" -ForegroundColor Yellow
}

Write-Host ""
Write-Host "============================================" -ForegroundColor Cyan
Write-Host "Migration Complete! 🎉" -ForegroundColor Green
Write-Host "============================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Next steps:" -ForegroundColor White
Write-Host "  1. Verify your data in Supabase Dashboard" -ForegroundColor Gray
Write-Host "  2. Start dev server: php -S localhost:8000 router.php" -ForegroundColor Gray
Write-Host "  3. Login with: admin@hospital.com / admin123" -ForegroundColor Gray
Write-Host ""
Write-Host "Documentation: See MIGRATION_GUIDE.md for details" -ForegroundColor Cyan
Write-Host ""
