@echo off
chcp 65001 >nul
cls
echo ========================================================
echo   INSTALLATION AUTOMATIQUE TV+AOS - Laravel 13
echo ========================================================
echo.
cd /d "%~dp0"

:: 1. Vérifie PHP & Composer
echo [1/7] Verification prerequis...
where php >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
  echo [ERREUR] PHP non trouve. Installe PHP 8.3+ et relancez.
  pause
  exit /b 1
)
where composer >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
  echo [ERREUR] Composer non trouve. Installe Composer et relancez.
  pause
  exit /b 1
)
php -v | findstr /i "8.3 8.4 8.5" >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
  echo [ATTENTION] Version PHP recommandee ^>= 8.3.0. Version actuelle detectee:
  php -v | head -n 1
  echo.
)
echo  ... OK.

:: 2. Composer vendor
echo.
echo [2/7] Installation dependances PHP (Composer vendor)...
set COMPOSER_CACHE_DIR=%cd%\.composer-cache
composer install --no-interaction --prefer-dist --ignore-platform-req=ext-gd --ignore-platform-req=ext-exif --ignore-platform-req=ext-intl --ignore-platform-req=ext-fileinfo --no-audit
if %ERRORLEVEL% NEQ 0 (
  echo [AVERTISSEMENT] Erreur install composer. Essayez "composer update"
  pause
)

:: 3. Copie .env.example vers .env si n'existe pas
echo.
echo [3/7] Initialisation fichier .env et clef application...
if not exist .env (
  copy .env.example .env /Y
  echo  .env cree depuis .env.example
)
php artisan key:generate --force
php artisan package:discover --ansi

:: 4. Crée base SQLite
echo.
echo [4/7] Base de donnees SQLite...
if not exist database\database.sqlite (
  echo. > database\database.sqlite
  echo  database/database.sqlite cree.
)

:: 5. Migrations + Seeders
echo.
echo [5/7] Migration des tables + Seeder jeux de donnees demo...
php artisan migrate --force
php artisan db:seed --force

:: 6. Node_modules
echo.
echo [6/7] Installation dependances JavaScript (NPM)...
call npm install
call npm run build

:: 7. Rapport
echo.
echo ========================================================
echo   INSTALLATION TERMINEE ^!
echo ========================================================
echo.
echo   Lancer 2 terminaux separes :
echo   [Terminal 1] - npm run dev          (Assets Vite - HMR)
echo   [Terminal 2] - php artisan serve    (Serveur HTTP)
echo.
echo   Puis ouvrir:  http://localhost:8000/login
echo.
echo   Pour les WebSockets temps reel (optionnel):
echo   [Terminal 3] - php artisan reverb:start
echo.
pause
