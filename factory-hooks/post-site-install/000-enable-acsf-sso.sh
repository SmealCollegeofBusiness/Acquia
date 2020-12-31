#!/bin/bash
#
# Factory Hook: post-site-install
#
# This is necessary so that blt drupal:install tasks are invoked automatically
# when a site is created on ACSF.
#
# Usage: post-site-install.sh sitegroup env db-role domain

# Exit immediately on error and enable verbose log output.
set -ev

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

# Enable ACSF modules.
$drush en -y --uri=$DOMAIN acsf acsf_duplication acsf_sso
