<?xml version="1.0" encoding="UTF-8"?>
<!--
	Not well-formed: <xsl:template> is never closed.
	Expected: rejected on upload with "The uploaded XSL file was not valid", nothing saved.
-->
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
	<xsl:template match="/">
		<p>Never closed</p>
</xsl:stylesheet>
