<?php

/**
 * @file plugins/generic/xmlGalley/tests/XMLGalleyPluginTest.php
 *
 * Copyright (c) 2013-2026 Simon Fraser University
 * Copyright (c) 2003-2026 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class XMLGalleyPluginTest
 * @ingroup plugins_generic_xmlGalley
 *
 * @brief Tests for XMLGalleyPlugin galley detection, stylesheet choice and caching.
 */

import('lib.pkp.tests.PKPTestCase');
import('classes.article.ArticleGalley');
import('lib.pkp.classes.submission.SubmissionFile');
require_once(dirname(__DIR__) . '/XMLGalleyPlugin.inc.php');
require_once(dirname(__DIR__) . '/XMLGalleyRenderer.inc.php');

class XMLGalleyPluginTest extends PKPTestCase {
	/** Journal ids that never exist, so cache files can't clash with real ones. */
	const CONTEXT_ID = 999901;
	const OTHER_CONTEXT_ID = 999902;

	/** @var string Fake journal files directory, with trailing slash */
	private $filesDir;

	/** @var array Fake plugin settings: name => value */
	private $settings;

	protected function setUp() : void {
		parent::setUp();
		$this->filesDir = sys_get_temp_dir() . '/xmlGalleyTest-' . uniqid() . '/';
		mkdir($this->filesDir . 'xmlGalley', 0777, true);
		$this->settings = [];
	}

	protected function tearDown() : void {
		$cacheManager = CacheManager::getManager();
		foreach ([self::CONTEXT_ID, self::OTHER_CONTEXT_ID] as $contextId) {
			$cacheManager->flush(XMLGalleyPlugin::CACHE_CONTEXT . '-' . $contextId, CACHE_TYPE_FILE);
		}
		$files = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($this->filesDir, FilesystemIterator::SKIP_DOTS),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ($files as $file) {
			$file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
		}
		rmdir($this->filesDir);
		parent::tearDown();
	}

	/**
	 * Plugin with settings and journal files directory replaced by fakes.
	 * @return XMLGalleyPlugin
	 */
	private function makePlugin($extraMockedMethods = []) {
		$plugin = $this->getMockBuilder(XMLGalleyPlugin::class)
			->onlyMethods(array_merge(['getSetting', 'getPluginPath', '_getContextFilesPath'], $extraMockedMethods))
			->getMock();
		$plugin->method('getSetting')->willReturnCallback(function ($contextId, $name) {
			return $this->settings[$name] ?? null;
		});
		$plugin->method('getPluginPath')->willReturn('plugins/generic/xmlGalley');
		$plugin->method('_getContextFilesPath')->willReturn($this->filesDir);
		return $plugin;
	}

	private function builtInStylesheet() {
		return Core::getBaseDir() . '/plugins/generic/xmlGalley/transform/nlm/nlm-xhtml.xsl';
	}

	/**
	 * @param $mimetype string|null Galley file type; null = galley without file
	 * @param $submissionFileId int Id of the galley's own file
	 * @return ArticleGalley
	 */
	private function makeGalley($mimetype, $submissionFileId = 5) {
		$submissionFile = null;
		if ($mimetype !== null) {
			$submissionFile = new SubmissionFile();
			$submissionFile->setData('mimetype', $mimetype);
		}
		$galley = $this->createMock(ArticleGalley::class);
		$galley->method('getFile')->willReturn($submissionFile);
		$galley->method('getData')->willReturnCallback(function ($key) use ($submissionFileId) {
			return $key === 'submissionFileId' ? $submissionFileId : null;
		});
		return $galley;
	}

	/**
	 * Plugin whose HTML building must be called $times times, returning $html.
	 * @return XMLGalleyPlugin
	 */
	private function makePluginRenderingGalley($times, $html) {
		$plugin = $this->makePlugin(['getGalleyHtml']);
		$plugin->expects($this->exactly($times))->method('getGalleyHtml')->willReturn($html);
		return $plugin;
	}

	public function testDownloadIgnoresNonXmlGalley() {
		$plugin = $this->makePluginRenderingGalley(0, '<html></html>');
		$this->assertFalse($plugin->articleDownloadCallback('ArticleHandler::download', [null, $this->makeGalley('application/pdf'), 5]));
		$this->assertFalse($plugin->articleDownloadCallback('ArticleHandler::download', [null, null, 5]));
	}

	public function testDownloadLeavesDependentFilesToOjs() {
		// Images/CSS of an XML galley go through the same hook with their own file id.
		$plugin = $this->makePluginRenderingGalley(0, '<html></html>');
		$this->assertFalse($plugin->articleDownloadCallback('ArticleHandler::download', [null, $this->makeGalley('text/xml', 5), 9]));
	}

	public function testDownloadFallsBackToRawXmlWhenRenderingFails() {
		$plugin = $this->makePluginRenderingGalley(1, false);
		$this->expectOutputString('');
		$this->assertFalse($plugin->articleDownloadCallback('ArticleHandler::download', [null, $this->makeGalley('text/xml', 5), 5]));
	}

	public function testViewIgnoresNonXmlGalley() {
		$plugin = $this->makePlugin();
		$this->assertFalse($plugin->articleViewCallback('ArticleHandler::view::galley', [null, null, $this->makeGalley('application/pdf'), null]));
		$this->assertFalse($plugin->articleViewCallback('ArticleHandler::view::galley', [null, null, null, null]));
	}

	private function makeSubmissionFile($submissionFileId, $fileId) {
		$submissionFile = new SubmissionFile();
		$submissionFile->setId($submissionFileId);
		$submissionFile->setData('fileId', $fileId);
		$submissionFile->setData('path', 'journals/1/articles/1/article.xml');
		return $submissionFile;
	}

	/**
	 * Renderer whose transform() must be called exactly $times times.
	 * @return XMLGalleyRenderer
	 */
	private function makeRenderer($times, $result) {
		$renderer = $this->createMock(XMLGalleyRenderer::class);
		$renderer->expects($this->exactly($times))->method('transform')->willReturn($result);
		return $renderer;
	}

	public function testIsXmlGalley() {
		$plugin = $this->makePlugin();
		$this->assertTrue($plugin->isXmlGalley($this->makeGalley('text/xml')));
		$this->assertTrue($plugin->isXmlGalley($this->makeGalley('application/xml')));
		$this->assertFalse($plugin->isXmlGalley($this->makeGalley('application/pdf')));
		$this->assertFalse($plugin->isXmlGalley($this->makeGalley('text/html')));
		$this->assertFalse($plugin->isXmlGalley($this->makeGalley(null)), 'galley without file');
		$this->assertFalse($plugin->isXmlGalley(null), 'no galley');
	}

	public function testStylesheetDefaultsToBuiltIn() {
		$this->assertSame($this->builtInStylesheet(), $this->makePlugin()->getStylesheetPath(self::CONTEXT_ID));
	}

	public function testCustomStylesheetUsedWhenSelectedAndStored() {
		touch($this->filesDir . 'xmlGalley/custom.xsl');
		$this->settings['XSLstylesheet'] = 'custom';
		$this->assertSame($this->filesDir . 'xmlGalley/custom.xsl', $this->makePlugin()->getStylesheetPath(self::CONTEXT_ID));
	}

	public function testCustomSelectedButMissingFallsBackToBuiltIn() {
		$this->settings['XSLstylesheet'] = 'custom';
		$this->assertSame($this->builtInStylesheet(), $this->makePlugin()->getStylesheetPath(self::CONTEXT_ID));
	}

	public function testStoredCustomIgnoredWhenBuiltInSelected() {
		touch($this->filesDir . 'xmlGalley/custom.xsl');
		$this->settings['XSLstylesheet'] = 'NLM';
		$this->assertSame($this->builtInStylesheet(), $this->makePlugin()->getStylesheetPath(self::CONTEXT_ID));
	}

	public function testLegacyOjs2StylesheetUsed() {
		touch($this->filesDir . 'legacy.xsl');
		$this->settings = ['XSLstylesheet' => 'custom', 'customXSL' => 'legacy.xsl'];
		$this->assertSame($this->filesDir . 'legacy.xsl', $this->makePlugin()->getStylesheetPath(self::CONTEXT_ID));
	}

	public function testNewLocationWinsOverLegacy() {
		touch($this->filesDir . 'legacy.xsl');
		touch($this->filesDir . 'xmlGalley/custom.xsl');
		$this->settings = ['XSLstylesheet' => 'custom', 'customXSL' => 'legacy.xsl'];
		$this->assertSame($this->filesDir . 'xmlGalley/custom.xsl', $this->makePlugin()->getStylesheetPath(self::CONTEXT_ID));
	}

	public function testLegacyNameCannotLeaveFilesDirectory() {
		touch($this->filesDir . 'legacy.xsl');
		$this->settings = ['customXSL' => '../../somewhere/legacy.xsl'];
		$this->assertSame($this->filesDir . 'legacy.xsl', $this->makePlugin()->getStoredCustomStylesheetPath(self::CONTEXT_ID));
	}

	public function testNoStoredCustomStylesheet() {
		$this->assertNull($this->makePlugin()->getStoredCustomStylesheetPath(self::CONTEXT_ID));
	}

	public function testCachedFragmentTransformsOnlyOnce() {
		$plugin = $this->makePlugin();
		$renderer = $this->makeRenderer(1, '<p>Rendered</p>');
		$submissionFile = $this->makeSubmissionFile(5, 7);
		$this->assertSame('<p>Rendered</p>', $plugin->getCachedFragment(self::CONTEXT_ID, $submissionFile, $renderer));
		$this->assertSame('<p>Rendered</p>', $plugin->getCachedFragment(self::CONTEXT_ID, $submissionFile, $renderer));
	}

	public function testFailedTransformIsNotCached() {
		$plugin = $this->makePlugin();
		$renderer = $this->makeRenderer(2, false);
		$submissionFile = $this->makeSubmissionFile(5, 7);
		$this->assertNull($plugin->getCachedFragment(self::CONTEXT_ID, $submissionFile, $renderer));
		$this->assertNull($plugin->getCachedFragment(self::CONTEXT_ID, $submissionFile, $renderer));
	}

	public function testNewFileRevisionIsTransformedAgain() {
		$plugin = $this->makePlugin();
		$renderer = $this->makeRenderer(2, '<p>Rendered</p>');
		$plugin->getCachedFragment(self::CONTEXT_ID, $this->makeSubmissionFile(5, 7), $renderer);
		$plugin->getCachedFragment(self::CONTEXT_ID, $this->makeSubmissionFile(5, 8), $renderer);
	}

	public function testFlushCacheOnlyClearsOneJournal() {
		$plugin = $this->makePlugin();
		$renderer = $this->makeRenderer(2, '<p>Rendered</p>');
		$plugin->getCachedFragment(self::CONTEXT_ID, $this->makeSubmissionFile(5, 7), $renderer);
		$plugin->getCachedFragment(self::OTHER_CONTEXT_ID, $this->makeSubmissionFile(5, 7), $renderer);

		$plugin->flushCache(self::CONTEXT_ID);

		$cachePath = CacheManager::getFileCachePath() . '/fc-' . XMLGalleyPlugin::CACHE_CONTEXT . '-';
		$this->assertCount(0, glob($cachePath . self::CONTEXT_ID . '-*.php'));
		$this->assertCount(1, glob($cachePath . self::OTHER_CONTEXT_ID . '-*.php'));
	}
}
