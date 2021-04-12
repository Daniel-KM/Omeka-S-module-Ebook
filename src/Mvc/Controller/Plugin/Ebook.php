<?php declare(strict_types=1);

/*
 * Copyright 2017-2019 Daniel Berthereau
 *
 * This software is governed by the CeCILL license under French law and abiding
 * by the rules of distribution of free software. You can use, modify and/or
 * redistribute the software under the terms of the CeCILL license as circulated
 * by CEA, CNRS and INRIA at the following URL "http://www.cecill.info".
 *
 * As a counterpart to the access to the source code and rights to copy, modify
 * and redistribute granted by the license, users are provided only with a
 * limited warranty and the software’s author, the holder of the economic
 * rights, and the successive licensors have only limited liability.
 *
 * In this respect, the user’s attention is drawn to the risks associated with
 * loading, using, modifying and/or developing or reproducing the software by
 * the user in light of its specific status of free software, that may mean that
 * it is complicated to manipulate, and that also therefore means that it is
 * reserved for developers and experienced professionals having in-depth
 * computer knowledge. Users are therefore encouraged to load and test the
 * software’s suitability as regards their requirements in conditions enabling
 * the security of their systems and/or data to be ensured and, more generally,
 * to use and operate it in the same conditions as regards security.
 *
 * The fact that you are presently reading this means that you have had
 * knowledge of the CeCILL license and that you accept its terms.
 */

namespace Ebook\Mvc\Controller\Plugin;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Laminas\I18n\View\Helper\Translate;
use Laminas\Log\Logger;
use Laminas\Mvc\Controller\Plugin\AbstractPlugin;
use Laminas\View\Helper\Url;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Exception\NotFoundException;
use Omeka\Api\Representation\ItemRepresentation;
use Omeka\Api\Representation\ItemSetRepresentation;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\File\TempFileFactory;
use Omeka\Mvc\Controller\Plugin\Api;
use Omeka\Settings\Settings;
use Omeka\Site\Navigation\Link\Manager as NavigationLinkManager;
use Omeka\Stdlib\Message;
use Omeka\View\Helper\NavigationLink;
use PHPePub\Core\EPub;
use PHPePub\Helpers\CalibreHelper;
use PHPePub\Helpers\IBooksHelper;
use PHPePub\Helpers\Rendition\RenditionHelper;
use UUID;

/**
 * @todo Build a generic export interface (for online view too). Why not a pseudo-theme?
 *
 * Note about structure of the output.
 * Because items can belong to multiple item sets and there is no primary item
 * set, it's not possible to manage each item set as a chapter.
 * So item set data are displayed first in a chapter, then the items, in another
 * chapter.
 *
 * @todo For compatibility with some common e-readers, each chapter (internal
 * file) should not exceed 250kB. See EPub::addChapter(). A chapter can have
 * multiple files. So autosplit is set. Anyway, one resource = one chapter, and
 * the metadata are generally less than some kilobytes.
 *
 * @todo Manage public files only.
 */
class Ebook extends AbstractPlugin
{
    /**
     * Ebook generated.
     *
     * @var EPub
     */
    protected $ebook;

    /**
     * Parameters for the process.
     *
     * @var array
     */
    protected $data;

    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @var TempFileFactory
     */
    protected $tempFileFactory;

    /**
     * @var NavigationLinkManager
     */
    protected $navigationLinkManager;

    /**
     * @var PhpRenderer
     */
    protected $viewRenderer;

    /**
     * @var Api
     */
    protected $api;

    /**
     * @var Connection
     */
    protected $connection;

    /**
     * Relative base path of the files (generally "/files").
     *
     * @var string
     */
    protected $basePath;

    /**
     * @var string
     */
    protected $tempDir;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var Settings
     */
    protected $settings;

    /**
     * @var Translate
     */
    protected $translate;

    /**
     * @var Url
     */
    protected $url;

    /**
     * @var string
     */
    protected $contentStart = <<<'XHTML5'
<?xml version="1.0" encoding="UTF-8"?>
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
 <head>
  <meta http-equiv="Default-Style" content="text/html; charset=utf-8" />
  __VIEWPORT_META_LINE__
  <link rel="stylesheet" type="text/css" href="styles.css" />
  <title>__TITLE__</title>
 </head>
 <body>

XHTML5;

    /**
     * @var string
     */
    protected $contentEnd = <<<'XHTML5'
 </body>
</html>

XHTML5;

    /**
     * @param EntityManager $entityManager
     * @param TempFileFactory $tempFileFactory
     * @param NavigationLinkManager $navigationLinkManager
     * @param PhpRenderer $viewRenderer
     * @param Api $api
     * @param Connection $connection
     * @param string $basePath
     * @param string $tempDir
     * @param Logger $logger
     * @param Translate $translate
     * @param Url $url
     * @param Settings $settings
     * @param NavigationLink $navigationLink
     */
    public function __construct(
        EntityManager $entityManager,
        TempFileFactory $tempFileFactory,
        NavigationLinkManager $navigationLinkManager,
        PhpRenderer $viewRenderer,
        Api $api,
        Connection $connection,
        $basePath,
        $tempDir,
        Logger $logger,
        Translate $translate,
        Url $url,
        Settings $settings,
        NavigationLink $navigationLink
    ) {
        $this->entityManager = $entityManager;
        $this->tempFileFactory = $tempFileFactory;
        $this->navigationLinkManager = $navigationLinkManager;
        $this->viewRenderer = $viewRenderer;
        $this->api = $api;
        $this->connection = $connection;
        $this->basePath = $basePath;
        $this->tempDir = $tempDir;
        $this->logger = $logger;
        $this->url = $url;
        $this->translate = $translate;
        $this->settings = $settings;
        $this->navigationLink = $navigationLink;
    }

    /**
     * Create an ebook from a list of resources, some metadata, and a template.
     *
     * @param array $data
     * @return array|null An array containing the asset and the url.
     */
    public function __invoke(array $data)
    {
        return $this->create($data);
    }

    public function task(array $data, $job_id)
    {
        $result = $this->create($data);
        $url = $result['url'] ?? '';
        $this->connection->query('UPDATE ebook_creation SET resource_data = "' . $url . '" WHERE `job_id` = "' . $job_id . '";');
        return $result;
    }

    protected function create(array $data)
    {
        $data = $this->prepareData($data);
        $this->data = $data;

        $this->initializeEbook();

        $isSite = !empty($data['site']);
        $isItemSet = $data['resource_type'] === 'item_sets';
        if ($isSite) {
            $this->processSite();
        } elseif ($isItemSet) {
            $this->processItemSets();
        } else {
            $this->processItems();
        }

        $this->finalizeEbook();

        return $this->saveEbook();
    }

    /**
     * Normalize and fill default value for data.
     *
     * @todo Use a class.
     *
     * @param array $data
     * @return array
     */
    protected function prepareData(array $data)
    {
        $translate = $this->translate;
        $defaultData = [
            'batch_action' => 'selected',
            'site' => null,
            'resource_type' => 'items',
            'resource_ids' => [],
            'query' => [],
            'dcterms:title' => $translate('[No title]'), // @translate
            'dcterms:creator' => '',
            'author_sort_key' => '',
            'dcterms:subject' => [],
            'dcterms:description' => '',
            'dcterms:publisher' => '',
            'publisher_url' => '',
            'dcterms:language' => '',
            'dcterms:rights' => '',
            'dcterms:identifier' => UUID::mintStr(4),
            'o:resource_template' => null,
            'resource_template_only' => false,
            'cover' => null,
            'dcterms:format' => 'application/epub+zip',
            'output' => 'download',
        ];
        $data += $defaultData;

        // Check arrays.
        foreach (['resource_ids', 'query', 'dcterms:subject'] as $key) {
            if (empty($data[$key])) {
                $data[$key] = [];
            } elseif (!is_array($data[$key])) {
                $data[$key] = array_map('trim', explode(',', $data[$key]));
            }
        }

        // Add required values.
        foreach (['dcterms:title', 'dcterms:identifier', 'dcterms:format', 'output'] as $key) {
            $data[$key] = $data[$key] ?: $defaultData[$key];
        }

        return $data;
    }

    /**
     * Initialize an ebook with general metadata.
     *
     * @see EPub.Example3.php
     */
    protected function initializeEbook(): void
    {
        $data = $this->data;
        $settings = $this->settings;
        $translate = $this->translate;
        $url = $this->url;

        $bookVersion = $data['dcterms:format'] === 'application/epub+zip; version=2.0'
            ? EPub::BOOK_VERSION_EPUB2
            : EPub::BOOK_VERSION_EPUB3;
        // The default for EPub is "en".
        $language = $data['dcterms:language'] ?: ($settings->get('locale') ?: 'en');
        $direction = empty($data['direction']) ? EPub::DIRECTION_LEFT_TO_RIGHT : $data['direction'];
        $htmlFormat = (!empty($data['htmlFormat']) && in_array($data['htmlFormat'], [EPub::FORMAT_XHTML, EPub::FORMAT_HTML5]))
            ? $data['htmlFormat']
            : EPub::FORMAT_XHTML;

        $ebook = new EPub($bookVersion, $language, $direction, $htmlFormat);
        $this->ebook = $ebook;

        $ebook->isLogging = !empty($data['debug']);

        // Title and Identifier are mandatory!
        $ebook->setTitle((string) $data['dcterms:title']);

        $identifier = trim((string) $data['dcterms:identifier']);
        $identifierType = null;
        // @see https://www.safaribooksonline.com/library/view/regular-expressions-cookbook/9781449327453/ch04s13.html
        $regexIsbn = <<<'REGEX'
/^(?:ISBN(?:-1[03])?:? )?(?=[0-9X]{10}$|(?=(?:[0-9]+[- ]){3})[- 0-9X]{13}$|97[89][0-9]{10}$|(?=(?:[0-9]+[- ]){4})[- 0-9]{17}$)(?:97[89][- ]?)?[0-9]{1,5}[- ]?[0-9]+[- ]?[0-9]+[- ]?[0-9X]$/i
REGEX;
        // @see https://www.safaribooksonline.com/library/view/regular-expressions-cookbook/9781449327453/ch04s13.html
        $regexUuid = <<<'REGEX'
/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i
REGEX;
        if (strpos($identifier, 'http') === 0) {
            $identifierType = EPub::IDENTIFIER_URI;
        } elseif (preg_match($regexIsbn, $identifier)) {
            $identifierType = EPub::IDENTIFIER_ISBN;
            $identifier = strtoupper($identifier);
            // ISBN is required when printed.
            if (strpos($identifier, 'ISBN') === false) {
                $identifier = 'ISBN ' . $identifier;
            }
        } elseif (preg_match($regexUuid, $identifier)) {
            $identifierType = EPub::IDENTIFIER_UUID;
            $identifier = strtolower($identifier);
        }
        // Else the identifier will be automatically set during finalization.
        $ebook->setIdentifier($identifier, $identifierType);

        // RFC3066 (https://tools.ietf.org/html/rfc3066), so ISO 639.
        $ebook->setLanguage($language);
        $ebook->setDescription($data['dcterms:description']);
        $ebook->setAuthor($data['dcterms:creator'], $data['author_sort_key']);
        $ebook->setPublisher($data['dcterms:publisher'], $data['publisher_url']);
        // Strictly not needed as the book date defaults to time().
        $ebook->setDate(time());
        // If rights are specific to the user, the identifier must be unique.
        // TODO Check if the rights are specific to the user.
        $ebook->setRights($data['dcterms:rights']);

        $value = $url('top', [], ['force_canonical' => true]);
        $ebook->setSourceURL($value);

        // Add the generator of the epub?
        // $ebook->addDublinCoreMetadata(DublinCore::CONTRIBUTOR, "PHP");

        foreach ($data['dcterms:subject'] as $value) {
            $ebook->setSubject($value);
        }

        // Custom metadata: Calibre series index information.
        $seriesTitle = sprintf($translate('Generated from digital library: %s'), // @translate
            $settings->get('installation_title'));
        CalibreHelper::setCalibreMetadata($ebook, $seriesTitle, '3');

        // Fixed-layout metadata (only available in ePub3).
        if ($data['dcterms:format'] !== 'application/epub+zip; version=2.0') {
            RenditionHelper::addPrefix($ebook);
            RenditionHelper::setLayout($ebook, RenditionHelper::LAYOUT_PRE_PAGINATED);
            RenditionHelper::setOrientation($ebook, RenditionHelper::ORIENTATION_AUTO);
            RenditionHelper::setSpread($ebook, RenditionHelper::SPREAD_AUTO);
        }

        // Setting rendition parameters for fixed layout requires the user to
        // add a viewport to each html file.
        // It is up to the user to do this, however the cover image and toc
        // files are generated by the EPub class, and need the information.
        // It can be set multiple times if different viewports are needed for
        // the cover image page and toc.
        $ebook->setViewport('720p');

        IBooksHelper::addPrefix($ebook);
        IBooksHelper::setIPadOrientationLock($ebook, IBooksHelper::ORIENTATION_PORTRAIT_ONLY);
        IBooksHelper::setIPhoneOrientationLock($ebook, IBooksHelper::ORIENTATION_PORTRAIT_ONLY);
        IBooksHelper::setSpecifiedFonts($ebook, true);
        IBooksHelper::setFixedLayout($ebook, true);

        // Initialize the content.
        $this->contentStart = str_replace('__TITLE__', $data['dcterms:title'], $this->contentStart);
        $this->contentStart = str_replace(
            '__VIEWPORT_META_LINE__',
            $ebook->getViewportMetaLine(),
            $this->contentStart
        );

        // Default presentation.
        $cssData = <<<'CSS'
body {
  margin-left: .5em;
  margin-right: .5em;
  text-align: justify;
}

p {
  font-family: serif;
  font-size: 10pt;
  text-align: justify;
  text-indent: 1em;
  margin-top: 0px;
  margin-bottom: 1ex;
}

h1,
h2 {
  font-family: sans-serif;
  font-style: italic;
  text-align: center;
  background-color: #6b879c;
  color: white;
  width: 100%;
}

h1 {
  margin-bottom: 2px;
}

h2 {
  margin-top: -2px;
  margin-bottom: 2px;
}
CSS;
        $ebook->addCSSFile('styles.css', 'css1', $cssData);
        // TODO Use epub.css from current theme, else from module as fallback.

        if ($data['cover']) {
            try {
                /** @var \Omeka\Api\Representation\AssetRepresentation $asset */
                $asset = $this->api->read('assets', $data['cover'])->getContent();
            } catch (NotFoundException $e) {
            }
            if ($asset) {
                $managedImages = [
                    'image/jpeg' => 'jpg',
                    'image/gif' => 'gif',
                    'image/png' => 'png',
                ];
                if (strpos($asset->mediaType(), 'image/') === 0
                    && isset($managedImages[$asset->mediaType()])
                ) {
                    $filepath = $this->basePath . DIRECTORY_SEPARATOR . 'asset/' . $asset->filename();
                    $ebook->setCoverImage(
                        'cover.' . $managedImages[$asset->mediaType()],
                        file_get_contents($filepath),
                        $asset->mediaType()
                    );
                } else {
                    $this->logger->warn(new Message('The cover is not a managed image.'));
                }
            }
        }

        $output = $this->viewRenderer->render('ebook/template/cover', [
            'data' => $data,
        ]);
        $ebook->addChapter(
            $translate('Notices'),
            'cover.xhtml',
            $this->contentStart . $output . $this->contentEnd
        );

        $ebook->addChapter($translate('Table of Contents'), 'toc.xhtml');
    }

    protected function finalizeEbook(): void
    {
        $ebook = $this->ebook;

        $ebook->rootLevel();
        $ebook->buildTOC();
        // Only used in case we need to debug EPub.
        if ($ebook->isLogging) {
            $epuplog = $ebook->getLog();
            $ebook->addChapter(
                'ePubLog',
                'ePubLog.xhtml',
                $this->contentStart . $epuplog . "\n" . $this->contentEnd
            );
        }

        $ebook->finalize();
    }

    protected function processSite(): void
    {
        $data = $this->data;
        $ebook = $this->ebook;

        // Need to set the site for site settings, that may be used indirectly.
        $siteSettings = $services->get('Omeka\Settings\Site');
        $siteSettings->setTargetId($site->id());

        /** @var \Omeka\Api\Representation\SiteRepresentation $site */
        $site = $data['site'];
        // The table of contents will be automatically created from pages, but
        // the structure and the order are needed.
        // TODO Use Zend navigation via $site->publicNav() instead of navigation data?
        $navigation = $site->navigation();

        // Remove non-page pages (search, browse, etc. are useless in a ebook).
        // TODO Manage all types of pages? Probably useless in a ebook.
        $types = ['page'];

        // Append each page to the ebook. Flat navigation is simpler to manage.
        $flatNavigation = $this->convertNestedToFlat($navigation, [], 0, $types);

        // Initialize the ebook to avoid possible issues.
        $ebook->setCurrentLevel(1);

        foreach ($flatNavigation as $flatPage) {
            // In the ebook standard, the root level is level 1.
            $level = $flatPage['level'] + 1;
            // There is no method to set the current level: setCurrentLevel()
            // is a shortcut to go back level (one of the upper level), so if
            // subLevel() is not used, it cannot be used.
            $previousLevel = $ebook->getCurrentLevel();
            if ($level > $previousLevel) {
                $ebook->subLevel();
            } elseif ($level < $previousLevel) {
                $ebook->setCurrentLevel($level);
            }
            $this->processSitePage($site, $flatPage);
        }

        $ebook->rootLevel();

        // Append item sets of the site if wanted.

        // Append items of the site  if wanted.
    }

    /**
     * Create an ebook chapter from a site page.
     *
     * @param SiteRepresentation $site
     * @param array $flatPage Navigation data of the page.
     */
    protected function processSitePage(SiteRepresentation $site, $flatPage): void
    {
        static $automaticId = 0;

        // TODO use a view model?
        // $data = $this->data;
        $ebook = $this->ebook;
        $translate = $this->translate;

        $type = $flatPage['type'];

        // Warn if the page doesn't exits, and use the fallback.
        if (!$this->navigationLinkManager->has($type)) {
            $this->logger->err(new Message('The ebook has an unavailable page type: "%s".', // @translate
                $type));
        } elseif ($type === 'page') {
            try {
                /** @var \Omeka\Api\Representation\SitePageRepresentation $page */
                $page = $this->api->read('site_pages', $flatPage['data']['id'])->getContent();
            } catch (NotFoundException $e) {
                $this->logger->err(new Message('The ebook has an unavailable page: "%s".', // @translate
                    $flatPage['id']));
                return;
            }
        }

        // The fallback is automatically managed by the manager (no toZend()).
        $siteNavigationLink = $this->navigationLinkManager->get($flatPage['type']);

        // Currently, only pages are supported.
        // TODO Manage other types than site pages (use a full theme instead).
        if ($type !== 'page') {
            $this->logger->err(new Message('The module cannot manage type "%s" currently.', // @translate
                $type));
            return;
        }

        // Because, only pages are supported, a template can be used.
        // Nevertheless, a specific view should be used, one for the main layout, the other one for the blocks.
        // See Omeka\Controller\Site\PageControllershowAction().
        // $output = $this->viewRenderer->render('ebook/template/site-page', [
        //     'pageViewModel', $view,
        //     'site' => $site,
        //     'resourceType' => 'site_page',
        //     'resource' => $page,
        //     'page' => $page,
        //     'data' => $data,
        // ]);
        $view = new \Laminas\View\Model\ViewModel;
        $view->setVariable('site', $site);
        $view->setVariable('page', $page);
        $view->setVariable('displayNavigation', false);
        $view->setTemplate('ebook/template/site-page');
        $contentView = clone $view;
        $contentView->setTemplate('omeka/site/page/content');
        $contentView->setVariable('pageViewModel', $view);

        $view->addChild($contentView, 'content');
        $output = $this->viewRenderer->render($view);

        $title = $siteNavigationLink->getLabel($flatPage['data'], $site) ?: $translate('[Untitled]'); // @translate
        $filename = sprintf('site_page_%d.xhtml', ++$automaticId);
        $ebook->addChapter(
            $title,
            $filename,
            $this->contentStart . $output . $this->contentEnd,
            false
        );
    }

    protected function processItemSets(): void
    {
        $data = $this->data;
        $ebook = $this->ebook;
        $translate = $this->translate;

        // TODO Manage possible memory overload.
        $selectAll = $data['batch_action'] === 'all';
        $resources = $this->fetchResources($data['resource_type'], $data['resource_ids'], $data['query'], $selectAll);

        // Manage the case where there is a single item set, to avoid duplicate
        // ebook description, etc.
        $singleItemSet = count($resources) == 1;
        $itemSetIds = [];
        if ($singleItemSet) {
            $itemSet = reset($resources);
            $itemSetIds[] = $itemSet->id();
        } else {
            $ebook->setCurrentLevel(1);
            $content = $this->contentStart
                . sprintf('<h1>%s</h1>', $translate('Item sets')) . PHP_EOL
                . $this->contentEnd;
            $ebook->addChapter($translate('Item sets'), 'item_sets.xhtml', $content);
            $ebook->subLevel();

            foreach ($resources as $itemSet) {
                $this->processItemSet($itemSet);
                $itemSetIds[] = $itemSet->id();
            }

            $ebook->setCurrentLevel(1);
            $content = $this->contentStart
                . sprintf('<h1>%s</h1>', $translate('Items')) . PHP_EOL
                . $this->contentEnd;
            $ebook->addChapter($translate('Items'), 'items.xhtml', $content);
            $ebook->subLevel();
        }

        $items = $this->api->search('items', ['item_set_id' => $itemSetIds])->getContent();
        foreach ($items as $item) {
            $this->processItem($item);
        }
    }

    /**
     * Create an ebook chapter from an item set.
     *
     * @param ItemSet Representation $itemSet
     */
    protected function processItemSet(ItemSetRepresentation $itemSet): void
    {
        static $automaticId = 0;

        // TODO use a view model?
        $data = $this->data;
        $ebook = $this->ebook;
        $translate = $this->translate;

        $output = $this->viewRenderer->render('ebook/template/item-set', [
            'resource' => $itemSet,
            'resourceType' => 'item_set',
            'itemSet' => $itemSet,
            'data' => $data,
        ]);
        $title = sprintf($translate('Item set #%d: %s'), $itemSet->id(), $itemSet->displayTitle());
        $filename = sprintf('item_set_%d_%d.xhtml', ++$automaticId, $itemSet->id());
        $ebook->addChapter(
            $title,
            $filename,
            $this->contentStart . $output . $this->contentEnd,
            false
        );
    }

    protected function processItems(): void
    {
        $data = $this->data;
        $ebook = $this->ebook;
        $translate = $this->translate;

        // TODO Manage possible memory overload.
        $selectAll = $data['batch_action'] === 'all';
        /** @var \Omeka\Api\Representation\ItemRepresentation[] $resources */
        $resources = $this->fetchResources($data['resource_type'], $data['resource_ids'], $data['query'], $selectAll);

        $ebook->setCurrentLevel(1);
        $content = $this->contentStart
            . sprintf('<h1>%s</h1>', $translate('Items')) . PHP_EOL
            . $this->contentEnd;
        $ebook->addChapter($translate('Items'), 'items.xhtml', $content);
        $ebook->subLevel();

        foreach ($resources as $item) {
            $this->processItem($item);
        }
    }

    /**
     * Create an ebook chapter from an item.
     *
     * @param ItemRepresentation $item
     */
    protected function processItem(ItemRepresentation $item): void
    {
        static $automaticId = 0;

        // TODO use a view model?
        $data = $this->data;
        $ebook = $this->ebook;
        $translate = $this->translate;

        $output = $this->viewRenderer->render('ebook/template/item', [
            'resource' => $item,
            'resourceType' => 'item',
            'item' => $item,
            'data' => $data,
        ]);
        $title = sprintf($translate('Item #%d: %s'), $item->id(), $item->displayTitle());
        $filename = sprintf('item_%d_%d.xhtml', ++$automaticId, $item->id());
        $ebook->addChapter(
            $title,
            $filename,
            $this->contentStart . $output . $this->contentEnd,
            false,
            EPub::EXTERNAL_REF_ADD
        );
        // TODO Modify the urls to save the images locally.
    }

    protected function saveEbook()
    {
        $data = $this->data;
        $ebook = $this->ebook;

        // Save a temporary file.
        // EPub adds an extension "epub" automatically, so the method saveBook
        // is not used. The tempFileFactory allows to use the Omeka temp dir.
        $tempFile = $this->tempFileFactory->build();
        $filepath = $tempFile->getTempPath() . '.epub';
        $tempFile->delete();

        switch ($data['dcterms:format']) {
            case 'application/epub+zip':
            case 'application/epub+zip; version=2.0':
            default:
                $result = file_put_contents($filepath, $ebook->getBook());
                break;
        }

        if (empty($result)) {
            $this->logger->err(new Message('Ebook cannot be built: "%s"', // @translate
                $data['dcterms:title']));
            return null;
        }

        switch ($data['output']) {
            case 'asset':
                // Use directly the entityManager instead of the adapter, because
                // the file is already loaded and it is complex to use the api
                // in that case.

                // Store the ebooks in a subfolder of "files/asset".
                $dirPath = $this->basePath . '/asset/ebook';
                $storedFile = $this->saveFile($filepath, $dirPath, 'ebook/', 'files/asset/ebook/');
                if (empty($storedFile)) {
                    return null;
                }

                $asset = new \Omeka\Entity\Asset;
                $asset->setName($storedFile['filename']);
                $asset->setStorageId($storedFile['storageId']);
                $asset->setExtension($storedFile['extension']);
                $asset->setMediaType(strtok($data['dcterms:format'], ';'));
                $this->entityManager->persist($asset);
                $this->entityManager->flush();

                /** @var \Omeka\Api\Representation\AssetRepresentation $asset */
                $asset = $this->api->read('assets', $asset->getId())->getContent();
                $result = [
                    'resource' => $asset,
                    'url' => $storedFile['url'],
                ];
                break;

            case 'item':
                // Store the ebooks in a subfolder of "files/temp" to upload.
                // Omeka doesn't allow to ingest a local path, so store ebook
                // temporary inside "files/temp" to upload it by ingester "url".
                $dirPath = $this->basePath . '/temp';
                $storedFile = $this->saveFile($filepath, $dirPath, '', 'files/temp/');
                if (empty($storedFile)) {
                    return null;
                }
                $tempPath = $dirPath . DIRECTORY_SEPARATOR . $storedFile['filename'];

                $itemData = [];
                $itemData['o:resource_template'] = ['o:id' => $data['o:resource_template']];
                $itemData['o:resource_class'] = ['o:id' => null];
                $itemData['o:is_public'] = false;
                $itemData['o:item_set'] = [];
                $itemData['o:media'][] = [
                    'o:is_public' => 1,
                    'ingest_url' => $data['url_top'] . $storedFile['url'],
                    'o:ingester' => 'url',
                ];
                foreach ([
                    'dcterms:title' => 1,
                    'dcterms:creator' => 2,
                    // 'author_sort_key' => ,
                    'dcterms:subject' => 3,
                    'dcterms:description' => 4,
                    'dcterms:publisher' => 5,
                    // 'publisher_url' => ,
                    'dcterms:language' => 12,
                    'dcterms:rights' => 15,
                    'dcterms:identifier' => 10,
                    // 'o:resource_template' => ,
                    // 'resource_template_only' => ,
                    // 'cover' => ,
                    'dcterms:format' => 9,
                    // 'output' => ,
                ] as $term => $termId) {
                    switch ($term) {
                        case'dcterms:publisher':
                            $value = $data[$term] . ($data['publisher_url'] ? ' (' . $data['publisher_url'] . ')' : '');
                            break;
                        default:
                            $value = $data[$term];
                            break;
                    }
                    if (!is_array($value)) {
                        $value = [$value];
                    }
                    foreach ($value as $val) {
                        if (strlen($val) === 0) {
                            continue;
                        }
                        $itemData[$term] = [
                            [
                                'property_id' => $termId,
                                'type' => 'literal',
                                '@language' => null,
                                '@value' => $val,
                            ],
                        ];
                    }
                }

                // TODO Add epub in global settings.
                $settings = $this->settings;
                $disableFileValidation = $settings->get('disable_file_validation');
                $settings->set('disable_file_validation', '1');

                $response = $this->api->create('items', $itemData, []);

                // In all cases, the temp file is removed.
                $settings->set('disable_file_validation', $disableFileValidation);
                @unlink($tempPath);

                if (!$response) {
                    $this->logger->err(new Message('Ebook cannot be created.')); // @translate
                    return null;
                }
                $item = $response->getContent();
                $media = $item->primaryMedia();

                $result = [
                    'resource' => $media,
                    'url' => $media->originalUrl(),
                ];
                break;

            case 'download':
            default:
                $dirPath = $this->basePath . '/temp';
                $storedFile = $this->saveFile($filepath, $dirPath, '', 'files/temp/');
                if (empty($storedFile)) {
                    return;
                }

                $result = [
                    'resource' => null,
                    'url' => $storedFile['url'],
                ];
                break;
        }

        return $result;
    }

    /**
     * Save the ebook into the specified location.
     *
     * @param string $source
     * @param string $destinationDir Should be a subfolder of "/files".
     * @param string $base
     * @param string $baseUrl
     * @return array|null
     */
    protected function saveFile($source, $destinationDir, $base = '', $baseUrl = '')
    {
        $data = $this->data;

        if (!$this->checkDir($destinationDir)) {
            return null;
        }

        $mapMediaTypesToExtensions = [
            'application/epub+zip; version=2.0' => 'epub',
            'application/epub+zip' => 'epub',
            'application/pdf' => 'pdf',
            'text/html' => 'html',
            'application/vnd.oasis.opendocument.text' => 'odt',
        ];

        // Find a unique meaningful filename instead of a hash.
        $name = strtolower(substr($this->slugify($data['dcterms:title']), 0, 20));
        $name = date('Y-m-d_His') . '_' . $name;
        $extension = $mapMediaTypesToExtensions[$data['dcterms:format']];

        $i = 0;
        do {
            $filename = $name . ($i ? '-' . $i : '') . '.' . $extension;
            $destination = $destinationDir . '/' . $filename;
            if (!file_exists($destination)) {
                $result = @rename($source, $destination);
                if (!$result) {
                    $this->logger->err(new Message('Ebook cannot be saved in "%1$s" (temp file: "%2$s")', // @translate
                        $destination, $source));
                    return null;
                }
                $storageId = $base . $name . ($i ? '-' . $i : '');
                break;
            }
        } while (++$i);

        return [
            'filename' => $filename,
            'storageId' => $storageId,
            'extension' => $extension,
            'url' => $baseUrl . $filename,
        ];
    }

    /**
     * Check or create the destination folder.
     *
     * @param string $dirPath
     * @return bool
     */
    protected function checkDir($dirPath)
    {
        if (!file_exists($dirPath)) {
            if (!is_writeable($this->basePath)) {
                $this->logger->err(new Message('The destination folder "%s" is not writeable.', // @translate
                    $dirPath));
                return false;
            }
            @mkdir($dirPath, 0755, true);
        } elseif (!is_dir($dirPath) || !is_writeable($dirPath)) {
            $this->logger->err(new Message('The destination folder "%s" is not writeable.', // @translate
                $dirPath));
            return false;
        }
        return bool;
    }

    /**
     * Fetch items from a list of ids.
     *
     * @todo Factorize with EbookController.
     *
     * @param string $resourceType
     * @param array $resourceIds
     * @param array $query
     * @param bool $selectAll
     * @return array|null
     */
    protected function fetchResources($resourceType, array $resourceIds, array $query = null, $selectAll = false)
    {
        if ($selectAll) {
            $resources = $this->api->search($resourceType, $query)->getContent();
        } else {
            // It is impossible to search an item set by id (< Omeka 1.2).
            switch ($resourceType) {
                case 'item_sets':
                    foreach ($resourceIds as $resourceId) {
                        try {
                            $resources[] = $this->api->read($resourceType, $resourceId)->getContent();
                        } catch (NotFoundException $e) {
                        }
                    }
                    break;
                default:
                    foreach ($resourceIds as $resourceId) {
                        $resources[] = $this->api->searchOne($resourceType, ['id' => $resourceId])->getContent();
                    }
                    break;
            }
        }
        return $resources;
    }

    /**
     * Transform the given string into a valid URL slug
     *
     * @see \Omeka\Api\Adapter\SiteSlugTrait::slugify()
     *
     * @param string $input
     * @return string
     */
    protected function slugify($input)
    {
        if (extension_loaded('intl')) {
            $transliterator = \Transliterator::createFromRules(':: NFD; :: [:Nonspacing Mark:] Remove; :: NFC;');
            $slug = $transliterator->transliterate($input);
        } elseif (extension_loaded('iconv')) {
            $slug = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $input);
        } else {
            $slug = $input;
        }
        $slug = mb_strtolower((string) $slug, 'UTF-8');
        $slug = preg_replace('/[^a-z0-9-]+/u', '-', $slug);
        $slug = preg_replace('/-{2,}/', '-', $slug);
        $slug = preg_replace('/-*$/', '', $slug);
        return $slug;
    }

    /**
     * Convert a navigation tree to a flat list of pages.
     *
     * This is a recursive method.
     *
     * @param array $tree The key "links" contains the sub-levels.
     * @param array $flat The key "level " indicates the level, and the key
     * "content" contains the original content.
     * @param int $level
     * @param array $types Allowed types of pages.
     * @return array Flat array, without key "links" but a  key "level".
     */
    protected function convertNestedToFlat(array $tree, array $flat = [], $level = 0, array $types = [])
    {
        foreach ($tree as $value) {
            $links = empty($value['links']) ? [] : $value['links'];
            unset($value['links']);
            // Skip the page.
            if ($types && (!isset($value['type']) || !in_array($value['type'], $types))) {
                $baseLevel = $level - 1;
            }
            // Append the page.
            else {
                $baseLevel = $level;
                $value['level'] = $level;
                $flat[] = $value;
            }
            $flat = $this->convertNestedToFlat($links, $flat, $baseLevel + 1, $types);
        }
        return $flat;
    }
}
