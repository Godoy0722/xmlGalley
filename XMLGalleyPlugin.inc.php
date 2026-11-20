<?php

/**
 * @file plugins/generic/xmlGalley/XMLGalleyPlugin.inc.php
 *
 * Copyright (c) 2013-2026 Simon Fraser University
 * Copyright (c) 2003-2026 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class XMLGalleyPlugin
 * @ingroup plugins_generic_xmlGalley
 *
 * @brief Renders XML article galleys as HTML using XSLT.
 */

import('lib.pkp.classes.plugins.GenericPlugin');

class XMLGalleyPlugin extends GenericPlugin {

	const XML_MIMETYPES = ['text/xml', 'application/xml'];
	const CACHE_CONTEXT = 'xmlGalley';
	const CUSTOM_XSL_FILE = 'xmlGalley/custom.xsl';

	/**
	 * @copydoc Plugin::register()
	 */
	function register($category, $path, $mainContextId = null) {
		if (!parent::register($category, $path, $mainContextId)) return false;
		if ($this->getEnabled($mainContextId)) {
			HookRegistry::register('ArticleHandler::view::galley', [$this, 'articleViewCallback'], HOOK_SEQUENCE_LATE);
			HookRegistry::register('ArticleHandler::download', [$this, 'articleDownloadCallback'], HOOK_SEQUENCE_LATE);
		}
		return true;
	}

	/**
	 * @copydoc Plugin::getDisplayName()
	 */
	function getDisplayName() {
		return __('plugins.generic.xmlGalley.displayName');
	}

	/**
	 * @copydoc Plugin::getDescription()
	 */
	function getDescription() {
		return __('plugins.generic.xmlGalley.description');
	}

	/**
	 * @copydoc Plugin::getActions()
	 */
	function getActions($request, $verb) {
		$router = $request->getRouter();
		import('lib.pkp.classes.linkAction.request.AjaxModal');
		return array_merge(
			$this->getEnabled() ? [
				new LinkAction(
					'settings',
					new AjaxModal(
						$router->url($request, null, null, 'manage', null, ['verb' => 'settings', 'plugin' => $this->getName(), 'category' => 'generic']),
						$this->getDisplayName()
					),
					__('manager.plugins.settings'),
					null
				),
			] : [],
			parent::getActions($request, $verb)
		);
	}

	/**
	 * @copydoc Plugin::manage()
	 */
	function manage($args, $request) {
		$context = $request->getContext();
		switch ($request->getUserVar('verb')) {
			case 'settings':
				$this->import('XMLGalleySettingsForm');
				$form = new XMLGalleySettingsForm($this, $context->getId(), $request->getUser()->getId());
				if ($request->getUserVar('save')) {
					$form->readInputData();
					if ($form->validate()) {
						$form->execute();
						return new JSONMessage(true);
					}
				} else {
					$form->initData();
				}
				return new JSONMessage(true, $form->fetch($request));

			case 'uploadXSL':
				import('lib.pkp.classes.file.TemporaryFileManager');
				$temporaryFileManager = new TemporaryFileManager();
				$temporaryFile = $temporaryFileManager->handleUpload('uploadedFile', $request->getUser()->getId());
				if (!$temporaryFile) return new JSONMessage(false, __('common.uploadFailed'));
				$json = new JSONMessage(true);
				$json->setAdditionalAttributes(['temporaryFileId' => $temporaryFile->getId()]);
				return $json;
		}
		return parent::manage($args, $request);
	}

	/**
	 * Present the article wrapper page for XML galleys.
	 * @param $hookName string
	 * @param $args array [$request, $issue, $galley, $article, $publication]
	 * @return boolean
	 */
	function articleViewCallback($hookName, $args) {
		$request =& $args[0];
		$issue =& $args[1];
		$galley =& $args[2];
		$article =& $args[3];

		if (!$this->isXmlGalley($galley)) return false;

		$galleyPublication = null;
		foreach ($article->getData('publications') as $publication) {
			if ($publication->getId() === $galley->getData('publicationId')) {
				$galleyPublication = $publication;
				break;
			}
		}
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign([
			'issue' => $issue,
			'article' => $article,
			'galley' => $galley,
			'isLatestPublication' => $article->getData('currentPublicationId') === $galley->getData('publicationId'),
			'galleyPublication' => $galleyPublication,
		]);
		$templateMgr->display($this->getTemplateResource('display.tpl'));
		return true;
	}

	/**
	 * Serve the XSLT-rendered HTML instead of the raw XML file.
	 * Falls back to the core download (raw XML) when the transform fails.
	 * @param $hookName string
	 * @param $args array [$article, $galley, $fileId]
	 * @return boolean
	 */
	function articleDownloadCallback($hookName, $args) {
		$article =& $args[0];
		$galley =& $args[1];
		$fileId =& $args[2];

		if (!$this->isXmlGalley($galley) || $galley->getData('submissionFileId') != $fileId) return false;

		$request = Application::get()->getRequest();
		$html = $this->getGalleyHtml($request, $article, $galley);
		if ($html === false) return false;

		header('Content-Type: text/html; charset=utf-8');
		header('Content-Length: ' . strlen($html));
		header('Content-Disposition: inline');
		header('Cache-Control: private');
		echo $html;

		$returner = true;
		HookRegistry::call('FileManager::downloadFileFinished', [&$returner]);
		return true;
	}

	/**
	 * @param $galley ArticleGalley|null
	 * @return boolean True iff the galley's file is XML.
	 */
	function isXmlGalley($galley) {
		if (!$galley) return false;
		$submissionFile = $galley->getFile();
		return $submissionFile && in_array($submissionFile->getData('mimetype'), self::XML_MIMETYPES);
	}

	/**
	 * Build the complete HTML document for an XML galley.
	 * @param $request PKPRequest
	 * @param $article Submission
	 * @param $galley ArticleGalley
	 * @return string|false
	 */
	function getGalleyHtml($request, $article, $galley) {
		$context = $request->getContext();
		$submissionFile = $galley->getFile();

		$this->import('XMLGalleyRenderer');
		$renderer = new XMLGalleyRenderer();

		$fragment = $this->getCachedFragment($context->getId(), $submissionFile, $renderer);
		if (!is_string($fragment)) return false;

		import('lib.pkp.classes.submission.SubmissionFile'); // SUBMISSION_FILE_DEPENDENT
		$dependentFiles = iterator_to_array(Services::get('submissionFile')->getMany([
			'assocTypes' => [ASSOC_TYPE_SUBMISSION_FILE],
			'assocIds' => [$submissionFile->getId()],
			'fileStages' => [SUBMISSION_FILE_DEPENDENT],
			'includeDependentFiles' => true,
		]));

		$urlsByFileName = [];
		$stylesheetUrls = [];
		foreach ($dependentFiles as $dependentFile) {
			$name = $dependentFile->getLocalizedData('name');
			$mimetype = $dependentFile->getData('mimetype');
			$params = in_array($mimetype, ['text/css', 'text/plain']) ? ['inline' => 'true'] : [];
			$url = $request->url(null, 'article', 'download', [
				$article->getBestId(), 'version', $galley->getData('publicationId'),
				$galley->getBestGalleyId(), $dependentFile->getId(), $name,
			], $params);
			$urlsByFileName[$name] = $url;
			if ($mimetype === 'text/css') $stylesheetUrls[] = $url;
		}

		$html = $renderer->replaceFileReferences($fragment, $urlsByFileName);
		$html = $renderer->wrapDocument($html, $article->getLocalizedTitle(), $stylesheetUrls);
		return TemplateManager::getManager($request)->loadHtmlGalleyStyles($html, $dependentFiles);
	}

	/**
	 * Get the XSLT output for a galley file, transforming on cache miss.
	 * A new file revision gets a new fileId, so stale entries are never hit.
	 * @param $contextId int
	 * @param $submissionFile SubmissionFile
	 * @param $renderer XMLGalleyRenderer
	 * @return string|null Null when the transform failed.
	 */
	function getCachedFragment($contextId, $submissionFile, $renderer) {
		$xmlPath = rtrim(Config::getVar('files', 'files_dir'), '/') . '/' . $submissionFile->getData('path');
		$xslPath = $this->getStylesheetPath($contextId);
		$cacheId = $contextId . '-' . $submissionFile->getId() . '-' . $submissionFile->getData('fileId');

		$cache = CacheManager::getManager()->getFileCache(self::CACHE_CONTEXT, $cacheId,
			function ($cache, $id) use ($renderer, $xmlPath, $xslPath) {
				$fragment = $renderer->transform($xmlPath, $xslPath);
				if ($fragment !== false) $cache->setEntireCache($fragment);
				return null;
			}
		);
		return $cache->getContents();
	}

	/**
	 * Drop all cached XSLT output of one journal.
	 * @param $contextId int
	 */
	function flushCache($contextId) {
		CacheManager::getManager()->flush(self::CACHE_CONTEXT . '-' . (int) $contextId, CACHE_TYPE_FILE);
	}

	/**
	 * Stylesheet to use for a journal: custom when selected and present, else built-in NLM.
	 * @param $contextId int
	 * @return string Absolute path
	 */
	function getStylesheetPath($contextId) {
		if ($this->getSetting($contextId, 'XSLstylesheet') === 'custom') {
			$customPath = $this->getStoredCustomStylesheetPath($contextId);
			if ($customPath) return $customPath;
		}
		return Core::getBaseDir() . '/' . $this->getPluginPath() . '/transform/nlm/nlm-xhtml.xsl';
	}

	/**
	 * Existing custom XSL of a journal, whether or not it is currently selected.
	 * @param $contextId int
	 * @return string|null Absolute path, or null when none is stored.
	 */
	function getStoredCustomStylesheetPath($contextId) {
		$customPath = $this->getCustomStylesheetPath($contextId);
		if (file_exists($customPath)) return $customPath;

		// Journals upgraded from OJS 2 keep the file in the journal files dir root.
		$legacyName = basename((string) $this->getSetting($contextId, 'customXSL'));
		if ($legacyName !== '') {
			$legacyPath = $this->_getContextFilesPath($contextId) . $legacyName;
			if (file_exists($legacyPath)) return $legacyPath;
		}
		return null;
	}

	/**
	 * @param $contextId int
	 * @return string Absolute path where an uploaded custom XSL is stored.
	 */
	function getCustomStylesheetPath($contextId) {
		return $this->_getContextFilesPath($contextId) . self::CUSTOM_XSL_FILE;
	}

	/**
	 * @param $contextId int
	 * @return string Journal files directory, with trailing slash.
	 */
	function _getContextFilesPath($contextId) {
		import('lib.pkp.classes.file.ContextFileManager');
		$contextFileManager = new ContextFileManager($contextId);
		return $contextFileManager->getBasePath();
	}
}
