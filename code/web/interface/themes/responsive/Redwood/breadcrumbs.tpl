{strip}
	{if $lastSearch}
		<a href="{$lastSearch|escape}#record{$id|escape:"url"}">{translate text="Archive Search Results"}</a> <span class="divider">&raquo;</span>
	{else}
		<a href="/Redwood/Home">Digital Archive</a> <span class="divider">&raquo;</span>
	{/if}
	{if $pageTitleShort}
		<em>{$pageTitleShort}</em> <span class="divider">&raquo;</span>
	{/if}
{/strip}