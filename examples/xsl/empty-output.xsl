<?xml version="1.0" encoding="UTF-8"?>
<!--
	Valid stylesheet whose output is only whitespace.
	Passes upload validation. Expected: the plugin treats empty output as a failure,
	so the download falls back to raw XML. Not cached.
-->
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
	<xsl:output method="html" omit-xml-declaration="yes"/>
	<xsl:template match="/"><xsl:text> </xsl:text></xsl:template>
</xsl:stylesheet>
