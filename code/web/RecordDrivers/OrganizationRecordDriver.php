<?php

require_once ROOT_DIR . '/RecordDrivers/IslandoraRecordDriver.php';
class OrganizationRecordDriver extends IslandoraRecordDriver {

	public function getViewAction() {
		return 'Organization';
	}

	protected function getPlaceholderImage() {
		return '/interface/themes/responsive/images/organization.png';
	}

	public function isEntity(){
		return true;
	}

	public function getFormat(){
		return 'Organization';
	}

	public function getMoreDetailsOptions() {
		//Load more details options
		global $interface;
		$moreDetailsOptions = $this->getBaseMoreDetailsOptions();

		$relatedPlaces = $this->getRelatedPlaces();
		$unlinkedEntities = $this->unlinkedEntities;
		$linkedAddresses = array();
		$unlinkedAddresses = array();
		foreach ($unlinkedEntities as $key => $tmpEntity){
			if ($tmpEntity['type'] == 'place'){
				if (strcasecmp($tmpEntity['role'], 'address') === 0){
					$unlinkedAddresses[] = $tmpEntity;
					unset($this->unlinkedEntities[$key]);
					$interface->assign('unlinkedEntities', $this->unlinkedEntities);
				}
			}
		}
		foreach ($relatedPlaces as $key => $tmpEntity){
			if (strcasecmp($tmpEntity['role'], 'address') === 0){
				$linkedAddresses[] = $tmpEntity;
				unset($this->relatedPlaces[$key]);
				$interface->assign('relatedPlaces', $this->relatedPlaces);
			}
		}
		if (count($this->relatedPlaces) == 0){
			unset($moreDetailsOptions['relatedPlaces']);
		}
		$interface->assign('unlinkedAddresses', $unlinkedAddresses);
		$interface->assign('linkedAddresses', $linkedAddresses);
		if (count($linkedAddresses) || count($unlinkedAddresses)) {
			$moreDetailsOptions['addresses'] = array(
					'label' => 'Addresses',
					'body' => $interface->fetch('Archive/addressSection.tpl'),
					'hideByDefault' => false,
			);
		}
		if ((count($interface->getVariable('creators')) > 0)
				|| $this->hasDetails
				|| (count($interface->getVariable('marriages')) > 0)
				|| (count($this->unlinkedEntities) > 0)){
			$moreDetailsOptions['details'] = array(
					'label' => 'Details',
					'body' => $interface->fetch('Archive/detailsSection.tpl'),
					'hideByDefault' => false
			);
		}else{
			unset($moreDetailsOptions['details']);
		}

		return $this->filterAndSortMoreDetailsOptions($moreDetailsOptions);
	}
}