<?xml version="1.0" encoding="UTF-8"?>
<!-- Second version of custom-basic.xsl, to test replacing a stored stylesheet. Galley shows "CUSTOM 2: <article title>". -->
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
	<xsl:output method="html" omit-xml-declaration="yes" encoding="utf-8"/>
	<xsl:template match="/">
		<p>CUSTOM 2: <xsl:value-of select="//article-title"/></p>
	</xsl:template>
</xsl:stylesheet>
