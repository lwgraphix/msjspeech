Speech and Debate
=
## Requirements:
1. PHP 7.0 or above
2. Nginx
3. MySQL

## Required PHP modules:
1. mysql
2. curl
3. mbstring
4. zip
5. apcu
6. xml

## Installation:
1. Install required PHP modules
    * Set `post_max_size` and `upload_max_filesize` to 256M in php.ini
2. Clone this repository
3. Create directory `_cache` and `uploads` and execute `chmod -R 777 _cache uploads`
4. Execute `composer update` in every folder that contains `composer.json` (this, commander, migrations)
5. Add cron task `0 * * * * /usr/bin/php [ABSOLUTE_PATH_TO_DIR]/commander/run.php process:partner_requests`
6. Create new database in MySQL with any name
7. Go to `app/config`, remove `example` filename postfix and change options regarding your database and needed application options
8. Do it same with `migrations/phinx.yml`
9. Go to `migrations` and execute `vendor/bin/phinx migrate` to initialize database structure
10. Execute SQL query `SET GLOBAL sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''));` in database.
11. Go to website url and log in. User: `admin@admin.com`, password: `admin`
12. Change all system settings
13. Load all your email templates 
14. You are welcome!