<?php

/**
 * @file plugins/generic/xmlGalley/XMLGalleyRenderer.inc.php
 *
 * Copyright (c) 2013-2026 Simon Fraser University
 * Copyright (c) 2003-2026 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class XMLGalleyRenderer
 * @ingroup plugins_generic_xmlGalley
 *
 * @brief Turns an XML galley into a standalone HTML document using XSLT.
 */

import('lib.pkp.classes.xslt.XSLTransformer');

class XMLGalleyRenderer {

	/**
	 * Transform an XML file with an XSL stylesheet using the core XSLT processor
	 * (PHP xsl extension, or [cli] xslt_command in config.inc.php).
	 * @param $xmlPath string Absolute path to the XML source
	 * @param $xslPath string Absolute path to the XSL stylesheet
	 * @return string|false HTML fragment, or false when the transform fails
	 */
	public function transform($xmlPath, $xslPath) {
		$xslTransformer = new XSLTransformer();
		$result = $xslTransformer->transform(
			$xmlPath, XSL_TRANSFORMER_DOCTYPE_FILE,
			$xslPath, XSL_TRANSFORMER_DOCTYPE_FILE,
			XSL_TRANSFORMER_DOCTYPE_STRING
		);
		if (!is_string($result) || trim($result) === '') return false;
		return $result;
	}

	/**
	 * Point src/href/data attributes that name a dependent file at its OJS URL.
	 * Matches the bare file name, optionally preceded by a path, raw or URL-encoded.
	 * @param $html string
	 * @param $urlsByFileName array file name => URL
	 * @return string
	 */
	public function replaceFileReferences($html, $urlsByFileName) {
		foreach ($urlsByFileName as $fileName => $url) {
			$names = array_unique([preg_quote($fileName, '/'), preg_quote(rawurlencode($fileName), '/')]);
			$replaced = preg_replace_callback(
				'/(src|href|data)(\s*=\s*)"(?:[^"]*\/)?(?:' . implode('|', $names) . ')"/i',
				function ($matches) use ($url) {
					return $matches[1] . $matches[2] . '"' . $url . '"';
				},
				$html
			);
			if ($replaced === null) {
				error_log('PREG error in ' . __FILE__ . ' line ' . __LINE__ . ': ' . preg_last_error());
				continue;
			}
			$html = $replaced;
		}
		return $html;
	}

	/**
	 * Wrap an HTML fragment in a complete HTML document.
	 * @param $fragment string
	 * @param $title string Plain-text document title
	 * @param $stylesheetUrls array URLs of CSS files to link
	 * @return string
	 */
	public function wrapDocument($fragment, $title, $stylesheetUrls = []) {
		$links = '';
		foreach ($stylesheetUrls as $url) {
			$links .= '<link rel="stylesheet" href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8', false) . '" type="text/css" />' . "\n";
		}
		return "<!DOCTYPE html>\n<html>\n<head>\n<meta charset=\"utf-8\" />\n"
			. '<title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . "</title>\n"
			. $links
			. "</head>\n<body>\n" . $fragment . "\n</body>\n</html>\n";
	}

	/**
	 * Check that a file is a well-formed XSLT stylesheet.
	 * @param $xslPath string
	 * @return boolean
	 */
	public function isValidStylesheet($xslPath) {
		if (!is_readable($xslPath)) return false;
		$dom = new DOMDocument();
		$previous = libxml_use_internal_errors(true);
		$loaded = $dom->load($xslPath, LIBXML_NONET);
		libxml_clear_errors();
		libxml_use_internal_errors($previous);
		if (!$loaded || !$dom->documentElement) return false;
		return $dom->documentElement->namespaceURI === 'http://www.w3.org/1999/XSL/Transform'
			&& in_array($dom->documentElement->localName, ['stylesheet', 'transform'], true);
	}
}
