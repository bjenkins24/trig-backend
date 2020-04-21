# Get Up and Running
Things you have to install:
* `brew update`
* `brew install mysql@8.0`
* `brew install php@7.4.3`
* `brew install redis@5.0.8`

## Composer
* Download: https://getcomposer.org/download/ 
* `mv composer.phar /usr/local/bin/composer`

## .env
* Copy `.env.development` and rename it to `.env`.
* Change `APP_URL` in .env `backend.test` to `folderName.test`, where folderName is the name of the folder that this repo appears in locally.
* Create a MYSQL dev and test DB
  * `mysql -u root`
  * `CREATE DATABASE trig;` `CREATE DATABASE trig_test`
* You may need a username/pass for sendGrid if emails don't work. `MAIL_USERNAME` and `MAIL_PASSWORD`. You can ask for it.

## PHP Extensions
* `pecl install redis` 
  * If you used homebrew to install php, it probably put something wrong in your php.ini file. 
`vi /usr/local/etc/php/7.4/php.ini` - Change the first line `extension=redis.so` to something
like `extension="/usr/local/Cellar/php/7.4.3/pecl/20190902/redis.so"` when redis installed it said
the full path. Use that. Make sure you restart your terminal too.

* `pecl install xdebug`
  * You may need to mess with your php.ini file too `php --ini`. It should look something like this:
`zend_extension="/usr/local/Cellar/php/7.4.3/pecl/20190902/xdebug.so"` with the full path

## Laravel Valet
* `composer global require laravel/valet`
* `valet install`
* Navigate to the folder you cloned this repo in and run `valet park`

## Bootstrapping
* `npm run bootstrap` - Bootstrap everything below. Only run this on a new machine, not any of the 
commands below. But for reference this is what it does:

  * `composer install`
  * `npm run fresh` - Gets oauth stuff - and runs all migrations fresh
  * `php artisan storage:link` - will make `/storage/` accessible. Needed for images to work locally
  * `php artisan horizon:install` 


# DB Design
`cards`
(id, user_id, card_type_id, title, description, image actual_created_at actual_modified_at created_at modified_at)

`card_documents`
(id, card_id, content)

`card_favorites`
(id, card_id, user_id)

`card_files`
(id, card_id, url)

`card_links`
(id, card_id, link)

`card_types` 
(id, name)

`decks`
(id, user_id, title, description, image)

`deck_cards`
(id, deck_id, card_id)

`deck_followers`
(id, deck_id, user_id)

`organizations`
(id, name)

`organizations_users`
(id, user_id, organization_id)

`permission_types` - **edit** or **view** 
(id, name)

`roles` - **admin**, **manager**, or **member** 
(id, name)

`permissions`
(id, permissionable_type, permissionable_id, link)

`permission_links`
(id, permission_type_id, permission_link_type_id)

`permission_link_types` - **has_link** or **public**
(id, name)

`permission_people` this will be unused but we need to save if in the future we allow for sharing with individuals
(id, permission_id, email)

`permission_teams`
(id, permission_id, team_id, permission_type_id)

`permission_users`
(id, permission_id, user_id, permission_type_id)

`teams` 
(id, organization_id, name)

`teams_users`
(id, team_id, user_id)

`users`
(id, role_id, first_name, last_name, email)

`views`
(id, viewable_type, viewable_id, created_at)
