Acquia Cloud Site Factory Connector
===================================

[Acquia Cloud Site Factory](http://www.acquia.com/products-services/acquia-cloud-site-factory)
provides an environment and a robust set of tools that simplify management of
many Drupal sites, allowing you to quickly deliver and manage any number of
websites.

This set of modules provides the necessary integrations for a Drupal site to
function on the Acquia Cloud Site Factory platform.

This text provides some technical background on assorted topics. More user
facing documentation is available at https://docs.acquia.com/site-factory/module/.


Dependencies / standalone and interconnected modules
----------------------------------------------------

Some modules are optional and can be enabled/disabled at will:

* acsf_sso, which provides the necessary functionality for login from the
  ACSF Management Console to work. (This is not a required module because some
  customers prefer forcing login through their own SSO portals and ignoring
  login from the Management Console.) Notes:
  - The module has its own dependency modules/libraries which the main acsf
    module does not depend on, so those likely need to be explicitly installed
    using e.g. 'composer require drupal/acsf_sso', even when the acsf_sso
    module itself is already available.
  - When this module is disabled, there will still be a "Log in" button for
    sites in the Management Console; it just won't be functional.
* acsf_sj, which provides integration with Scheduled Jobs functionality which
  is/may be available only after communication with your Acquia account manager.

The other modules contain interconnected ACSF functionality which is required
to all be enabled, even though it has historically been divided over various
submodules. The acsf module depends on these required submodules for easier /
safer enabling of all of them. This however also means:
- These submodules must not be uninstalled from any functional Drupal site
  that runs on the ACSF platform.
- When disabling the full ACSF suite on a site (which does not happen often),
  various modules will need to be uninstalled together.

These submodules which the main acsf module depends on are:

* acsf_duplication (providing the necessary scrubbing mechanisms on copies of
  a site's data that was duplicated in the ACSF Management Console)
* acsf_theme (containing functionality that likely has never been used on
  Drupal 8 and may therefore be deprecated soon)
* acsf_variables (which will likely be deprecated as the storage it provides
  will be replaced by a more Drupal 8 native mechanism)

This group of modules shares a single src/ directory (referenced in the main
composer.json) containing all of the classes - with the exception of those
classes which are mentioned in submodules' various yml files.


Running acsf-init
-----------------

Your sites' codebase must be modified in order for the sites to function
properly on the Acquia Cloud Site Factory platform. (The most basic purpose of
this is to take over your codebase's sites.php file in order to point to the
correct site database & settings.php during early bootstrap; other purposes
include hooking into the staging / duplication process to modify data /
configuration in the target site.)

The command to do this is
```
drush --include=<PATH-TO-MODULE>/acsf_init acsf-init
```
Specify the -y flag to overwrite all acsf files in your codebase without
prompting. If you're running BLT, you may not need to run this command directly
but e.g. 'blt recipes:acsf:init:all' will take care of this.

Note that acsf_init is *not* a Drupal module that can be enabled; the acsf-init
command is a completely standalone script. This is intentional: the command
needs to be able to modify a site's codebase without any site databases being
present, or e.g. a Drupal codebase that's broken. This is why the --include
option is necessary to point to the directory containing this standalone drush
command.

### Verifying your codebase is correct

The acsf-init command needs to be re-run after every update of the acsf module,
to ensure the latest versions of the appropriate files are present in your
codebase. The command to verify that those files are present is:
```
drush --include=<PATH-TO-MODULE>/acsf_init acsf-init-verify
```
Acquia Cloud Site Factory will deny deploying any code which this command fails
to successfully verify.


Dev branches in the drupal.org repository
-----------------------------------------

The acsf module has development model that is different from regular Drupal
modules: releases are tested internally by Acquia and changes are always
released in cadence with new releases of the Acquia Cloud Site Factory platform,
to ensure compatibility. This means no development releases of the module are
made available in the way most Drupal modules have them.

Development snapshot are available in order for composer to be able to install
them from drupal.org, which is required by Acquia's internal quality control
policies. (These are pushed to branches named like "8.x-2.x" and technically
are available as 'dev releases', as mandated by packages.drupal.org.) While
everyone is in principle free to inspect / test those, please do not install
them on sites. They are not guaranteed to function on any already-released
version of the Acquia Cloud Site Factory platform and Acquia makes no guarantee
that any code contained in them will end up in the next released version of the
module.

These testing/composer requirements and the discrepancy of acsf vs. regular
drupal.org assumptions about module development, have the unfortunate result of
the 'development' branches being completely separate from regular release
branches (because force-pushing to them is prohibited by drupal.org).

The regular downloadable releases are available from branches named like
"8.x-2.x-release".
