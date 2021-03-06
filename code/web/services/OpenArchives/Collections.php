<?php

require_once ROOT_DIR . '/Action.php';
require_once ROOT_DIR . '/services/Admin/Admin.php';
require_once ROOT_DIR . '/services/Admin/ObjectEditor.php';
require_once ROOT_DIR . '/sys/OpenArchives/OpenArchivesCollection.php';
class OpenArchives_Collections extends ObjectEditor {
	function getObjectType(){
		return 'OpenArchivesCollection';
	}
	function getToolName(){
		return 'Collections';
	}
    function getModule(){
        return 'OpenArchives';
    }
	function getPageTitle(){
		return 'Open Archives collections to include';
	}
	function getAllObjects(){
		$list = array();

		$object = new OpenArchivesCollection();
		$object->orderBy('name asc');
		$object->find();
		while ($object->fetch()){
			$list[$object->id] = clone $object;
		}

		return $list;
	}
	function getObjectStructure(){
		return OpenArchivesCollection::getObjectStructure();
	}
	function getAllowableRoles(){

		return array('opacAdmin', 'libraryAdmin', 'archives');
	}
	function getPrimaryKeyColumn(){
		return 'id';
	}
	function getIdKeyColumn(){
		return 'id';
	}
	function canAddNew(){
		return true;
	}
	function canDelete(){
		return UserAccount::userHasRole('opacAdmin') || UserAccount::userHasRole('libraryAdmin') || UserAccount::userHasRole('archives');
	}

}