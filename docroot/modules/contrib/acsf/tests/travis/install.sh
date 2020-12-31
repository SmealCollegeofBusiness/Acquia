#!/usr/bin/env bash

# NAME
#     install.sh - Install Travis CI dependencies
#
# SYNOPSIS
#     install.sh
#
# DESCRIPTION
#     Creates the test fixture. Hints for ACSF devs on why this is necessary;
#     https://github.com/acquia/orca/blob/develop/docs/faq.md

cd "$(dirname "$0")"

# Reuse ORCA's own includes.
source ../../../orca/bin/travis/_includes.sh

# Exit early in the absence of a fixture.
[[ -d "$ORCA_FIXTURE_DIR" ]] || exit 0

composer -d"$ORCA_FIXTURE_DIR" require mockery/mockery:^1.2
