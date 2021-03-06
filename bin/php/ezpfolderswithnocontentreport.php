#!/usr/bin/env php
<?php
/**
 * File containing the ezpfolderswithnocontentreport.php bin script
 *
 * @copyright Copyright (C) 1999 - 2016 Brookins Consulting. All rights reserved.
 * @copyright Copyright (C) 2013 - 2016 Think Creative. All rights reserved.
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License v2 (or later)
 * @version 0.1.3
 * @package ezpfolderswithnocontentreport
 */

require 'autoload.php';

set_time_limit( 0 );

ini_set("memory_limit", -1);

/** Script startup and initialization **/

$cli = eZCLI::instance();
$script = eZScript::instance( array( 'description' => ( "eZ Publish Folders With No Content CSV Report Script\n" .
                                                        "\n" .
                                                        "ezpfolderswithnocontentreport.php --storage-dir=var/foldersWithNoContentCsvReport --hostname=www.example.com --exclude-node-ids=43,1999,2001" ),
                                     'use-session' => false,
                                     'use-modules' => true,
                                     'use-extensions' => true,
                                     'user' => true ) );

$script->startup();

$options = $script->getOptions( "[storage-dir:][hostname:][exclude-node-ids:]",
                                "[node]",
                                array( 'storage-dir' => 'Directory to place exported report file in',
                                       'hostname' => 'Website hostname to match url searches for',
                                       'exclude-node-ids' => 'Comma separted list of nodeID content tree nodeID paths to exclude from report. This parameter overrides defeault ini settings when used.' ),
                                false,
                                array( 'user' => true ) );
$script->initialize();


/** Access ini variables **/
$ini = eZINI::instance();
$iniFoldersWithNoContentReport = eZINI::instance( 'ezpfolderswithnocontentreport.ini' );

/** Script default values **/

$openedFPs = array();

$orphanedCsvReportFileName = 'ezpfolderswithnocontentreport';

$csvHeader = array( 'ContentObjectID', 'NodeID', 'AttributeID', 'Attribute Identifier', 'Attribute Name', 'Version', 'Content Empty', 'Visibility', 'Node Name', 'Node Url' );

$siteNodeUrlPrefix = $iniFoldersWithNoContentReport->variable( 'eZpFoldersWithNoContentReportSettings', 'NodeUrlProtocolPrefix' );

$excludeParentNodeIDs = $iniFoldersWithNoContentReport->variable( 'eZpFoldersWithNoContentReportSettings', 'ExcludedParentNodeIDs' );

$excludeObjectsWithAttributeShowChildrenChecked = $iniFoldersWithNoContentReport->variable( 'eZpFoldersWithNoContentReportSettings', 'ExcludeObjectsWithAttributeShowChildrenChecked' ) == 'enabled' ? true : false;

$excludeObjectsWithParentObjectPathTreeObjectsWithAttributeShowChildrenChecked = $iniFoldersWithNoContentReport->variable( 'eZpFoldersWithNoContentReportSettings', 'ExcludeObjectsWithParentObjectPathTreeObjectsWithAttributeShowChildrenChecked' ) == 'enabled' ? true : false;

/** Test for required script arguments **/

if ( $options['storage-dir'] )
{
    $storageDir = $options['storage-dir'];
}
else
{
    $storageDir = '';
}

if ( $options['hostname'] )
{
    $siteNodeUrlHostname = $options['hostname'];
}
else
{
    $cli->error( 'Hostname is required. Specify a website hostname for the site report url matching' );
    $script->shutdown( 2 );
}

if ( isset( $options['exclude-node-ids'] ) )
{
    $excludeParentNodeIDs = explode( ',', $options['exclude-node-ids'] );
}

/** Alert user of report generation process starting **/

$cli->output( "Searching through content for folders with no content ...\n" );

/** Fetch folder objects in content tree and check for no content in description **/

$db = eZDB::instance();
$query = 'SELECT DISTINCT ezcontentobject_attribute.contentobject_id, ezcontentobject_attribute.contentclassattribute_id, ezcontentclass_attribute.identifier, ezcontentclass_attribute.serialized_name_list, ezcontentobject_attribute.id, MAX( ezcontentobject_attribute.version ) as version FROM ezcontentobject_attribute,ezcontentclass_attribute WHERE ezcontentclass_attribute.contentclass_id = 1 AND ezcontentclass_attribute.id = \'156\' AND ezcontentobject_attribute.data_type_string = \'ezxmltext\' AND ( data_text like ""  OR data_text like \'<?xml version="1.0" encoding="utf-8"?>
<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/" xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/"/>
\' ) AND ezcontentclass_attribute.id = ezcontentobject_attribute.contentclassattribute_id GROUP BY ezcontentobject_attribute.id ORDER BY ezcontentobject_attribute.contentobject_id DESC, ezcontentobject_attribute.id DESC, version DESC';

// echo $query; die();

// $results = $db->arrayQuery( $query, array( 'limit' => 1 ) );
$results = $db->arrayQuery( $query );
$resultsCount = count( $results );

/*
if( is_array( $results ) and count( $results ) >= 1 )
{
print_r( $resultsCount ); echo "\n\n";
print_r( $results );
}
*/

/** Setup script iteration details **/

$script->setIterationData( '.', '.' );
$script->resetIteration( $resultsCount );

/** Open report file for writting **/

if ( !isset( $openedFPs[$orphanedCsvReportFileName] ) )
{
    $fileName = $storageDir . '/' . $orphanedCsvReportFileName . '.csv';

    if ( !file_exists( $storageDir ) )
    {
        mkdir( $storageDir, 0775);
    }

    $tempFP = @fopen( $fileName, "w" );

    if ( $tempFP )
    {
        $openedFPs[$orphanedCsvReportFileName] = $tempFP;
    }
    else
    {
        $cli->error( "Can not open output file for $fileName file" );
        $script->shutdown( 4 );
    }
}
else
{
   if ( !$openedFPs[$orphanedCsvReportFileName] )
   {
        $cli->error( "Can not open output file for $fileName file" );
        $script->shutdown( 4 );
   }
}

/** Define report file pointer **/

$fp = $openedFPs[$orphanedCsvReportFileName];

/** Write report csv header **/

if ( !fputcsv( $fp, $csvHeader, ';' ) )
{
    $cli->error( "Can not write to report file" );
    $script->shutdown( 6 );
}

/** Iterate over nodes **/

while ( list( $key, $contentObject ) = each( $results ) )
{
    $objectData = array();
    $estimateObjectOrphaned = 0;
    $excludeObjectMainNode = false;
    $excludeObjectWithAttributeShowChildrenChecked = false;
    $status = true;

    /** Fetch object details **/
    $objectAttributeWithNoContent = 1;
    $contentObjectID = $contentObject['contentobject_id'];
    $contentObjectAttributeID = $contentObject['id'];
    $contentObjectVersionID = $contentObject['version'];

    $contentClassAttributeID = $contentObject['contentclassattribute_id'];
    $contentClassAttributeIdentifier = $contentObject['identifier'];
    $contentClassAttributeNameUnserialized = unserialize($contentObject['serialized_name_list']);
    $contentClassAttributeName = $contentClassAttributeNameUnserialized['eng-US'];

    $object = eZContentObject::fetch( $contentObjectID );
    $objectName = $object->name();
    $objectMainNode = $object->mainNode();
    $objectDataMap = $object->dataMap();

    if( $object->attribute( 'current_version' ) != $contentObject['version'] )
    {
        continue;
    }

    if( $excludeObjectsWithAttributeShowChildrenChecked && isset( $objectDataMap['show_children'] ) && $objectDataMap['show_children']->attribute( 'content' ) == 1 )
    {
        continue;
    }

    if ( is_object( $objectMainNode ) )
    {
        $objectMainNodeVisibility = $objectMainNode->attribute( 'is_hidden' );
        $objectMainNodeParentVisibility = $objectMainNode->attribute( 'is_invisible' );

        /** Test if content object tree node id exists within excluded parent node ids content tree node id path **/
        foreach( $excludeParentNodeIDs as $excludeParentNodeID )
        {
            if( strpos( $objectMainNode->attribute( 'path_string' ), '/' . $excludeParentNodeID . '/' ) !== false )
            {
                $excludeObjectMainNode = true;
            }
        }

        /** Exclude matches from the report **/
        if( $excludeObjectMainNode == true )
        {
            continue;
        }

        if( $excludeObjectsWithParentObjectPathTreeObjectsWithAttributeShowChildrenChecked == true && $excludeObjectMainNode == false )
        {
            $objectMainNodePathStringArray = explode( '/', $objectMainNode->attribute( 'path_string' ) );
            array_pop( $objectMainNodePathStringArray );
            array_pop( $objectMainNodePathStringArray );

            if( count( $objectMainNodePathStringArray ) >= 4 )
            {
                $objectMainNodePathStringArray = array_slice( $objectMainNodePathStringArray, 3 );
            }

            $objectMainNodePathStringArray = array_reverse( $objectMainNodePathStringArray );

            /** Test if content object tree path nodes (in reverse) data_map contains show_children attribute checked **/
            foreach( $objectMainNodePathStringArray as $parentNodeID )
            {
                $parentNode = eZContentObjectTreeNode::fetch( $parentNodeID );
                $parentNodeDataMap = $parentNode->dataMap();

                if( isset( $parentNodeDataMap['show_children'] ) && $parentNodeDataMap['show_children']->attribute( 'content' ) == 1 )
                {
                    $excludeObjectWithAttributeShowChildrenChecked = true;
                }
            }

            /** Exclude matches from the report **/
            if( $excludeObjectWithAttributeShowChildrenChecked == true )
            {
                continue;
            }
        }

        $objectMainNodeID = $objectMainNode->attribute( 'node_id' );
        $objectMainNodePath = $siteNodeUrlPrefix . $siteNodeUrlHostname . '/' . $objectMainNode->attribute( 'url' );

        /** Build report for objects **/

        $objectData[] = $contentObjectID;

        $objectData[] = $objectMainNodeID;

        $objectData[] = $contentObjectAttributeID;

        $objectData[] = $contentClassAttributeIdentifier;

        $objectData[] = $contentClassAttributeName;

        $objectData[] = $contentObjectVersionID;

        $objectData[] = $objectAttributeWithNoContent;

        if( $objectMainNodeVisibility == 1 )
        {
            $objectData[] = 'Hidden';
        }
        elseif( $objectMainNodeParentVisibility == 1 )
        {
            $objectData[] = 'Hidden By Parent';
        }
        else
        {
            $objectData[] = 'Visible';
        }

        $objectData[] = $objectName;

        $objectData[] = $objectMainNodePath;

        /** Test if report file is opened **/

        if ( !$fp )
        {
            $cli->error( "Can not open output file" );
            $script->shutdown( 5 );
        }

        /** Write iteration report data to file **/

        if ( !fputcsv( $fp, $objectData, ';' ) )
        {
            $cli->error( "Can not write to file" );
            $script->shutdown( 6 );
        }
    }

    $script->iterate( $cli, $status );
}

/** Close report file **/

while ( $fp = each( $openedFPs ) )
{
    fclose( $fp['value'] );
}

/** Assign permissions to report file **/

chmod( $fileName, 0777);

/** Alert the user to the completion of the report generation **/

$cli->output( "\n\nReport generation complete! Please review the report content written to disk: $fileName\n" );

/** Shutdown script **/

$script->shutdown();

?>