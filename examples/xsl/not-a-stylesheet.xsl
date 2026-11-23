<?xml version="1.0" encoding="UTF-8"?>
<!--
	Well-formed XML with a .xsl extension, but the root is not xsl:stylesheet / xsl:transform.
	Expected: rejected on upload with "The uploaded XSL file was not valid", nothing saved.
-->
<stylesheet version="1.0">
	<template match="/">
		<p>Looks like XSL, but has no XSLT namespace.</p>
	</template>
</stylesheet>
