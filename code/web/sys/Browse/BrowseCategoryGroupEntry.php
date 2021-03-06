<?php

require_once ROOT_DIR . '/sys/Browse/BrowseCategoryGroup.php';

class BrowseCategoryGroupEntry extends DataObject
{
	public $__table = 'browse_category_group_entry';
	public $id;
	public $weight;
	public $browseCategoryGroupId;
	public $browseCategoryId;

	static function getObjectStructure(){
		//Load Groups for lookup values
		$groups = new BrowseCategoryGroup();
		$groups->orderBy('name');
		$groups->find();
		$groupList = array();
		while ($groups->fetch()){
			$groupList[$groups->id] = $groups->name;
		}
		require_once ROOT_DIR . '/sys/Browse/BrowseCategory.php';
		$browseCategories = new BrowseCategory();
		$browseCategories->orderBy('label');
		$browseCategories->find();
		while($browseCategories->fetch()){
			$browseCategoryList[$browseCategories->id] = $browseCategories->label . " ({$browseCategories->textId})";
		}
		$structure = array(
			'id' => array('property'=>'id', 'type'=>'label', 'label'=>'Id', 'description'=>'The unique id of the hours within the database'),
			'browseCategoryGroupId' => array('property'=>'browseCategoryGroupId', 'type'=>'enum', 'values'=>$groupList, 'label'=>'Group', 'description'=>'The group the browse category should be added in'),
			'browseCategoryId' => array('property'=>'browseCategoryId', 'type'=>'enum', 'values'=>$browseCategoryList, 'label'=>'Browse Category', 'description'=>'The browse category to display '),
			'weight' => array('property' => 'weight', 'type' => 'numeric', 'label' => 'Weight', 'weight' => 'Defines how lists are sorted within the group.  Lower weights are displayed to the left of the screen.', 'required'=> true),
		);
		return $structure;
	}

	function getEditLink(){
		return '/Admin/BrowseCategories?objectAction=edit&id=' . $this->browseCategoryId;
	}
}