<?php
/**
 * File containing the folderswithnocontentreport/report module view.
 *
 * @copyright Copyright (C) 1999 - 2015 Brookins Consulting. All rights reserved.
 * @copyright Copyright (C) 2013 - 2015 Think Creative. All rights reserved.
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License v2 (or later)
 * @version 0.1.1
 * @package ezpfolderswithnocontentreport
 */

/**
 * Disable memory and time limit
 */
set_time_limit( 0 );
ini_set("memory_limit", -1);

/**
 * Default module parameters
 */
$module = $Params["Module"];

/**
* Default class instances
*/

// Parse HTTP POST variables
$http = eZHTTPTool::instance();

// Access system variables
$sys = eZSys::instance();

// Init template behaviors
$tpl = eZTemplate::factory();

// Access ini variables
$ini = eZINI::instance();
$iniFoldersWithNoContentReport = eZINI::instance( 'ezpfolderswithnocontentreport.ini' );

// Report file variables
$dir = $iniFoldersWithNoContentReport->variable( 'SiteSettings', 'ReportStoragePath' );
$file = $dir . '/ezpfolderswithnocontentreport.csv';

/**
 * Handle download action
 */
if ( $http->hasPostVariable( 'Download' ) )
{
    if ( !eZFile::download( $file, true, 'ezpfolderswithnocontentreport.csv' ) )
       $module->redirectTo( 'literalreport/report' );
}

/**
 * Handle generate actions
 */
if ( $http->hasPostVariable( 'Generate' ) )
{
    $siteHostname = $iniFoldersWithNoContentReport->variable( 'SiteSettings', 'SiteHostname' );
    $reportStoragePath = $iniFoldersWithNoContentReport->variable( 'SiteSettings', 'ReportStoragePath' );

    // General script options
    $phpBin = '/usr/bin/php';
    $generatorWorkerScript = 'extension/ezpfolderswithnocontentreport/bin/php/ezpfolderswithnocontentreport.php';
    $options = '--storage-dir=' . $reportStoragePath . ' --hostname=' . $siteHostname;
    $result = false;
    $output = false;

    exec( "$phpBin ./$generatorWorkerScript $options;", $output, $result );
}

/**
 * Test for generated report
 */
if ( file_exists( $file ) )
{
    $tpl->setVariable( 'fileModificationTimestamp', date("F d Y H:i:s", filemtime( $file ) ) );
    $tpl->setVariable( 'status', true );
}
else
{
    $tpl->setVariable( 'status', false );
}


/**
 * Default template include
 */
$Result = array();
$Result['content'] = $tpl->fetch( "design:folderswithnocontentreport/report.tpl" );
$Result['path'] = array( array( 'url' => false,
                                'text' => ezpI18n::tr('design/standard/folderswithnocontentreport', 'Literal Report') ),
                         array( 'url' => false,
                                'text' => ezpI18n::tr('design/standard/folderswithnocontentreport', 'Report') )
                        );

$Result['left_menu'] = 'design:folderswithnocontentreport/menu.tpl';

?>