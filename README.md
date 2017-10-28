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

## How to create copy only for admin
1. Copy project directory with another name (`speech-and-debate` to `speech-and-debate-old`)
2. Go to new directory -> `app/config` -> change or add to `system.ini` next row: `copy_mode = "on"`
3. Change `database_mysql.ini` db to new database name (`debate_old`)
4. Create copy of old nginx configuration (located at `/etc/nginx/sites-enabled/`) with new name
5. Change in new nginx configuration `root` and `server_name` path
6. Reload nginx (`/etc/init.d/nginx reload`)
7. Dont forget copy your database to old database

## How to copy your current database to another database
1. On current engine directory go to `commander`
2. Execute command: `php run.php db:migrate ANOTHER_DATABASE_NAME`
