{strip}
	<div id="home-page-library-section" class="row"{if $displaySidebarMenu} style="display: none"{/if}>
		{if $showLibraryHoursAndLocationsLink}
			<a href="{$path}/AJAX/JSON?method=getHoursAndLocations" data-title="Library Hours and Locations" class="modalDialogTrigger">
				<div id="home-page-hours-locations" class="sidebar-button">
					{if !isset($hasValidHours) || $hasValidHours}Library Hours &amp; {/if}Location{if $numLocations != 1}s{/if}
				</div>
			</a>
		{/if}

		{if !empty($homeLink)}
			<a href="{$homeLink}">
				<div id="home-page-home-button" class="sidebar-button">
					{translate text='Library Home Page'}
				</div>
			</a>
		{/if}

		{include file="library-links.tpl" libraryLinks=$libraryHelpLinks linksId='home-library-links' section='Help'}
	</div>
{/strip}