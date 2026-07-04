# Heroku process types.
#
# web:     serves ORSEE via Apache with the document root set to orsee/,
#          using our custom vhost include (hardens install/, config/, tagsets/).
# release: runs once per deploy BEFORE the new web dyno goes live. It imports
#          the ORSEE schema on the very first deploy and is a no-op afterwards
#          (idempotent), so redeploys never touch existing data.
web: heroku-php-apache2 -C heroku/apache2.conf orsee/
release: php bin/release.php
