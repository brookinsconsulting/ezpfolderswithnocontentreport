<?php
/**
 * File containing the folderswithnocontentreport module configuration file, module.php
 *
 * @copyright Copyright (C) 1999 - 2015 Brookins Consulting. All rights reserved.
 * @copyright Copyright (C) 2013 - 2015 Think Creative. All rights reserved.
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License v2 (or later)
 * @version 0.1.1
 * @package ezpfolderswithnocontentreport
*/

// Define module name
$Module = array('name' => 'Folder Report');

// Define module view and parameters
$ViewList = array();

// Define 'report' module view parameters
$ViewList['report'] = array( 'script' => 'report.php',
                             'functions' => array( 'report' ),
                             'default_navigation_part' => 'ezfolderswithnocontentreportnavigationpart',
                             'post_actions' => array( 'Download', 'Generate' ),
                             'params' => array() );

// Define function parameters
$FunctionList = array();

// Define function 'report' parameters
$FunctionList['report'] = array();

?>