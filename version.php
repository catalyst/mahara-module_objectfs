<?php
/**
 * Version information.
 *
 * @package    mahara
 * @subpackage module.objectfs
 * @author     Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('INTERNAL') || die();

$config = new stdClass();
$config->version   = 2022050300;      // The current plugin version (Date: YYYYMMDDXX).
$config->release   = '2.0.0';

$config->dependencies = array(
    'module_aws' => 2017030100
);
