<?xml version="1.0" encoding="UTF-8"?>
<!--
	Valid stylesheet that includes a sibling file (custom-basic.xsl).
	The upload passes validation (it is a well-formed XSL), but the plugin stores
	only this one file as custom.xsl, so the include cannot be found at render time.
	Expected: transform fails, download falls back to raw XML, error in the PHP log.
	Documents the "custom XSL must be one file" limitation.
-->
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
	<xsl:include href="custom-basic.xsl"/>
</xsl:stylesheet>
