<?xml version="1.0" encoding="UTF-8"?>
<!--
	Valid, well-formed stylesheet that stops itself while running.
	Passes upload validation; the transform fails at render time.
	Expected: download falls back to raw XML, no fatal error, message in the PHP log.
-->
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
	<xsl:output method="html" omit-xml-declaration="yes"/>
	<xsl:template match="/">
		<xsl:message terminate="yes">runtime-error.xsl: stopped on purpose</xsl:message>
	</xsl:template>
</xsl:stylesheet>
