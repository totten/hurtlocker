<?php

require_once 'hurtlocker.civix.php';
// phpcs:disable
use CRM_Hurtlocker_ExtensionUtil as E;
use Hurtlocker\DaoDatabaseAdapter;
use Hurtlocker\PdoDatabaseAdapter;
// phpcs:enable

/**
 * @param string $dbType
 *   Ex: 'pdo', 'dao'
 * @param string $workerSeries
 *   List of workers. For example, suppose you want 3 workers:
 *     - Worker #1: write to table A then B then C then D ('abcd')
 *     - Worker #2: write to table B then C then D then A ('bacd')
 *     - Worker #3: write to table D then C then B then A ('dcba')
 *   This is condensed into a string: 'abcd-bcda-dcba'
 * @return \Hurtlocker\Hurtlocker
 */
function hurtlocker(string $dbType, string $workerSeries): \Hurtlocker\Hurtlocker {
  switch (strtolower($dbType)) {
    case 'dao':
      $db = new DaoDatabaseAdapter();
      break;

    case 'pdo':
      $db = new PdoDatabaseAdapter();
      break;

    default:
      throw new \RuntimeException("Unrecognized dbType: $dbType");
  }

  return new \Hurtlocker\Hurtlocker($db, $workerSeries);
}

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 */
function hurtlocker_civicrm_config(&$config) {
  _hurtlocker_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function hurtlocker_civicrm_install() {
  _hurtlocker_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_postInstall().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_postInstall
 */
function hurtlocker_civicrm_postInstall() {
  _hurtlocker_civix_civicrm_postInstall();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_uninstall
 */
function hurtlocker_civicrm_uninstall() {
  _hurtlocker_civix_civicrm_uninstall();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function hurtlocker_civicrm_enable() {
  _hurtlocker_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_disable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_disable
 */
function hurtlocker_civicrm_disable() {
  _hurtlocker_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_upgrade
 */
function hurtlocker_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _hurtlocker_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_entityTypes().
 *
 * Declare entity types provided by this module.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_entityTypes
 */
function hurtlocker_civicrm_entityTypes(&$entityTypes) {
  _hurtlocker_civix_civicrm_entityTypes($entityTypes);
}

// --- Functions below this ship commented out. Uncomment as required. ---

/**
 * Implements hook_civicrm_preProcess().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_preProcess
 */
//function hurtlocker_civicrm_preProcess($formName, &$form) {
//
//}

/**
 * Implements hook_civicrm_navigationMenu().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_navigationMenu
 */
//function hurtlocker_civicrm_navigationMenu(&$menu) {
//  _hurtlocker_civix_insert_navigation_menu($menu, 'Mailings', [
//    'label' => E::ts('New subliminal message'),
//    'name' => 'mailing_subliminal_message',
//    'url' => 'civicrm/mailing/subliminal',
//    'permission' => 'access CiviMail',
//    'operator' => 'OR',
//    'separator' => 0,
//  ]);
//  _hurtlocker_civix_navigationMenu($menu);
//}
