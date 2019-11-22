<?php

require_once ROOT_DIR . '/Action.php';
require_once ROOT_DIR . '/services/Admin/Admin.php';
require_once ROOT_DIR . '/sys/Pager.php';

abstract class Admin_IndexingLog extends Admin_Admin
{
	function launch()
	{
		global $interface;
		global $configArray;

		$logEntries = array();
		$logEntry = $this->getIndexLogEntryObject();
		if (isset($_REQUEST['processedLimit']) && is_numeric($_REQUEST['processedLimit'])){
			$this->applyMinProcessedFilter($logEntry, $_REQUEST['processedLimit']);
		}
		$total = $logEntry->count();
		$logEntry = $this->getIndexLogEntryObject();
		$logEntry->orderBy('startTime DESC');
		$page = isset($_REQUEST['page']) ? $_REQUEST['page'] : 1;
		$pageSize = isset($_REQUEST['pageSize']) ? $_REQUEST['pageSize'] : 30; // to adjust number of items listed on a page
		$interface->assign('recordsPerPage', $pageSize);
		$interface->assign('page', $page);
		$logEntry->limit(($page - 1) * $pageSize, $pageSize);
		if (isset($_REQUEST['processedLimit']) && is_numeric($_REQUEST['processedLimit'])){
			$interface->assign('processedLimit', $_REQUEST['processedLimit']);
			$this->applyMinProcessedFilter($logEntry, $_REQUEST['processedLimit']);
		}
		$logEntry->find();
		while ($logEntry->fetch()){
			$logEntries[] = clone($logEntry);
		}
		$interface->assign('logEntries', $logEntries);

		$options = array('totalItems' => $total,
			'fileName'   => "/{$this->getModule()}/IndexingLog?page=%d". (empty($_REQUEST['pageSize']) ? '' : '&pageSize=' . $_REQUEST['pageSize']),
			'perPage'    => $pageSize,
		);
		$pager = new Pager($options);
		$interface->assign('pageLinks', $pager->getLinks());

		$this->display($this->getTemplateName(), $this->getTitle());
	}

	function getAllowableRoles(){
		return array('opacAdmin', 'libraryAdmin', 'cataloging');
	}

	abstract function getIndexLogEntryObject() : DataObject;

	abstract function getTemplateName() : string;

	abstract function getTitle() : string;

	abstract function getModule() : string;

	abstract function applyMinProcessedFilter(DataObject $indexingObject, $minProcessed);
}