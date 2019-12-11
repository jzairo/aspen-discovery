package com.turning_leaf_technologies.reindexer;

import com.turning_leaf_technologies.indexing.Scope;
import com.turning_leaf_technologies.indexing.SideLoadScope;
import com.turning_leaf_technologies.marc.MarcUtil;
import org.apache.logging.log4j.Logger;
import org.marc4j.marc.Record;

import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.util.*;

class SideLoadedEContentProcessor extends MarcRecordProcessor{
	private long sideLoadId;
	private String profileType;
	protected boolean fullReindex;
	private PreparedStatement getDateAddedStmt;

	SideLoadedEContentProcessor(GroupedWorkIndexer indexer, Connection dbConn, ResultSet sideLoadSettingsRS, Logger logger, boolean fullReindex) {
		super(indexer, logger);
		this.fullReindex = fullReindex;

		try{
			sideLoadId = sideLoadSettingsRS.getLong("id");
			profileType = sideLoadSettingsRS.getString("name");
			numCharsToCreateFolderFrom = sideLoadSettingsRS.getInt("numCharsToCreateFolderFrom");
			createFolderFromLeadingCharacters = sideLoadSettingsRS.getBoolean("createFolderFromLeadingCharacters");
			individualMarcPath = sideLoadSettingsRS.getString("individualMarcPath");
			formatSource = sideLoadSettingsRS.getString("formatSource");
			specifiedFormat = sideLoadSettingsRS.getString("specifiedFormat");
			specifiedFormatCategory = sideLoadSettingsRS.getString("specifiedFormatCategory");
			specifiedFormatBoost = sideLoadSettingsRS.getInt("specifiedFormatBoost");

			getDateAddedStmt = dbConn.prepareStatement("SELECT dateFirstDetected FROM ils_marc_checksums WHERE ilsId = ?", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
		}catch (Exception e){
			logger.error("Error setting up side load processor");
		}
	}

	@Override
	protected void updateGroupedWorkSolrDataBasedOnMarc(GroupedWorkSolr groupedWork, Record record, String identifier) {
		try{
			HashSet<RecordInfo> allRelatedRecords = new HashSet<>();
			RecordInfo recordInfo = loadEContentRecord(groupedWork, identifier, record);
			allRelatedRecords.add(recordInfo);

			//Do updates based on the overall bib (shared regardless of scoping)
			String primaryFormat = recordInfo.getPrimaryFormat();
			if (primaryFormat == null) primaryFormat = "Unknown";
			updateGroupedWorkSolrDataBasedOnStandardMarcData(groupedWork, record, recordInfo.getRelatedItems(), identifier, primaryFormat);

			String fullDescription = Util.getCRSeparatedString(MarcUtil.getFieldList(record, "520a"));
			groupedWork.addDescription(fullDescription, primaryFormat);

			loadEditions(groupedWork, record, allRelatedRecords);
			loadPhysicalDescription(groupedWork, record, allRelatedRecords);
			loadLanguageDetails(groupedWork, record, allRelatedRecords, identifier);
			loadPublicationDetails(groupedWork, record, allRelatedRecords);

			if (record.getControlNumber() != null){
				groupedWork.addKeywords(record.getControlNumber());
			}

			//Do updates based on items
			loadPopularity(groupedWork, identifier);

			groupedWork.addHoldings(1);

			scopeItems(recordInfo, record);
		}catch (Exception e){
			logger.error("Error updating grouped work for side loaded eContent MARC record with identifier " + identifier, e);
		}
	}

	private void scopeItems(RecordInfo recordInfo, Record record){
		for (ItemInfo itemInfo : recordInfo.getRelatedItems()){
			loadScopeInfoForEContentItem(itemInfo, record);
		}
	}

	private void loadScopeInfoForEContentItem(ItemInfo itemInfo, Record record) {
		String itemLocation = itemInfo.getLocationCode();
		String originalUrl = itemInfo.geteContentUrl();
		for (Scope curScope : indexer.getScopes()){
//			String format = itemInfo.getFormat();
//			if (format == null){
//				format = itemInfo.getRecordInfo().getPrimaryFormat();
//			}
			SideLoadScope sideLoadScope = curScope.getSideLoadScope(sideLoadId);
			if (sideLoadScope != null) {
				boolean itemPartOfScope = sideLoadScope.isItemPartOfScope(record);
				if (itemPartOfScope) {
					ScopingInfo scopingInfo = itemInfo.addScope(curScope);
					scopingInfo.setAvailable(true);
					scopingInfo.setStatus("Available Online");
					scopingInfo.setGroupedStatus("Available Online");
					scopingInfo.setHoldable(false);
					if (curScope.isLocationScope()) {
						scopingInfo.setLocallyOwned(curScope.isItemOwnedByScope(profileType, itemLocation, ""));
						if (curScope.getLibraryScope() != null) {
							scopingInfo.setLibraryOwned(curScope.getLibraryScope().isItemOwnedByScope(profileType, itemLocation, ""));
						}
					}
					if (curScope.isLibraryScope()) {
						scopingInfo.setLibraryOwned(curScope.isItemOwnedByScope(profileType, itemLocation, ""));
					}
					//Check to see if we need to do url rewriting
					if (originalUrl != null) {
						String newUrl = sideLoadScope.getLocalUrl(originalUrl);
						scopingInfo.setLocalUrl(newUrl);
					}
				}
			}
		}
	}

	private void loadPopularity(GroupedWorkSolr groupedWork, @SuppressWarnings("unused") String identifier) {
		//TODO: Load popularity based on usage in the database
		groupedWork.addPopularity(0);
	}

	private RecordInfo loadEContentRecord(GroupedWorkSolr groupedWork, String identifier, Record record){
		//We will always have a single record
		return getEContentIlsRecord(groupedWork, record, identifier);
	}

	private RecordInfo getEContentIlsRecord(GroupedWorkSolr groupedWork, Record record, String identifier) {
		ItemInfo itemInfo = new ItemInfo();
		itemInfo.setIsEContent(true);

		loadDateAdded(identifier, itemInfo);
		itemInfo.setLocationCode(profileType);
		itemInfo.setCallNumber("Online " + profileType);
		itemInfo.setItemIdentifier(identifier);
		itemInfo.setShelfLocation(profileType);

		//No Collection for Side loaded eContent
		//itemInfo.setCollection(translateValue("collection", getItemSubfieldData(collectionSubfield, itemField), identifier));

		itemInfo.seteContentSource(profileType);

		RecordInfo relatedRecord = groupedWork.addRelatedRecord(profileType, identifier);
		relatedRecord.addItem(itemInfo);
		loadEContentUrl(record, itemInfo);

		loadEContentFormatInformation(record, relatedRecord, itemInfo);

		itemInfo.setDetailedStatus("Available Online");

		return relatedRecord;
	}

	private void loadEContentFormatInformation(Record record, RecordInfo econtentRecord, ItemInfo econtentItem) {
		if (formatSource.equals("specified")){
			HashSet<String> translatedFormats = new HashSet<>();
			translatedFormats.add(specifiedFormat);
			HashSet<String> translatedFormatCategories = new HashSet<>();
			translatedFormatCategories.add(specifiedFormatCategory);
			econtentRecord.addFormats(translatedFormats);
			econtentRecord.addFormatCategories(translatedFormatCategories);
			econtentRecord.setFormatBoost(specifiedFormatBoost);
		} else {
			LinkedHashSet<String> printFormats = getFormatsFromBib(record, econtentRecord);
			//Convert formats from print to eContent version
			for (String format : printFormats) {
				if (format.equalsIgnoreCase("eBook") || format.equalsIgnoreCase("Book") || format.equalsIgnoreCase("LargePrint") || format.equalsIgnoreCase("GraphicNovel") || format.equalsIgnoreCase("Manuscript") || format.equalsIgnoreCase("Thesis") || format.equalsIgnoreCase("Print") || format.equalsIgnoreCase("Microfilm") || format.equalsIgnoreCase("Kit")) {
					econtentItem.setFormat("eBook");
					econtentItem.setFormatCategory("eBook");
					econtentRecord.setFormatBoost(10);
				}else if (format.equalsIgnoreCase("Journal") || format.equalsIgnoreCase("Serial")) {
					econtentItem.setFormat("eMagazine");
					econtentItem.setFormatCategory("eBook");
					econtentRecord.setFormatBoost(3);
				} else if (format.equalsIgnoreCase("SoundRecording") || format.equalsIgnoreCase("SoundDisc") || format.equalsIgnoreCase("Playaway") || format.equalsIgnoreCase("CDROM") || format.equalsIgnoreCase("SoundCassette") || format.equalsIgnoreCase("CompactDisc") || format.equalsIgnoreCase("eAudio")) {
					econtentItem.setFormat("eAudiobook");
					econtentItem.setFormatCategory("Audio Books");
					econtentRecord.setFormatBoost(8);
				} else if (format.equalsIgnoreCase("MusicRecording")) {
					econtentItem.setFormat("eMusic");
					econtentItem.setFormatCategory("Music");
					econtentRecord.setFormatBoost(5);
				} else if (format.equalsIgnoreCase("MusicalScore")) {
					econtentItem.setFormat("MusicalScore");
					econtentItem.setFormatCategory("eBook");
					econtentRecord.setFormatBoost(5);
				} else if (format.equalsIgnoreCase("Movies") || format.equalsIgnoreCase("Video") || format.equalsIgnoreCase("DVD") || format.equalsIgnoreCase("VideoDisc")) {
					econtentItem.setFormat("eVideo");
					econtentItem.setFormatCategory("Movies");
					econtentRecord.setFormatBoost(10);
				} else if (format.equalsIgnoreCase("Electronic") || format.equalsIgnoreCase("Software")) {
					econtentItem.setFormat("Online Materials");
					econtentItem.setFormatCategory("Other");
					econtentRecord.setFormatBoost(2);
				} else if (format.equalsIgnoreCase("Photo")) {
					econtentItem.setFormat("Photo");
					econtentItem.setFormatCategory("Other");
					econtentRecord.setFormatBoost(2);
				} else if (format.equalsIgnoreCase("Map")) {
					econtentItem.setFormat("Map");
					econtentItem.setFormatCategory("Other");
					econtentRecord.setFormatBoost(2);
				} else if (format.equalsIgnoreCase("Newspaper")) {
					econtentItem.setFormat("Newspaper");
					econtentItem.setFormatCategory("eBook");
					econtentRecord.setFormatBoost(2);
				} else {
					logger.warn("Could not find appropriate eContent format for " + format + " while side loading eContent " + econtentRecord.getFullIdentifier());
				}
			}
		}
	}

	private void loadDateAdded(String identifier, ItemInfo itemInfo) {
		try {
			getDateAddedStmt.setString(1, identifier);
			ResultSet getDateAddedRS = getDateAddedStmt.executeQuery();
			if (getDateAddedRS.next()) {
				long timeAdded = getDateAddedRS.getLong(1);
				Date curDate = new Date(timeAdded * 1000);
				itemInfo.setDateAdded(curDate);
				getDateAddedRS.close();
			}else{
				logger.debug("Could not determine date added for " + identifier);
			}
		}catch (Exception e){
			logger.error("Unable to load date added for " + identifier);
		}
	}
}
