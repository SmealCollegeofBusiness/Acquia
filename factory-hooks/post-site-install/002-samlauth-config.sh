#!/usr/bin/env bash
#
# Factory hook: post-site-install
#
# Add samlauth config after site install

SITEGROUP="$1"
ENVIRONMENT="$2"
DB_ROLE="$3"
DOMAIN="$4"

echo "sitegroup: $SITEGROUP"
echo "env: $ENVIRONMENT"
echo "db role: $DB_ROLE"
echo "domain: $DOMAIN"

# Drush executable:
drush="/mnt/www/html/$SITEGROUP.$ENVIRONMENT/vendor/bin/drush"

# Create and set Drush cache to unique local temporary storage per site.
# This approach isolates drush processes to completely avoid race conditions
# that persist after initial attempts at addressing in BLT: https://github.com/acquia/blt/pull/2922
cache_dir=`/usr/bin/env php /mnt/www/html/$SITEGROUP.$ENVIRONMENT/vendor/acquia/blt/scripts/blt/drush/cache.php $SITEGROUP $ENVIRONMENT $DOMAIN`

echo "Generated temporary Drush cache directory: $cache_dir."

echo "Configuring samlauth on $DOMAIN domain in $ENVIRONMENT environment on the $SITEGROUP subscription."

# Set samlauth configuration after install.
DRUSH_PATHS_CACHE_DIRECTORY="$cache_dir" $drush -l $DOMAIN config:set -y samlauth.authentication map_users_name 'true'
DRUSH_PATHS_CACHE_DIRECTORY="$cache_dir" $drush -l $DOMAIN config:set -y samlauth.authentication map_users_mail 'true'
result=$?


# Clean up the drush cache directory.
echo "Removing temporary drush cache files."
rm -rf "$cache_dir"

set +v

# Site Factory will send a notification of a partially failed install and will
# stop executing any further post-site-install hook scripts that would be in
# this directory (and get executed in alphabetical order).
exit $result
