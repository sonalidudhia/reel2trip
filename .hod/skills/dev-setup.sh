#!/bin/bash
[ ! -d "vendor" ] && composer install
[ ! -d "node_modules" ] && npm install
[ ! -f ".env" ] && cp .env.example .env && php artisan key:generate
echo "✅ Ready"
