<?php

/**
 * @file
 * Git pull functions for automated deploys for Drupal projects.
 *
 * This script contains the main functions to pull in new branch changes from git
 * which can be triggered by a post commit service hook from Bitbucket or Github.
 */

/**
 * Function for the git commands.
 */
function gitpull_deploy($cnf = array()) {
  $output = array();

  // Start the process
  chdir(DEPLOY_DOCROOT);
  $output[] = '## Git Process Starting ##';
  $output[] = 'Docroot: '. shell_exec('pwd -P');
  $output[] = 'User: '. shell_exec('whoami'); // Log the script user.

  $output[] = '# git status #';
  exec(GIT_PATH .' status', $op); // Log current status.
  if ($cnf['debug']) $output[] = $op;

  if ($cnf['git_clean']) {
    $output[] = '# git clean -df #';
    $output[] = exec(GIT_PATH .' clean -df 2>&1', $op); // Remove untracked files.
  }

  if ($cnf['git_reset']) {
    $output[] = '# git reset --hard HEAD #';
    $output[] = exec(GIT_PATH .' reset --hard HEAD 2>&1', $op); // Reset any modified files.
  }

  $output[] = '# git pull --rebase #';
  $output[] = exec(GIT_PATH .' pull --rebase '. $cnf['git_remote'] .' '. $cnf['git_branch'] .' 2>&1', $op);

  $output[] = '# git submodule sync #';
  $output[] = exec('git submodule sync 2>&1', $op);

  $output[] = '# git submodule update #';
  $output[] = exec(GIT_PATH .' submodule update 2>&1', $op);

  $output[] = '# git submodule status #';
  $output[] = exec(GIT_PATH .' submodule status 2>&1', $op);

  $output[] = '## COMMAND chmod -R o-rx .git ##';
  exec('chmod -R o-rx .git 2>&1', $op); // Remove read permissions for others.

  // Drush options
  if ($cnf['drush_fra']) {
    $output[] = '# drush fra -y '. $cnf['drush_site'] .' #';
    $output[] = exec($cnf['drush_path'] .' fra -y '. $cnf['drush_site'], $op);
  }

  $output[] = '# drush updb -y --uri='. $cnf['drush_uri'] .' #';
  $output[] = exec($cnf['drush_path'] .' updb -y --uri='. $cnf['drush_uri'], $op);

  $output[] = '## Git Process Complete ##';

  // Parse $output and log it.
  // To Do: Shove this into a helper function!
  foreach($output AS $line) {
    if(is_array($line)) {
      foreach($line AS $subline) {
        $line = trim($subline);
        if (!empty($line)) {
          _deploy_log($line);
        }
      }
    }
    else {
      $line = trim($line);
    }
    if (!empty($line)) {
      _deploy_log($line);
    }
  }

  return TRUE;
}

/**
 * Git specific requirements check.
 */
function gitpull_deploy_requirements() {
  // placeholder for gitpull specific requirements.
}

/**
 * Confirm the payload.
 *
 * @param $payload
 *   Json Payload returned by the git service.
 * @param $debug
 *   Debug option for extra logging.
 */
function _gitpull_deploy_payload_decode($payload, $debug) {
  if(empty($payload)) { // Check the payload.
    _deploy_log('Insufficient Payload Data!', 'crit');
    return FALSE;
  }
  else {
    $payload_json = json_decode($payload); // Decode the payload json.
    if ($debug) {
      _deploy_log('JSON Payload: '. $payload, 'debug', FALSE);
    }
    return $payload_json;
  }
}

/**
 * Pull the branch name from the payload.
 *
 * @param $payload
 *   Json converted to an array returned by the git service.
 */
function _gitpull_deploy_payload_branch($payload) {
  // Detect the repo service.
  if ($payload->commits[0]->branch) { // Bitbucket
    $branch = $payload->commits[0]->branch;
    return $branch;
  }
  elseif ($payload->ref) { // Github
    $branch = array_pop(explode($payload->ref));
    return $branch;
  }
  else {  // Not a known Git service.
    _deploy_log('Unrecognized Git Service!', 'crit');
    return FALSE;
  }
}

