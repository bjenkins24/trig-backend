# Docker start
* `docker-compose up`
* Docker is really slow on a mac. This will help, but it will still be slow: [Setting up NSF on Mac](https://blog.fingo.pl/speed-up-docker-using-nfs-volumes/). The docker-compose volumes are set up already just gotta enable it on your mac.

# Get Up and Running
If you want to avoid docker locally you can do all of this:
* `brew update`
* `brew install mysql && brew services start mysql`
* `brew install php@7.4`
* `brew install redis && ln -sfv /usr/local/opt/redis/*.plist ~/Library/LaunchAgents && launchctl load ~/Library/LaunchAgents/homebrew.mxcl.redis.plist`
* `brew tap elastic/tap && brew install elastic/tap/elasticsearch-full` run elastic search locally with `elasticsearch` then access it at `localhost:9200`
* `brew install elastic/tap/kibana-full` (it hung on downloading for around 45 minutes for me but finally finished) then run kibana (make sure elasticsearch is running first) with `kibana` it can be accessed at `localhost:5601`
* `brew install maven` - For installing apache tika
* `brew install tesseract` - For use with apache tika (required for OCR)
* `brew install jpegoptim && brew install optipng && brew install pngquant && brew install svgo && brew install gifsicle && brew install webp` - For optimizing images
## Composer
* Download: https://getcomposer.org/download/ 
* `mv composer.phar /usr/local/bin/composer`
* Add to paths: `sudo vi /etc/paths` add `/Users/brian/.composer/vendor/bin`

## Apache Tika
Must be installed to pull data from documents (for searching)
`git clone https://github.com/apache/tika.git`
`cd tika`
`mvn install` (this took one hour and four minutes literally)
`cd ./tika-server/target/`
`java -jar tika-server-2.0.0-SNAPSHOT.jar` - starts apache tika on `localhost:9998`

## .env
* Copy `.env.development` and rename it to `.env`.
* Change `APP_URL` in .env `backend.test` to `folderName.test`, where folderName is the name of the folder that this repo appears in locally.
* Create a MYSQL dev and test DB
  * `mysql -u root`
  * `CREATE DATABASE trig;`
* You'll need a mailtrap.io account. MAIL_USERNAME and MAIL_PASSWORD must be replaced with your _inbox_ mailtrap credentials, _not_ the credentials you use to log in. Go to your inbox and you'll find what you need.

# Testing
* `sqlite3 trig_test.db` - then run `.database` - `.quit` to close. That will create the test sqlite database.

## PHP Extensions
* `sudo pecl install redis` 
  * If you used homebrew to install php, it probably put something wrong in your php.ini file. 
`vi /usr/local/etc/php/7.4/php.ini` - Change the first line `extension=redis.so` to something
like `extension="/usr/local/Cellar/php@7.4/7.4.26_1/pecl/20190902/redis.so"` when redis installed it said
the full path. Use that. Make sure you restart your terminal too.

* `pecl install xdebug`
  * You may need to mess with your php.ini file too `php --ini`. It should look something like thise
`zend_extension="/usr/local/Cellar/php/7.4.8/pecl/20190902/xdebug.so"` with the full path

## Laravel Valet
* `composer global require laravel/valet`
* `valet install`
* Navigate to the folder you cloned this repo in and run `valet park && valet secure`

## Bootstrapping
* I'm not sure why, but I think you have to run `php artisan queue:work` at least once before the redis queue will work locally
* `npm run bootstrap` - Bootstrap everything below. Only run this on a new machine, not any of the 
commands below. But for reference this is what it does:

  * `composer install`
  * `php artisan storage:link` - will make `/storage/` accessible. Needed for images to work locally
  * `php artisan horizon:install` 
  * `npm run scaffold` - Gets oauth stuff - and runs all migrations fresh

## Performance on PROD
* `php artisan config:cache` - https://laravel.com/docs/7.x/configuration#configuration-caching

# DB Design
`cards`
(id, user_id, card_type_id, link, title, description, image, actual_created_at actual_updated_at created_at modified_at)

`card_favorites`
(id, card_id, user_id)

`card_types` 
(id, name) - file, link, etc

`card_duplicates`
(id, primary_card_id, duplicate_card_id)

`decks`
(id, user_id, title, description, image, link)

`deck_cards`
(id, deck_id, card_id)

`deck_followers`
(id, deck_id, user_id)

`workspaces`
(id, name)

`workspaces_users`
(id, user_id, workspace_id)

`roles` - **admin**, **manager**, or **member** 
(id, name)

`permissions`
nullable permissionable-Type and permissionable_id
(id, permissionable_type, permissionable_id, permission_capability_id)

`capabilities` - **writer** or **reader** 
(id, name)

`permission_types`
id typeable_types typeable_id

card or deck can only morph one of these not multiple like permissions
Link share settings are only for the "link_share_type" type of sharing. 
If you need to share with an individual or group like (workspace/team/user/person)
that will be under "Permissions"
`link_share_settings`
id shareable_id shareable_type link_share_type_id capability_id 

`link_share_type`
id name  **anyoneWithLink** **public**
anyoneWithLink means anyone with the link can access it
public means anyone can access it and it will be indexed by search engines (discoverable)

`people` - this will be unused but we need to save if in the future we allow for sharing with individuals
id email

`teams` 
(id, workspace_id, name)

`teams_users`
(id, team_id, user_id)

`users`
(id, role_id, first_name, last_name, email)

`views`
(id, viewable_type, viewable_id, created_at)



