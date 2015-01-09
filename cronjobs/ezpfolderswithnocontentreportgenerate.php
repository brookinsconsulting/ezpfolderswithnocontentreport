<?php
/**
 * File containing the ezpfolderswithnocontentreportgenerate.php cronjob.
 *
 * @copyright Copyright (C) 1999 - 2015 Brookins Consulting. All rights reserved.
 * @copyright Copyright (C) 2013 - 2015 Think Creative. All rights reserved.
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License v2 (or later)
 * @version 0.1.1
 * @package ezpfolderswithnocontentreport
 */

$ini = eZINI::instance( 'ezpfolderswithnocontentreport.ini' );
$siteHostname = $ini->variable( 'SiteSettings', 'SiteHostname' );
$reportStoragePath = $ini->variable( 'SiteSettings', 'ReportStoragePath' );

// General cronjob part options
$phpBin = '/usr/bin/php';
$generatorWorkerScript = 'extension/ezpfolderswithnocontentreport/bin/php/ezpfolderswithnocontentreport.php';
$options = '--storage-dir=' . $reportStoragePath . ' --hostname=' . $siteHostname;
$result = false;

passthru( "$phpBin ./$generatorWorkerScript $options;", $result );

print_r( $result ); echo "\n";

?>