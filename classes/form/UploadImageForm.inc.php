<?php

/**
 * @file classes/form/UploadImageForm.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class UploadImageForm
 * @ingroup plugins_importexport_quicksubmit_classes_form
 *
 * @brief Form for upload an image.
 */

use APP\facades\Repo;
use PKP\facades\Locale;
use PKP\form\Form;

class UploadImageForm extends Form
{
    /** string Setting key that will be associated with the uploaded file. */
    public $_fileSettingName;

    /** @var $request object */
    public $request;

    /** @var $submissionId int */
    public $submissionId;

    /** @var $submission Submission */
    public $submission;

    /** @var $publication Publication */
    public $publication;

    /** @var $plugin QuickSubmitPlugin */
    public $plugin;

    /** @var $context Journal */
    public $context;

    /**
     * Constructor.
     * @param $plugin object
     * @param $request object
     */
    public function __construct($plugin, $request)
    {
        parent::__construct($plugin->getTemplateResource('uploadImageForm.tpl'));

        $this->addCheck(new \PKP\form\validation\FormValidator($this, 'temporaryFileId', 'required', 'manager.website.imageFileRequired'));

        $this->plugin = $plugin;
        $this->request = $request;
        $this->context = $request->getContext();

        $this->submissionId = $request->getUserVar('submissionId');

        $this->submission = Repo::submission()->get($request->getUserVar('submissionId'));
        if ($this->submission->getContextId() != $this->context->getId()) {
            throw new Exception('Submission context ID does not match context!');
        }
        $this->publication = $this->submission->getCurrentPublication();
    }

    //
    // Extend methods from Form.
    //
    /**
     * @copydoc Form::getLocaleFieldNames()
     */
    public function getLocaleFieldNames()
    {
        return array('imageAltText');
    }

    /**
     * @copydoc Form::initData()
     */
    public function initData()
    {
        $templateMgr = TemplateManager::getManager($this->request);
        $templateMgr->assign('submissionId', $this->submissionId);

        $locale = Locale::getLocale();
        $coverImage = $this->submission->getCurrentPublication()->getLocalizedData('coverImage') ?? '';

        if ($coverImage) {
            import('lib.pkp.classes.linkAction.LinkAction');
            import('lib.pkp.classes.linkAction.request.RemoteActionConfirmationModal');
            $router = $this->request->getRouter();
            $deleteCoverImageLinkAction = new LinkAction(
                'deleteCoverImage',
                new RemoteActionConfirmationModal(
                    $this->request->getSession(),
                    __('common.confirmDelete'),
                    null,
                    $router->url($this->request, null, null, 'importexport', array('plugin', 'QuickSubmitPlugin', 'deleteCoverImage'), array(
                        'coverImage' => $coverImage['uploadName'],
                        'submissionId' => $this->submission->getId(),
                        'stageId' => WORKFLOW_STAGE_ID_PRODUCTION,
                    )),
                    'modal_delete'
                ),
                __('common.delete'),
                null
            );
            $templateMgr->assign('deleteCoverImageLinkAction', $deleteCoverImageLinkAction);
        }

        $this->setData('coverImage', $coverImage);
        $this->setData('imageAltText', $this->submission->getCoverImageAltText($locale));
        $this->setData('coverImageName', $coverImage['uploadName'] ?? '');
    }

    /**
     * @copydoc Form::readInputData()
     */
    public function readInputData()
    {
        $this->readUserVars(array('imageAltText', 'temporaryFileId'));
    }

    /**
     * An action to delete an article cover image.
     * @param $request PKPRequest
     * @return JSONMessage JSON object
     */
    public function deleteCoverImage($request)
    {
        assert($request->getUserVar('coverImage') != '' && $request->getUserVar('submissionId') != '');

        $publicationDao = DAORegistry::getDAO('PublicationDAO'); /* @var $publicationDao PublicationDAO */
        $file = $request->getUserVar('coverImage');

        // Remove cover image and alt text from article settings
        $locale = AppLocale::getLocale();
        $this->publication->setData('coverImage', []);
        $publicationDao->updateObject($this->publication);

        // Remove the file
        $publicFileManager = new PublicFileManager();
        if ($publicFileManager->removeContextFile($this->submission->getContextId(), $file)) {
            $json = new JSONMessage(true);
            $json->setEvent('fileDeleted');
            return $json;
        } else {
            return new JSONMessage(false, __('editor.article.removeCoverImageFileNotFound'));
        }
    }

    /**
     * @copydoc Form::execute()
     */
    public function execute(...$functionArgs)
    {
        $request = Application::get()->getRequest();
        $publicationDao = DAORegistry::getDAO('PublicationDAO'); /* @var $publicationDao PublicationDAO */

        $temporaryFile = $this->fetchTemporaryFile($request);
        $locale = AppLocale::getLocale();
        $coverImage = $this->publication->getData('coverImage');

        import('classes.file.PublicFileManager');
        $publicFileManager = new PublicFileManager();

        if (is_a($temporaryFile, 'TemporaryFile')) {
            $type = $temporaryFile->getFileType();
            $extension = $publicFileManager->getImageExtension($type);
            if (!$extension) {
                return false;
            }
            $locale = AppLocale::getLocale();

            $newFileName = 'book_' . $this->submissionId . '_cover_' . $locale . $publicFileManager->getImageExtension($temporaryFile->getFileType());

            if ($publicFileManager->copyContextFile($this->context->getId(), $temporaryFile->getFilePath(), $newFileName)) {

                $this->publication->setData('coverImage', [
                    'altText' => $this->getData('imageAltText'),
                    'uploadName' => $newFileName,
                ], $locale);
                $publicationDao->updateObject($this->publication);

                // Clean up the temporary file.
                $this->removeTemporaryFile($request);

                return DAO::getDataChangedEvent();
            }
        } elseif ($coverImage) {
            $coverImage = $this->publication->getData('coverImage');
            $coverImage[$locale]['altText'] = $this->getData('imageAltText');
            $this->publication->setData('coverImage', $coverImage);
            $publicationDao->updateObject($this->publication);
            return DAO::getDataChangedEvent();
        }
        return new JSONMessage(false, __('common.uploadFailed'));

    }

    /**
     * Get the image that this form will upload a file to.
     * @return string
     */
    public function getFileSettingName()
    {
        return $this->_fileSettingName;
    }

    /**
     * Set the image that this form will upload a file to.
     * @param $image string
     */
    public function setFileSettingName($fileSettingName)
    {
        $this->_fileSettingName = $fileSettingName;
    }


    //
    // Implement template methods from Form.
    //
    /**
     * @see Form::fetch()
     * @param $params template parameters
     */
    public function fetch($request, $template = null, $display = false, $params = null)
    {
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign(array(
            'fileSettingName' => $this->getFileSettingName(),
            'fileType' => 'image',
        ));

        return parent::fetch($request, $template, $display);
    }


    //
    // Public methods
    //
    /**
     * Fecth the temporary file.
     * @param $request Request
     * @return TemporaryFile
     */
    public function fetchTemporaryFile($request)
    {
        $user = $request->getUser();

        $temporaryFileDao = DAORegistry::getDAO('TemporaryFileDAO');
        $temporaryFile = $temporaryFileDao->getTemporaryFile(
            $this->getData('temporaryFileId'),
            $user->getId()
        );
        return $temporaryFile;
    }

    /**
     * Clean temporary file.
     * @param $request Request
     */
    public function removeTemporaryFile($request)
    {
        $user = $request->getUser();

        import('lib.pkp.classes.file.TemporaryFileManager');
        $temporaryFileManager = new TemporaryFileManager();
        $temporaryFileManager->deleteById($this->getData('temporaryFileId'), $user->getId());
    }

    /**
     * Upload a temporary file.
     * @param $request Request
     */
    public function uploadFile($request)
    {
        $user = $request->getUser();

        import('lib.pkp.classes.file.TemporaryFileManager');
        $temporaryFileManager = new TemporaryFileManager();
        $temporaryFile = $temporaryFileManager->handleUpload('uploadedFile', $user->getId());

        if ($temporaryFile) {
            return $temporaryFile->getId();
        }

        return false;
    }
}
