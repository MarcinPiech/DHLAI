#!/bin/bash

echo "🚀 Tworzę strukturę projektu APM Automation..."

# Katalogi
mkdir -p config
mkdir -p database/migrations
mkdir -p src/Controllers
mkdir -p src/Models
mkdir -p src/Services
mkdir -p src/Views/emails
mkdir -p src/Views/dashboard
mkdir -p public/assets/css
mkdir -p public/assets/js
mkdir -p storage/logs
mkdir -p storage/backups
mkdir -p storage/temp
mkdir -p public/uploads
mkdir -p scripts
mkdir -p tests

# Gitkeep
touch storage/logs/.gitkeep
touch storage/backups/.gitkeep
touch storage/temp/.gitkeep
touch public/uploads/.gitkeep

echo "✅ Struktura katalogów utworzona!"
ls -la

