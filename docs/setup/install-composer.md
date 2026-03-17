# Installing Composer

Composer is the dependency manager for PHP.  
Follow these steps to install it on your system.

---

## 1. Check PHP Installation
Make sure PHP is installed and available in your terminal:


php -v

## 2. Download Composer Installer
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"

## 3. Verify Installer
php -r "if (hash_file('sha384', 'composer-setup.php') === file_get_contents('https://composer.github.io/installer.sig')) { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;"

## 4. Install Composer Globally
php composer-setup.php --install-dir=/usr/local/bin --filename=composer

## 5. Test Installation
composer --version

# Notes

On Windows, you can use the Composer Windows Installer.

```bash

https://getcomposer.org/download/