<script src="/ckeditor/ckeditor.js"></script>
{if $lastError}
	<div class="alert alert-danger">
		{$lastError}
	</div>
{/if}
{strip}
	<div class="col-xs-12">
		{if !empty($pageTitleShort) || !empty($objectName)}
			<h1>{if !empty($pageTitleShort)}{$pageTitleShort} - {/if}{$objectName}</h1>
		{/if}
		<p>
			{if $showReturnToList}
				<a class="btn btn-default" href='/{$module}/{$toolName}?objectAction=list'>{translate text="Return to List"}</a>
			{/if}
			{if $id > 0 && $canDelete}<a class="btn btn-danger" href='/{$module}/{$toolName}?id={$id}&amp;objectAction=delete' onclick='return confirm("Are you sure you want to delete this {$objectType}?")'>{translate text="Delete"}</a>{/if}
		</p>
		<div class="btn-group">
			{foreach from=$additionalObjectActions item=action}
				<a class="btn btn-default btn-sm"{if $action.url} href='{$action.url}'{/if}{if $action.onclick} onclick="{$action.onclick}"{/if}>{$action.text}</a>
			{/foreach}
		</div>
		{include file="DataObjectUtil/objectEditForm.tpl"}
	</div>
{/strip}