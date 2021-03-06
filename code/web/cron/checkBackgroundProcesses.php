<?php
require_once __DIR__ . '/../bootstrap.php';

global $configArray;
global $serverName;
$runningProcesses = [];
if ($configArray['System']['operatingSystem'] == 'windows'){
	exec("WMIC PROCESS get Processid,Commandline", $processes);
	$processRegEx = '/.*?java\s+-jar\s(.*?)\.jar.*?\s+(\d+)/ix';
	$processIdIndex = 2;
	$processNameIndex = 1;
	$solrRegex = "/{$serverName}\\\\solr7/ix";
}else{
	exec("ps -ef | grep java", $processes);
	$processRegEx = '/(\d+)\s+.*?\d{2}:\d{2}:\d{2}\sjava\s-jar\s(.*?)\.jar\s' . $serverName . '/ix';
	$processIdIndex = 1;
	$processNameIndex = 2;
	$solrRegex = "/{$serverName}\/solr7/ix";
}

$results = "";

$solrRunning = false;
foreach ($processes as $processInfo){
	if (preg_match($processRegEx, $processInfo, $matches)) {
		$processId = $matches[$processIdIndex];
		$process = $matches[$processNameIndex];
		if (array_key_exists($process, $runningProcesses)){
			$results .= "There is more than one process for $process PID: {$runningProcesses[$process]['pid']} and $processId\r\n";
		}else{
			$runningProcesses[$process] = [
				'name' => $process,
				'pid' => $processId
			];
		}

		//echo("Process: $process ($processId)\r\n");
	}else if (preg_match($solrRegex, $processInfo, $matches)) {
		$solrRunning = true;
	}
}

if (!$solrRunning){
	$results .= "Solr is not running for {$serverName}\r\n";
	if ($configArray['System']['operatingSystem'] == 'windows') {
		$solrCmd = "/web/aspen-discovery/sites/{$serverName}/{$serverName}.bat start";
	}else{
		$solrCmd = "/usr/local/aspen-discovery/sites/{$serverName}/{$serverName}.sh start";
	}
	execInBackground($solrCmd);
	$results .= "Started solr using command \r\n$solrCmd\r\n";
}
require_once ROOT_DIR . '/sys/Module.php';
$module = new Module();
$module->enabled = true;
$module->find();

while ($module->fetch()){
	if (!empty($module->backgroundProcess)){
		if (isset($runningProcesses[$module->backgroundProcess])){
			unset($runningProcesses[$module->backgroundProcess]);
		}else{
			$results .= "No process found for '{$module->name}' expected '{$module->backgroundProcess}'\r\n";
			//Attempt to restart the service
			$local = $configArray['Site']['local'];
			//The local path include web, get rid of that
			$local = substr($local, 0, strrpos($local, '/'));
			$processPath = $local . '/' . $module->backgroundProcess;
			if (file_exists($processPath)){
				if (file_exists($processPath . "/{$module->backgroundProcess}.jar")){
					execInBackground("cd $processPath; java -jar {$module->backgroundProcess}.jar $serverName");
					$results .= "Restarted '{$module->name}'\r\n";
				}else{
					$results .= "Could not automatically restart {$module->name}, the jar $processPath/{$module->backgroundProcess}.jar did not exist\r\n";
				}
			}else{
				$results .= "Could not automatically restart {$module->name}, the directory $processPath did not exist\r\n";
			}
		}
	}
}

foreach ($runningProcesses as $process){
	if ($process['name'] != 'cron' && $process['name'] != 'oai_indexer' && $process['name'] != 'reindexer' && $process['name'] != 'hoopla_export'){
		$results .= "Found process '{$process['name']}' that does not have a module for it\r\n";
	}
}

if (strlen($results) > 0){
	//For debugging
	try {
		require_once ROOT_DIR . '/sys/SystemVariables.php';
		$systemVariables = new SystemVariables();
		if ($systemVariables->find(true) && !empty($systemVariables->errorEmail)) {
			require_once ROOT_DIR . '/sys/Email/Mailer.php';
			$mailer = new Mailer();
			$mailer->send($systemVariables->errorEmail, "$serverName Error with Background processes", $results);
		}
	}catch (Exception $e) {
		//This happens if the table has not been created
	}
}

function execInBackground($cmd) {
	if (substr(php_uname(), 0, 7) == "Windows"){
		pclose(popen("start /B ". $cmd, "r"));
	}
	else {
		exec($cmd . " > /dev/null &");
	}
}