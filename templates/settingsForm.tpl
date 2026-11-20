{**
 * plugins/generic/xmlGalley/templates/settingsForm.tpl
 *
 * Copyright (c) 2013-2026 Simon Fraser University
 * Copyright (c) 2003-2026 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * XML galley plugin settings.
 *}
<script>
	$(function() {ldelim}
		$('#xmlGalleySettingsForm').pkpHandler(
			'$.pkp.controllers.form.FileUploadFormHandler',
			{ldelim}
				$uploader: $('#xmlGalleyPlupload'),
				uploaderOptions: {ldelim}
					uploadUrl: {url|json_encode router=$smarty.const.ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="uploadXSL" escape=false},
					baseUrl: {$baseUrl|json_encode}
				{rdelim}
			{rdelim}
		);
	{rdelim});
</script>

<form class="pkp_form" id="xmlGalleySettingsForm" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="settings" save=true}">
	{csrf}
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="xmlGalleySettingsFormNotification"}

	<div>{translate key="plugins.generic.xmlGalley.settings.description"}</div>

	{fbvFormArea id="xmlGalleyStylesheetArea"}
		{fbvFormSection list=true title="plugins.generic.xmlGalley.settings.stylesheet" required=true}
			{fbvElement type="radio" id="XSLstylesheet-NLM" name="XSLstylesheet" value="NLM" checked=!$isCustom label="plugins.generic.xmlGalley.settings.xslNLM"}
			{fbvElement type="radio" id="XSLstylesheet-custom" name="XSLstylesheet" value="custom" checked=$isCustom label="plugins.generic.xmlGalley.settings.customXSL"}
		{/fbvFormSection}
		{fbvFormSection title="plugins.generic.xmlGalley.settings.customXSLUpload"}
			{if $customXSL}
				<p>{translate key="plugins.generic.xmlGalley.settings.customXSLCurrent" fileName=$customXSL|escape}</p>
			{/if}
			{fbvElement type="hidden" id="temporaryFileId" value=""}
			{include file="controllers/fileUploadContainer.tpl" id="xmlGalleyPlupload"}
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormButtons}
	<p><span class="formRequired">{translate key="common.requiredField"}</span></p>
</form>
