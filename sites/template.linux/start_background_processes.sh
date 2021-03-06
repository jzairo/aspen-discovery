#!/bin/sh
/usr/local/aspen-discovery/sites/{sitename}/{sitename}.sh restart

#Wait for solr to restart and warm up
sleep 10

#TODO: Check for which processes are included on the server, kill the old version, start the new version.
echo "Starting koha export"
cd /usr/local/aspen-discovery/code/koha_export; java -jar koha_export.jar {sitename} &
sleep 5
#echo "Starting Overdrive export"
#cd /usr/local/aspen-discovery/code/overdrive_extract; java -jar overdrive_extract.jar {sitename} &
#sleep 5
#echo "Starting Cloud Library export"
#cd /usr/local/aspen-discovery/code/cloud_library_export; java -jar cloud_library_export.jar {sitename} &
#sleep 5
echo "Starting User List Indexing"
cd /usr/local/aspen-discovery/code/user_list_indexer; java -jar user_list_indexer.jar {sitename} &
sleep 5
#echo "Starting Side Load Processing"
#cd /usr/local/aspen-discovery/code/sideload_processing; java -jar sideload_processing.jar {sitename} &

