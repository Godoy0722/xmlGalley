<?xml version="1.0" encoding="UTF-8"?>
<!--
	Realistic one-file custom stylesheet for NLM/JATS articles.
	Outputs every attribute kind the plugin rewrites to dependent-file URLs:
	  <img src="...">      from <graphic> / <inline-graphic>
	  <a href="...">       from <supplementary-material> / <inline-supplementary-material>
	  <object data="...">  from <graphic> (as an embedded object, second rendering)
	Use with examples/xml/full-article.xml and its dependent files.
	Marker: header line "CUSTOM FULL" so you can tell it apart from the NLM rendering.
-->
<xsl:stylesheet version="1.0"
	xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
	xmlns:xlink="http://www.w3.org/1999/xlink"
	xmlns:mml="http://www.w3.org/1998/Math/MathML"
	exclude-result-prefixes="xlink mml">

	<xsl:output method="html" omit-xml-declaration="yes" encoding="utf-8" indent="yes"/>

	<xsl:template match="/">
		<div class="custom-full">
			<p class="marker">CUSTOM FULL</p>
			<xsl:apply-templates select="article"/>
		</div>
	</xsl:template>

	<xsl:template match="article">
		<h1><xsl:apply-templates select="front/article-meta/title-group/article-title"/></h1>
		<xsl:apply-templates select="front/article-meta/contrib-group[1]"/>
		<xsl:apply-templates select="front/article-meta/abstract"/>
		<xsl:apply-templates select="body"/>
		<xsl:apply-templates select="back/ref-list"/>
	</xsl:template>

	<!-- Front matter -->
	<xsl:template match="contrib-group">
		<p class="authors">
			<xsl:for-each select="contrib[@contrib-type='author']">
				<xsl:if test="position() &gt; 1">, </xsl:if>
				<xsl:choose>
					<xsl:when test="collab"><xsl:value-of select="collab"/></xsl:when>
					<xsl:otherwise><xsl:value-of select="concat(name/given-names, ' ', name/surname)"/></xsl:otherwise>
				</xsl:choose>
			</xsl:for-each>
		</p>
	</xsl:template>

	<xsl:template match="abstract">
		<section class="abstract">
			<h2>Abstract</h2>
			<xsl:apply-templates select="p | sec"/>
		</section>
	</xsl:template>

	<!-- Body -->
	<xsl:template match="sec">
		<section id="{@id}">
			<xsl:apply-templates/>
		</section>
	</xsl:template>

	<xsl:template match="sec/title">
		<xsl:element name="h{count(ancestor::sec) + 1}"><xsl:apply-templates/></xsl:element>
	</xsl:template>

	<xsl:template match="p"><p><xsl:apply-templates/></p></xsl:template>
	<xsl:template match="bold"><strong><xsl:apply-templates/></strong></xsl:template>
	<xsl:template match="italic"><em><xsl:apply-templates/></em></xsl:template>
	<xsl:template match="sup"><sup><xsl:apply-templates/></sup></xsl:template>
	<xsl:template match="sub"><sub><xsl:apply-templates/></sub></xsl:template>
	<xsl:template match="monospace"><code><xsl:apply-templates/></code></xsl:template>

	<xsl:template match="list[@list-type='order']"><ol><xsl:apply-templates/></ol></xsl:template>
	<xsl:template match="list"><ul><xsl:apply-templates/></ul></xsl:template>
	<xsl:template match="list-item"><li><xsl:apply-templates/></li></xsl:template>

	<xsl:template match="ext-link | uri">
		<a href="{@xlink:href}"><xsl:apply-templates/></a>
	</xsl:template>

	<xsl:template match="xref">
		<a href="#{@rid}"><xsl:apply-templates/></a>
	</xsl:template>

	<!-- File references: these attributes are rewritten by the plugin -->
	<xsl:template match="fig">
		<figure id="{@id}">
			<img src="{graphic/@xlink:href}" alt="{label}"/>
			<object data="{graphic/@xlink:href}" type="image/png" width="60" height="40"></object>
			<figcaption>
				<strong><xsl:value-of select="label"/></strong>
				<xsl:text> </xsl:text>
				<xsl:apply-templates select="caption/title | caption/p"/>
			</figcaption>
		</figure>
	</xsl:template>

	<xsl:template match="caption/title"><xsl:apply-templates/><xsl:text> </xsl:text></xsl:template>
	<xsl:template match="caption/p"><xsl:apply-templates/></xsl:template>

	<xsl:template match="inline-graphic">
		<img src="{@xlink:href}" alt="inline graphic"/>
	</xsl:template>

	<xsl:template match="supplementary-material | inline-supplementary-material">
		<a href="{@xlink:href}" class="supplementary">
			<xsl:choose>
				<xsl:when test="label"><xsl:value-of select="label"/></xsl:when>
				<xsl:otherwise><xsl:apply-templates/></xsl:otherwise>
			</xsl:choose>
		</a>
	</xsl:template>

	<!-- Tables -->
	<xsl:template match="table-wrap">
		<div class="table" id="{@id}">
			<p><strong><xsl:value-of select="label"/></strong><xsl:text> </xsl:text><xsl:value-of select="caption/title"/></p>
			<xsl:copy-of select="table"/>
		</div>
	</xsl:template>

	<!-- Formulas: copy MathML through untouched -->
	<xsl:template match="inline-formula | disp-formula">
		<span class="formula"><xsl:copy-of select="mml:math"/><xsl:value-of select="tex-math"/></span>
	</xsl:template>

	<!-- Back matter -->
	<xsl:template match="ref-list">
		<section class="references">
			<h2>References</h2>
			<ol>
				<xsl:for-each select="ref">
					<li id="{@id}"><xsl:value-of select="normalize-space(nlm-citation | citation)"/></li>
				</xsl:for-each>
			</ol>
		</section>
	</xsl:template>

	<!-- Already shown by the templates above, or not needed -->
	<xsl:template match="label | fn | table-wrap-foot"/>
</xsl:stylesheet>
