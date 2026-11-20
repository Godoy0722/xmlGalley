<?php

/**
 * @file plugins/generic/xmlGalley/tests/XMLGalleyRendererTest.php
 *
 * Copyright (c) 2013-2026 Simon Fraser University
 * Copyright (c) 2003-2026 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class XMLGalleyRendererTest
 * @ingroup plugins_generic_xmlGalley
 *
 * @brief Tests for XMLGalleyRenderer.
 */

import('lib.pkp.tests.PKPTestCase');
require_once(dirname(__DIR__) . '/XMLGalleyRenderer.inc.php');

class XMLGalleyRendererTest extends PKPTestCase {
	/** @var XMLGalleyRenderer */
	private $renderer;

	protected function setUp() : void {
		parent::setUp();
		$this->renderer = new XMLGalleyRenderer();
	}

	private function fixture($name) {
		return __DIR__ . '/fixtures/' . $name;
	}

	private function nlmStylesheet() {
		return dirname(__DIR__) . '/transform/nlm/nlm-xhtml.xsl';
	}

	public function testTransformRendersNlmArticleToHtml() {
		$html = $this->renderer->transform($this->fixture('article.xml'), $this->nlmStylesheet());
		$this->assertStringContainsString('<h3 class="article-title">Sample Title</h3>', $html);
		$this->assertStringContainsString('<img src="figure1.png">', $html);
	}

	public function testTransformReturnsFalseForMissingFile() {
		$this->assertFalse($this->renderer->transform($this->fixture('missing.xml'), $this->nlmStylesheet()));
	}

	/**
	 * Run $callback with PHP warnings muted, as in production (logged, not thrown).
	 * @return mixed What $callback returns
	 */
	private function withoutWarnings(callable $callback) {
		set_error_handler(function () { return true; });
		try {
			return $callback();
		} finally {
			restore_error_handler();
		}
	}

	public function testTransformReturnsFalseForMissingStylesheet() {
		$this->assertFalse($this->renderer->transform($this->fixture('article.xml'), $this->fixture('missing.xsl')));
	}

	public function testTransformReturnsFalseForBrokenStylesheet() {
		$result = $this->withoutWarnings(function () {
			return $this->renderer->transform($this->fixture('article.xml'), $this->fixture('broken.xsl'));
		});
		$this->assertFalse($result);
	}

	public function testTransformReturnsFalseWhenStylesheetOutputsNothing() {
		$this->assertFalse($this->renderer->transform($this->fixture('article.xml'), $this->fixture('empty-output.xsl')));
	}

	public function testTransformRepairsBrokenXml() {
		// Accepted behaviour: core XSLTransformer recovers malformed XML instead of failing.
		$html = $this->withoutWarnings(function () {
			return $this->renderer->transform($this->fixture('broken.xml'), $this->nlmStylesheet());
		});
		$this->assertStringContainsString('Broken &amp; unclosed', $html);
		$this->assertStringContainsString('<strong>never closed</strong>', $html);
	}

	public function testReplaceFileReferencesRewritesMatchingNamesOnly() {
		$html = '<img src="figure1.png"><img src="images/figure1.png"><img src="otherfigure1.png"><a HREF="data.csv">x</a>';
		$result = $this->renderer->replaceFileReferences($html, [
			'figure1.png' => 'http://ex/f/1',
			'data.csv' => 'http://ex/f/2?a=1&b=$1',
		]);
		$this->assertSame(
			'<img src="http://ex/f/1"><img src="http://ex/f/1"><img src="otherfigure1.png"><a HREF="http://ex/f/2?a=1&b=$1">x</a>',
			$result
		);
	}

	public function testReplaceFileReferencesMatchesUrlEncodedNames() {
		$result = $this->renderer->replaceFileReferences('<img src="my%20figure.png">', ['my figure.png' => 'http://ex/f/3']);
		$this->assertSame('<img src="http://ex/f/3">', $result);
	}

	public function testWrapDocumentBuildsHtmlPageWithStylesheets() {
		$doc = $this->renderer->wrapDocument('<p>Body</p>', 'A & B', ['http://ex/style.css?a=1&amp;b=2']);
		$this->assertStringStartsWith('<!DOCTYPE html>', $doc);
		$this->assertStringContainsString('<head>', $doc);
		$this->assertStringContainsString('<title>A &amp; B</title>', $doc);
		$this->assertStringContainsString('<link rel="stylesheet" href="http://ex/style.css?a=1&amp;b=2" type="text/css" />', $doc);
		$this->assertStringContainsString("<body>\n<p>Body</p>\n</body>", $doc);
	}

	public function testIsValidStylesheet() {
		$this->assertTrue($this->renderer->isValidStylesheet($this->nlmStylesheet()));
		$this->assertFalse($this->renderer->isValidStylesheet($this->fixture('article.xml')));
		$this->assertFalse($this->renderer->isValidStylesheet($this->fixture('broken.xsl')));
		$this->assertFalse($this->renderer->isValidStylesheet($this->fixture('missing.xsl')));
	}

	public function testIsValidStylesheetRejectsEmptyFile() {
		$emptyFile = tempnam(sys_get_temp_dir(), 'xmlGalleyEmpty');
		try {
			$this->assertFalse($this->renderer->isValidStylesheet($emptyFile));
		} finally {
			unlink($emptyFile);
		}
	}
}
