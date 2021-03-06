<?php

require_once ROOT_DIR . '/Action.php';
require_once ROOT_DIR . '/RecordDrivers/RecordDriverFactory.php';
require_once ROOT_DIR . '/sys/Genealogy/Person.php';

class Person_Home extends Action
{
	function __construct($subAction = false, $record_id = null)
	{
		global $interface;
		global $configArray;
		global $timer;
		$user = UserAccount::getLoggedInUser();

		//Check to see if a user is logged in with admin permissions
		if ($user && UserAccount::userHasRole('genealogyContributor')){
			$interface->assign('userIsAdmin', true);
		}else{
			$interface->assign('userIsAdmin', false);
		}

		$searchSource = !empty($_REQUEST['searchSource']) ? $_REQUEST['searchSource'] : 'local';

		//Load basic information needed in subclasses
		if ($record_id == null || !isset($record_id)){
			$this->id = $_GET['id'];
		}else{
			$this->id = $record_id;
		}

		// Setup Search Engine Connection
		// Include Search Engine Class
        require_once ROOT_DIR . '/sys/SolrConnector/GenealogySolrConnector.php';
		$timer->logTime('Include search engine');

		// Initialise from the current search globals
		$this->db = SearchObjectFactory::initSearchObject('Genealogy');
		$this->db->init($searchSource);

		// Retrieve Full Marc Record
		if (!($record = $this->db->getRecord($this->id))) {
			AspenError::raiseError(new AspenError('Record Does Not Exist'));
		}
		$this->record = $record;

		//Load person from the database to get additional information
		/* @var Person $person */
		$person = new Person();
		$person->get($this->id);
		$record['picture'] = $person->picture;

		$interface->assign('record', $record);
		$interface->assign('person', $person);
		$this->recordDriver = RecordDriverFactory::initRecordDriver($record);
		$interface->assign('recordDriver', $this->recordDriver);
		$timer->logTime('Initialized the Record Driver');

		$marriages = array();
		$personMarriages = $person->marriages;
		if (isset($personMarriages)){
			foreach ($personMarriages as $marriage){
				$marriageArray = (array)$marriage;
				$marriageArray['formattedMarriageDate'] = $person->formatPartialDate($marriage->marriageDateDay, $marriage->marriageDateMonth, $marriage->marriageDateYear);
				$marriages[] = $marriageArray;
			}
		}
		$interface->assign('marriages', $marriages);
		$obituaries = array();
		$personObituaries =$person->obituaries;
		if (isset($personObituaries)){
			foreach ($personObituaries as $obit){
				$obitArray = (array)$obit;
				$obitArray['formattedObitDate'] = $person->formatPartialDate($obit->dateDay, $obit->dateMonth, $obit->dateYear);
				$obituaries[] = $obitArray;
			}
		}
		$interface->assign('obituaries', $obituaries);

		//Do actions needed if this is the main action.
		$interface->assign('id', $this->id);

		// Retrieve User Search History
		$interface->assign('lastSearch', isset($_SESSION['lastSearchURL']) ?
		$_SESSION['lastSearchURL'] : false);

		$this->cacheId = 'Person|' . $_GET['id'] . '|' . get_class($this);

		$formattedBirthdate = $person->formatPartialDate($person->birthDateDay, $person->birthDateMonth, $person->birthDateYear);
		$interface->assign('birthDate', $formattedBirthdate);

		$formattedDeathdate = $person->formatPartialDate($person->deathDateDay, $person->deathDateMonth, $person->deathDateYear);
		$interface->assign('deathDate', $formattedDeathdate);

		//Setup next and previous links based on the search results.
		if (isset($_REQUEST['searchId'])){
			//rerun the search
			$s = new SearchEntry();
			$s->id = $_REQUEST['searchId'];
			$interface->assign('searchId', $_REQUEST['searchId']);
			$currentPage = isset($_REQUEST['page']) ? $_REQUEST['page'] : 1;
			$interface->assign('page', $currentPage);

			$s->find();
			if ($s->getNumResults() > 0){
				$s->fetch();
				$minSO = unserialize($s->search_object);
				$searchObject = SearchObjectFactory::deminify($minSO);
				$searchObject->setPage($currentPage);
				//Run the search
				$result = $searchObject->processSearch(true, false, false);

				//Check to see if we need to run a search for the next or previous page
				$currentResultIndex = $_REQUEST['recordIndex'] - 1;
				$recordsPerPage = $searchObject->getLimit();

				if (($currentResultIndex) % $recordsPerPage == 0 && $currentResultIndex > 0){
					//Need to run a search for the previous page
					$interface->assign('previousPage', $currentPage - 1);
					$previousSearchObject = clone $searchObject;
					$previousSearchObject->setPage($currentPage - 1);
					$previousSearchObject->processSearch(true, false, false);
					$previousResults = $previousSearchObject->getResultRecordSet();
				}else if (($currentResultIndex + 1) % $recordsPerPage == 0 && ($currentResultIndex + 1) < $searchObject->getResultTotal()){
					//Need to run a search for the next page
					$nextSearchObject = clone $searchObject;
					$interface->assign('nextPage', $currentPage + 1);
					$nextSearchObject->setPage($currentPage + 1);
					$nextSearchObject->processSearch(true, false, false);
					$nextResults = $nextSearchObject->getResultRecordSet();
				}

				if ($result instanceof AspenError) {
					//If we get an error excuting the search, just eat it for now.
				}else{
					if ($searchObject->getResultTotal() < 1) {
						//No results found
					}else{
						$recordSet = $searchObject->getResultRecordSet();
						//Record set is 0 based, but we are passed a 1 based index
						if ($currentResultIndex > 0){
							if (isset($previousResults)){
								$previousRecord = $previousResults[count($previousResults) -1];
							}else{
								$previousRecord = $recordSet[$currentResultIndex - 1 - (($currentPage -1) * $recordsPerPage)];
							}
							$interface->assign('previousId', $previousRecord['id']);
							//Convert back to 1 based index
							$interface->assign('previousIndex', $currentResultIndex - 1 + 1);
							$interface->assign('previousTitle', $previousRecord['title']);
						}
						if ($currentResultIndex + 1 < $searchObject->getResultTotal()){

							if (isset($nextResults)){
								$nextRecord = $nextResults[0];
							}else{
								$nextRecord = $recordSet[$currentResultIndex + 1 - (($currentPage -1) * $recordsPerPage)];
							}
							$interface->assign('nextId', $nextRecord['id']);
							//Convert back to 1 based index
							$interface->assign('nextIndex', $currentResultIndex + 1 + 1);
							$interface->assign('nextTitle', $nextRecord['title']);
						}

					}
				}
			}
			$timer->logTime('Got next/previous links');
		}
	}

	function launch()
	{

		$titleField = $this->recordDriver->getName(); //$this->record['firstName'] . ' ' . $this->record['lastName'];

		// Display Page
		$this->display('full-record.tpl', $titleField);
	}
}