<?xml version="1.0" encoding="UTF-8"?>
<!-- Smallest valid custom stylesheet. Galley shows "CUSTOM: <article title>". -->
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
	<xsl:output method="html" omit-xml-declaration="yes" encoding="utf-8"/>
	<xsl:template match="/">
		<p>CUSTOM: <xsl:value-of select="//article-title"/></p>
	</xsl:template>
</xsl:stylesheet>
