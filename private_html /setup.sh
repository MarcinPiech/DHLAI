#!/bin/bash

echo "🚀 APM Automation - Instalacja"
echo "================================"

# Sprawdź PHP
if ! command -v php &> /dev/null; then
	echo "❌ PHP nie jest zainstalowane"
	exit 1
fi

echo "✓ PHP $(php -v | head -n 1)"

# Sprawdź Composer
if ! command -v composer &> /dev/null; then
	echo "❌ Composer nie jest zainstalowany"
	echo "Zainstaluj: https://getcomposer.org/download/"
	exit 1
fi

echo "✓ Composer zainstalowany"

# Instaluj zależności
echo ""
echo "📦 Instalowanie zależności PHP..."
composer install

# Twórz katalogi
echo ""
echo "📁 Tworzenie katalogów..."
mkdir -p storage/logs
mkdir -p storage/backups
mkdir -p storage/temp
mkdir -p public/uploads

chmod 755 storage
chmod 755 storage/logs
chmod 755 storage/backups
chmod 755 storage/temp
chmod 755 public/uploads

echo "✓ Katalogi utworzone"

# Kopiuj .env
if [ ! -f .env ]; then
	echo ""
	echo "📝 Tworzenie pliku .env..."
	cp .env.example .env
	echo "✓ Plik .env utworzony - UZUPEŁNIJ DANE!"
fi

# Baza danych
echo ""
echo "🗄️  Konfiguracja bazy danych"
read -p "Nazwa bazy danych [apm_automation]: " DB_NAME
DB_NAME=${DB_NAME:-apm_automation}

read -p "Użytkownik MySQL [root]: " DB_USER
DB_USER=${DB_USER:-root}

read -sp "Hasło MySQL: " DB_PASS
echo ""

read -p "Host MySQL [localhost]: " DB_HOST
DB_HOST=${DB_HOST:-localhost}

# Aktualizuj .env
sed -i "s/DB_DATABASE=.*/DB_DATABASE=$DB_NAME/" .env
sed -i "s/DB_USERNAME=.*/DB_USERNAME=$DB_USER/" .env
sed -i "s/DB_PASSWORD=.*/DB_PASSWORD=$DB_PASS/" .env
sed -i "s/DB_HOST=.*/DB_HOST=$DB_HOST/" .env

# Utwórz bazę danych
echo ""
echo "📊 Tworzenie bazy danych..."
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -e "CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

if [ $? -eq 0 ]; then
	echo "✓ Baza danych utworzona"
else
	echo "❌ Błąd tworzenia bazy danych"
	exit 1
fi

# Wykonaj migracje
echo ""
echo "🔧 Wykonywanie migracji..."
for migration in database/migrations/*.sql; do
	echo "  - $(basename $migration)"
	mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$migration"
done

echo "✓ Migracje wykonane"

# Konfiguracja SMTP
echo ""
echo "📧 Konfiguracja SMTP"
read -p "SMTP Host [smtp.gmail.com]: " MAIL_HOST
MAIL_HOST=${MAIL_HOST:-smtp.gmail.com}

read -p "SMTP Port [587]: " MAIL_PORT
MAIL_PORT=${MAIL_PORT:-587}

read -p "SMTP Username: " MAIL_USER

read -sp "SMTP Password: " MAIL_PASS
echo ""

sed -i "s/MAIL_HOST=.*/MAIL_HOST=$MAIL_HOST/" .env
sed -i "s/MAIL_PORT=.*/MAIL_PORT=$MAIL_PORT/" .env
sed -i "s/MAIL_USERNAME=.*/MAIL_USERNAME=$MAIL_USER/" .env
sed -i "s/MAIL_PASSWORD=.*/MAIL_PASSWORD=$MAIL_PASS/" .env

echo ""
echo "✅ Instalacja zakończona!"
echo ""
echo "Następne kroki:"
echo "1. Uzupełnij pozostałe dane w pliku .env"
echo "2. Skonfiguruj serwer WWW (Apache/Nginx) aby document root wskazywał na katalog 'public/'"
echo "3. Uruchom aplikację w przeglądarce"
echo ""
echo "📖 Dokumentacja: README.md"