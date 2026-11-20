<?php

/**
 * @file plugins/generic/xmlGalley/XMLGalleySettingsForm.inc.php
 *
 * Copyright (c) 2013-2026 Simon Fraser University
 * Copyright (c) 2003-2026 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class XMLGalleySettingsForm
 * @ingroup plugins_generic_xmlGalley
 *
 * @brief Journal manager form: choose the XSL stylesheet and upload a custom one.
 */

import('lib.pkp.classes.form.Form');

class XMLGalleySettingsForm extends Form {
	/** @var XMLGalleyPlugin */
	private $_plugin;

	/** @var int */
	private $_contextId;

	/** @var int Current user; only their temporary uploads are accepted. */
	private $_userId;

	/**
	 * @param $plugin XMLGalleyPlugin
	 * @param $contextId int
	 * @param $userId int
	 */
	function __construct($plugin, $contextId, $userId) {
		$this->_plugin = $plugin;
		$this->_contextId = $contextId;
		$this->_userId = $userId;
		parent::__construct($plugin->getTemplateResource('settingsForm.tpl'));

		$this->addCheck(new FormValidatorInSet($this, 'XSLstylesheet', 'required', 'plugins.generic.xmlGalley.settings.stylesheetRequired', ['NLM', 'custom']));
		$this->addCheck(new FormValidatorCustom($this, 'temporaryFileId', 'optional', 'plugins.generic.xmlGalley.settings.customXSLInvalid', [$this, 'isValidTemporaryFile']));
		$this->addCheck(new FormValidatorCustom($this, 'XSLstylesheet', 'required', 'plugins.generic.xmlGalley.settings.customXSLRequired', [$this, 'hasCustomStylesheetIfSelected']));
		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorCSRF($this));
	}

	/**
	 * @copydoc Form::initData()
	 */
	function initData() {
		$this->_data = [
			'XSLstylesheet' => $this->_plugin->getSetting($this->_contextId, 'XSLstylesheet') === 'custom' ? 'custom' : 'NLM',
		];
	}

	/**
	 * @copydoc Form::readInputData()
	 */
	function readInputData() {
		$this->readUserVars(['XSLstylesheet', 'temporaryFileId']);
	}

	/**
	 * @copydoc Form::fetch()
	 */
	function fetch($request, $template = null, $display = false) {
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign([
			'pluginName' => $this->_plugin->getName(),
			'isCustom' => $this->getData('XSLstylesheet') === 'custom',
			'customXSL' => $this->_plugin->getSetting($this->_contextId, 'customXSL'),
		]);
		return parent::fetch($request, $template, $display);
	}

	/**
	 * Uploaded temporary file must be an .xsl that parses as an XSLT stylesheet.
	 * @param $temporaryFileId int
	 * @return boolean
	 */
	function isValidTemporaryFile($temporaryFileId) {
		$temporaryFile = $this->_getTemporaryFile($temporaryFileId);
		if (!$temporaryFile) return false;
		if (strtolower(pathinfo($temporaryFile->getOriginalFileName(), PATHINFO_EXTENSION)) !== 'xsl') return false;
		$this->_plugin->import('XMLGalleyRenderer');
		$renderer = new XMLGalleyRenderer();
		return $renderer->isValidStylesheet($temporaryFile->getFilePath());
	}

	/**
	 * Selecting "custom" requires a new upload or an already stored stylesheet.
	 * @param $choice string
	 * @return boolean
	 */
	function hasCustomStylesheetIfSelected($choice) {
		if ($choice !== 'custom') return true;
		if ($this->getData('temporaryFileId')) return true;
		return $this->_plugin->getStoredCustomStylesheetPath($this->_contextId) !== null;
	}

	/**
	 * @copydoc Form::execute()
	 */
	function execute(...$functionArgs) {
		$temporaryFileId = $this->getData('temporaryFileId');
		if ($temporaryFileId) {
			$temporaryFile = $this->_getTemporaryFile($temporaryFileId);
			$target = $this->_plugin->getCustomStylesheetPath($this->_contextId);

			import('lib.pkp.classes.file.FileManager');
			$fileManager = new FileManager();
			if (!$fileManager->fileExists(dirname($target), 'dir')) $fileManager->mkdirtree(dirname($target));
			if (!$fileManager->copyFile($temporaryFile->getFilePath(), $target)) {
				throw new Exception('Could not store the custom XSL stylesheet at ' . $target);
			}
			$this->_plugin->updateSetting($this->_contextId, 'customXSL', $temporaryFile->getOriginalFileName(), 'string');

			import('lib.pkp.classes.file.TemporaryFileManager');
			$temporaryFileManager = new TemporaryFileManager();
			$temporaryFileManager->deleteById($temporaryFile->getId(), $temporaryFile->getUserId());
		}

		$this->_plugin->updateSetting($this->_contextId, 'XSLstylesheet', $this->getData('XSLstylesheet'), 'string');
		$this->_plugin->flushCache($this->_contextId);
		parent::execute(...$functionArgs);
	}

	/**
	 * @param $temporaryFileId int
	 * @return TemporaryFile|null Only files uploaded by the current user.
	 */
	private function _getTemporaryFile($temporaryFileId) {
		$temporaryFileDao = DAORegistry::getDAO('TemporaryFileDAO'); /* @var $temporaryFileDao TemporaryFileDAO */
		return $temporaryFileDao->getTemporaryFile((int) $temporaryFileId, $this->_userId);
	}
}
