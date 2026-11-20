<?php

/**
 * @file plugins/generic/xmlGalley/tests/XMLGalleySettingsFormTest.php
 *
 * Copyright (c) 2013-2026 Simon Fraser University
 * Copyright (c) 2003-2026 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class XMLGalleySettingsFormTest
 * @ingroup plugins_generic_xmlGalley
 *
 * @brief Tests for the XMLGalleySettingsForm validation rules.
 */

import('lib.pkp.tests.PKPTestCase');
import('lib.pkp.classes.file.TemporaryFile');
import('lib.pkp.classes.file.TemporaryFileDAO');
require_once(dirname(__DIR__) . '/XMLGalleyPlugin.inc.php');
require_once(dirname(__DIR__) . '/XMLGalleyRenderer.inc.php');
require_once(dirname(__DIR__) . '/XMLGalleySettingsForm.inc.php');

class XMLGalleySettingsFormTest extends PKPTestCase {
	const USER_ID = 3;

	/**
	 * @copydoc PKPTestCase::getMockedDAOs()
	 */
	protected function getMockedDAOs() {
		return ['TemporaryFileDAO'];
	}

	/**
	 * Build the form without its constructor, which needs locale/site data from the database.
	 * Only the validation methods are exercised here.
	 * @param $storedCustomPath string|null What the plugin reports as stored custom XSL
	 * @param $userId int Current user
	 * @return XMLGalleySettingsForm
	 */
	private function makeForm($storedCustomPath = null, $userId = self::USER_ID, $plugin = null) {
		if (!$plugin) {
			$plugin = $this->createMock(XMLGalleyPlugin::class);
			$plugin->method('getStoredCustomStylesheetPath')->willReturn($storedCustomPath);
		}

		$reflection = new ReflectionClass(XMLGalleySettingsForm::class);
		$form = $reflection->newInstanceWithoutConstructor();
		foreach (['_plugin' => $plugin, '_contextId' => 22, '_userId' => $userId] as $name => $value) {
			$property = $reflection->getProperty($name);
			$property->setAccessible(true);
			$property->setValue($form, $value);
		}
		$form->_data = [];
		return $form;
	}

	/**
	 * Register a fake TemporaryFileDAO holding files owned by USER_ID.
	 * @param $files array temporaryFileId => [original name, path on disk]
	 */
	private function registerTemporaryFiles($files) {
		$temporaryFiles = [];
		foreach ($files as $id => list($originalName, $path)) {
			$temporaryFile = $this->createMock(TemporaryFile::class);
			$temporaryFile->method('getOriginalFileName')->willReturn($originalName);
			$temporaryFile->method('getFilePath')->willReturn($path);
			$temporaryFiles[$id] = $temporaryFile;
		}
		$dao = $this->createMock(TemporaryFileDAO::class);
		$dao->method('getTemporaryFile')->willReturnCallback(function ($id, $userId) use ($temporaryFiles) {
			return $userId === self::USER_ID ? ($temporaryFiles[$id] ?? null) : null;
		});
		DAORegistry::registerDAO('TemporaryFileDAO', $dao);
	}

	private function validXsl() {
		return dirname(__DIR__) . '/transform/nlm/nlm-xhtml.xsl';
	}

	public function testBuiltInChoiceNeedsNoFile() {
		$this->assertTrue($this->makeForm()->hasCustomStylesheetIfSelected('NLM'));
	}

	public function testCustomChoiceAcceptedWithNewUpload() {
		$form = $this->makeForm();
		$form->setData('temporaryFileId', 10);
		$this->assertTrue($form->hasCustomStylesheetIfSelected('custom'));
	}

	public function testCustomChoiceAcceptedWithStoredStylesheet() {
		$this->assertTrue($this->makeForm('/files/journals/22/xmlGalley/custom.xsl')->hasCustomStylesheetIfSelected('custom'));
	}

	public function testCustomChoiceRejectedWithoutAnyStylesheet() {
		$this->assertFalse($this->makeForm()->hasCustomStylesheetIfSelected('custom'));
	}

	public function testValidXslUploadAccepted() {
		$this->registerTemporaryFiles([10 => ['custom.xsl', $this->validXsl()], 11 => ['CUSTOM.XSL', $this->validXsl()]]);
		$form = $this->makeForm();
		$this->assertTrue($form->isValidTemporaryFile(10));
		$this->assertTrue($form->isValidTemporaryFile(11), 'extension check ignores case');
	}

	public function testBrokenXslRejected() {
		$this->registerTemporaryFiles([10 => ['broken.xsl', __DIR__ . '/fixtures/broken.xsl']]);
		$this->assertFalse($this->makeForm()->isValidTemporaryFile(10));
	}

	public function testWrongExtensionRejected() {
		$this->registerTemporaryFiles([10 => ['style.css', $this->validXsl()], 11 => ['custom.xsl.txt', $this->validXsl()]]);
		$form = $this->makeForm();
		$this->assertFalse($form->isValidTemporaryFile(10));
		$this->assertFalse($form->isValidTemporaryFile(11));
	}

	public function testUnknownTemporaryFileRejected() {
		$this->registerTemporaryFiles([]);
		$this->assertFalse($this->makeForm()->isValidTemporaryFile(99));
	}

	public function testSaveFailsWithoutPartialChangesWhenStylesheetCannotBeStored() {
		$this->registerTemporaryFiles([10 => ['custom.xsl', $this->validXsl()]]);

		// Target directory can't be created: its parent is a regular file.
		$blocker = tempnam(sys_get_temp_dir(), 'xmlGalleyBlocker');
		$plugin = $this->createMock(XMLGalleyPlugin::class);
		$plugin->method('getCustomStylesheetPath')->willReturn($blocker . '/xmlGalley/custom.xsl');
		$plugin->expects($this->never())->method('updateSetting');
		$plugin->expects($this->never())->method('flushCache');

		$form = $this->makeForm(null, self::USER_ID, $plugin);
		$form->setData('temporaryFileId', 10);
		$form->setData('XSLstylesheet', 'custom');

		set_error_handler(function () { return true; }); // mkdir/copy warnings are only logged in production
		try {
			$form->execute();
			$this->fail('Expected an exception when the stylesheet cannot be stored.');
		} catch (Exception $e) {
			$this->assertStringContainsString('Could not store the custom XSL stylesheet', $e->getMessage());
		} finally {
			restore_error_handler();
			unlink($blocker);
		}
	}

	public function testOtherUsersUploadRejected() {
		$this->registerTemporaryFiles([10 => ['custom.xsl', $this->validXsl()]]);
		$this->assertFalse($this->makeForm(null, self::USER_ID + 1)->isValidTemporaryFile(10));
	}
}
