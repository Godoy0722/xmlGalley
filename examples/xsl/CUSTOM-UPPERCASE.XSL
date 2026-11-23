<?xml version="1.0" encoding="UTF-8"?>
<!--
	Valid stylesheet with an upper-case .XSL extension.
	Expected: accepted (the extension check ignores case). Galley shows "CUSTOM UPPERCASE: <article title>".
-->
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
	<xsl:output method="html" omit-xml-declaration="yes" encoding="utf-8"/>
	<xsl:template match="/">
		<p>CUSTOM UPPERCASE: <xsl:value-of select="//article-title"/></p>
	</xsl:template>
</xsl:stylesheet>
