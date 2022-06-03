#!/usr/bin/env bash

set -ev

cd "$(dirname "$0")" || exit; source _includes.sh

DRUPAL_CORE=9;
if [[ "$ORCA_JOB" == "INTEGRATED_TEST_ON_OLDEST_SUPPORTED" || "$ORCA_JOB" == "INTEGRATED_TEST_ON_LATEST_LTS" || $DEPLOY || $DO_DEV ]]; then
  DRUPAL_CORE=8;
fi

if [[ "$ORCA_JOB" == "STATIC_CODE_ANALYSIS" ]]; then
  rm -Rf "$ORCA_SUT_DIR/modules/acquia_contenthub_curation/ember"
fi
if [[ "$ORCA_JOB" == "STATIC_CODE_ANALYSIS" && "$ACQUIACI" == TRUE ]]; then
  rm -Rf "/ramfs$ORCA_SUT_DIR/modules/acquia_contenthub_curation/ember"
fi
