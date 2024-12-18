<?php declare(strict_types=1);

namespace Ebook\Controller\Admin;

use Common\Stdlib\PsrMessage;
use Doctrine\DBAL\Connection;
use Ebook\Form\EbookForm;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Omeka\Api\Representation\SiteRepresentation;

class EbookController extends AbstractActionController
{
    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected $connection;

    /**
     * @param Connection $connection
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Create an ebook for a site.
     */
    public function createSiteAction()
    {
        $siteSlug = $this->params('site-slug');
        $site = $this->api()->read('sites', ['slug' => $siteSlug])->getContent();
        if ($this->getRequest()->isGet()) {
            $isPost = false;
            $params = $this->params()->fromQuery();
            $params['dcterms:title'] = $site->title();
        } elseif ($this->getRequest()->isPost()) {
            $isPost = true;
            $params = $this->params()->fromPost();
        } else {
            return $this->redirect()->toRoute('admin/site/slug/action', ['site-slug' => $siteSlug, 'action' => 'show']);
        }

        $params['site'] = $site;
        $viewHelpers = $this->viewHelpers();

        $form = $this->getForm(EbookForm::class);
        $form->setAttribute('id', 'ebook-create');
        if ($isPost) {
            $form->setData($params);
            if ($form->isValid()) {
                $data = $form->getData();
                $data['site'] = $site;
                $data['url_top'] = rtrim($this->url()->fromRoute('top', [], ['force_canonical' => true]), '/') . '/';

                $result = $this->ebook($data);

                if ($result) {
                    $messageResource = '';
                    if (isset($result['resource']) && is_object($result['resource'])) {
                        if ($result['resource'] instanceof \Omeka\Api\Representation\AssetRepresentation) {
                        } else {
                            $messageResource = new PsrMessage(
                                'See it as {link}item #{item_id}{link_end}.',
                                [
                                    'link' => '<a href="' . htmlspecialchars($result['resource']->url()) . '">',
                                    'item_id' => $result['resource']->id(),
                                    'link_end' => '</a>',
                                ]
                            );
                            $messageResource->setEscapeHtml(false);
                        }
                    }

                    // Get the absolute url if it's a relative one.
                    $url = $result['url'];
                    if (strpos($url, 'http') !== 0) {
                        $serverUrl = $viewHelpers->get('ServerUrl');
                        $webPath = $serverUrl('/');
                        $basePath = $viewHelpers->get('BasePath');
                        $basePath = trim($basePath(), '/') . '/';
                        $url = $webPath . $basePath . $url;
                    }

                    $assetUrl = $viewHelpers->get('assetUrl');
                    $urlRead = $assetUrl('vendor/epubjs-reader/index.html', 'Ebook') . '&bookPath=' . $url;
                    $message = new PsrMessage(
                        'Ebook successfully created. {link}Download it{link_end} or {link_2}read it{link_end}. {message}', // @translate
                        [
                            'link' => '<a href="' . htmlspecialchars($url) . '">',
                            'link_end' => '</a>',
                            'link_2' => '<a target="_blank" rel="noopener" href="' . htmlspecialchars($urlRead) . '">',
                            'message' => $messageResource,
                        ]
                    );
                    $message->setEscapeHtml(false);
                    $this->messenger()->addSuccess($message);
                }
                return $this->redirect()->toRoute(
                    'admin/site/slug/action',
                    ['site-slug' => $siteSlug, 'action' => 'navigation']
                );
            }

            $this->messenger()->addFormErrors($form);
        } else {
            $params += $this->fillDefaultParamsSite($site);
            $form->setData($params);
        }

        $ckEditor = $viewHelpers->get('ckEditor');
        $ckEditor();

        $view = new ViewModel([
            'form' => $form,
            'siteSlug' => $siteSlug,
        ]);
        return $view
            ->setTemplate('ebook/site-admin/ebook/create');
    }

    /**
     * Get the list of created ebooks.
     */
    public function createdEbooksAction()
    {
        $conn = $this->connection;

        $assetUrl = $this->viewHelpers()->get('assetUrl');
        $urlRead = $assetUrl('vendor/epubjs-reader/index.html', 'Ebook') . '&bookPath=';

        $qb = $conn->createQueryBuilder()
            ->select('eb.job_id', 'eb.resource_data', 'j.status', 'j.started', 'j.ended')
            ->from('ebook_creation', 'eb')
            ->leftJoin('eb', 'job', 'j', 'eb.job_id = j.id');

        $stmt = $conn->executeQuery($qb, $qb->getParameters());
        $ebooks = $stmt->fetchAll();

        return new ViewModel([
            'ebooks' => $ebooks,
            'urlRead' => $urlRead,
        ]);
    }

    /**
     * Create an ebook from the selected ressources.
     */
    public function createAction()
    {
        if ($this->getRequest()->isGet()) {
            $params = $this->params()->fromQuery();
        } elseif ($this->getRequest()->isPost()) {
            $params = $this->params()->fromPost();
        } else {
            return $this->redirect()->toRoute('admin');
        }

        // Set default values to simplify checks.
        $params += array_fill_keys(['resource_type', 'resource_ids', 'query', 'batch_action', 'ebook_all'], null);

        $resourceType = $params['resource_type'];
        $resourceTypeMap = [
            'item' => 'items',
            'item-set' => 'item_sets',
            'items' => 'items',
            'item_sets' => 'item_sets',
            // TODO Allow media.
            // 'media' => 'media',
            // 'medias' => 'media',
        ];
        if (!isset($resourceTypeMap[$resourceType])) {
            $this->messenger()->addError('You can create an ebook only from items, item sets and media.'); // @translate
            return $this->redirect()->toRoute('admin');
        }

        $resource = $resourceTypeMap[$resourceType];
        $resourceIds = $params['resource_ids']
            ? (is_array($params['resource_ids']) ? $params['resource_ids'] : explode(',', $params['resource_ids']))
            : [];

        $params['resource_ids'] = $resourceIds;
        // Manage Omeka with or without pull request #1260 (with or without
        // param batch_action), so check $resourceIds in all cases.
        $selectAll = $params['batch_action'] ? $params['batch_action'] === 'ebook-all' : (empty($resourceIds) || (bool) $params['ebook_all']);
        $params['batch_action'] = $selectAll ? 'ebook-all' : 'ebook-selected';

        $controllers = [
            'items' => 'item',
            'item_sets' => 'item-set',
            'media' => 'media',
        ];

        $query = null;
        $resources = [];

        if ($selectAll) {
            // Derive the query, removing limiting and sorting params.
            $query = json_decode($params['query'] ?: [], true);
            unset($query['submit'], $query['page'], $query['per_page'], $query['limit'],
                $query['offset'], $query['sort_by'], $query['sort_order']);
        }

        // Export of item sets is managed like a query for all their items.
        $itemSets = [];
        $itemSetCount = 0;
        $itemSetQuery = null;
        $itemQuery = $query;

        if ($selectAll || $resource === 'item_sets') {
            if ($resource === 'item_sets') {
                if ($selectAll) {
                    $itemSetQuery = $query;
                    $itemSetIds = $this->api()->search('item_sets', $itemSetQuery, ['returnScalar' => 'id'])->getContent();
                } else {
                    $itemSetIds = $resourceIds;
                    foreach ($itemSetIds as $resourceId) {
                        $itemSets[] = $this->api()->read('item_sets', $resourceId)->getContent();
                    }
                }
                if (empty($itemSetIds)) {
                    $this->messenger()->addError('You must select at least one item set with items to create an Ebook.'); // @translate
                    return $this->redirect()->toRoute('admin/default', ['controller' => $controllers[$resource], 'action' => 'browse'], true);
                }
                $itemQuery = ['item_set_id' => $itemSetIds];
                $itemSetCount = count($itemSetIds);
            }

            // TODO Allows item set alone, without item.
            // Don't load entities if the only information needed is total results.
            if (empty($query['limit'])) {
                $query['limit'] = 0;
            }
            $count = $this->api()->search('items', $itemQuery)->getTotalResults();
            if (!$count) {
                $this->messenger()->addError('You must select at least one item to create an ebook.'); // @translate
                return $this->redirect()->toRoute('admin/default', ['controller' => $controllers[$resource], 'action' => 'browse'], true);
            }
        }
        // Use of selected resources.
        else {
            if (empty($resourceIds)) {
                $this->messenger()->addError('You must select at least one resource to create an ebook.'); // @translate
                return $this->redirect()->toRoute('admin/default', ['controller' => $controllers[$resource], 'action' => 'browse'], true);
            }
            foreach ($resourceIds as $resourceId) {
                $resources[] = $this->api()->read($resource, $resourceId)->getContent();
            }
            $count = count($resources);
            if ($resource === 'item_sets') {
                $itemSetIds = $resourceIds;
                $itemSetCount = $count;
            }
        }

        $form = $this->getForm(EbookForm::class);
        $form->setAttribute('id', 'ebook-create');
        if ($this->params()->fromPost('batch_process')) {
            $data = $this->params()->fromPost();
            $form->setData($data);
            if ($form->isValid()) {
                $data = $form->getData();

                // Normalize params.
                unset($data['csrf']);
                $data['batch_action'] = $selectAll ? 'all' : 'selected';
                $data['resource_type'] = $resource;
                $data['resource_ids'] = $resourceIds;
                $data['query'] = $query;
                $data['url_top'] = rtrim($this->url()->fromRoute('top', [], ['force_canonical' => true]), '/') . '/';

                $dispatcher = $this->jobDispatcher();

                $job = $dispatcher->dispatch('Ebook\Job\Create', $data);

                // Unlike site, the ebook record is created via a job.

                $message = new PsrMessage(
                    'Creating ebook in background ({link}job #{job_id}{link_end}, {link_log}logs{link_end})', // @translate
                    [
                        'link' => sprintf('<a href="%s">',
                            htmlspecialchars($this->url()->fromRoute('admin/id', ['controller' => 'job', 'id' => $job->getId()]))
                        ),
                        'job_id' => $job->getId(),
                        'link_end' => '</a>',
                        'link_log' => class_exists('Log\Module', false)
                            ? sprintf('<a href="%1$s">', $this->url()->fromRoute('admin/default', ['controller' => 'log'], ['query' => ['job_id' => $job->getId()]]))
                            : sprintf('<a href="%1$s">', $this->url()->fromRoute('admin/id', ['controller' => 'job', 'action' => 'log', 'id' => $job->getId()])),
                    ]
                );
                $message->setEscapeHtml(false);
                $this->messenger()->addSuccess($message);

                $sql = 'INSERT INTO `ebook_creation` (`job_id`) VALUES (' . $job->getId() . ');';

                $this->connection->executeStatement($sql);

                return $this->redirect()->toRoute('admin/default', ['controller' => 'ebook', 'action' => 'created-ebooks'], true);
            }

            $this->messenger()->addFormErrors($form);
        } else {
            // If single item set, fill the form with its metadata.
            if ($itemSetCount == 1) {
                $params += $this->fillDefaultParamsItemSet(reset($itemSetIds));
            } else {
                $params += $this->fillDefaultParams();
            }
            // Keep hidden the values from the browse page.
            $params['resource_ids'] = implode(',', $params['resource_ids']);
            $form->setData($params);
        }

        $ckEditor = $this->viewHelpers()->get('ckEditor');
        $ckEditor();

        return new ViewModel([
            'form' => $form,
            // Keep current request.
            'selectAll' => $selectAll,
            'resourceType' => $resourceType,
            'resourceIds' => $resourceIds,
            'query' => $query,
            // Complete to display info about the resources to export.
            'resources' => $resources,
            'count' => $count,
            'itemQuery' => $itemQuery,
            'itemSetQuery' => $itemSetQuery,
            'itemSets' => $itemSets,
            'itemSetCount' => $itemSetCount,
        ]);
    }

    /**
     * Get default params for the form.
     *
     * @return array
     */
    protected function fillDefaultParams()
    {
        $params = [];
        $params['dcterms:title'] = 'eBook';
        $params['dcterms:creator'] = $this->identity()->getName();
        $params['dcterms:language'] = $this->settings()->get('locale');
        $params['publisher_url'] = $this->url()->fromRoute('top', [], ['force_canonical' => true]);
        return $params;
    }

    /**
     * Get default params for the form with a site.
     *
     * @param SiteRepresentation $site
     * @return array
     */
    protected function fillDefaultParamsSite(SiteRepresentation $site)
    {
        $params = [];
        $params['dcterms:title'] = $site->title();
        $params['dcterms:creator'] = $this->identity()->getName();
        $siteSettings = $this->siteSettings();
        $siteSettings->setTargetId($site->id());
        $params['dcterms:language'] = $siteSettings->get('locale') ?: $this->settings()->get('locale');
        $params['publisher_url'] = $this->url()->fromRoute('top', [], ['force_canonical' => true]);
        return $params;
    }

    /**
     * Get default params for the form with an item set.
     *
     * @param int $itemSetId
     * @return array
     */
    protected function fillDefaultParamsItemSet($itemSetId)
    {
        /** @var \Omeka\Api\Representation\ItemSetRepresentation $itemSet */
        $itemSet = $this->api()->read('item_sets', $itemSetId)->getContent();
        $params = [];
        $params['dcterms:title'] = $itemSet->displayTitle();
        $params['dcterms:description'] = $itemSet->displayDescription();
        $params['dcterms:subject'] = implode(', ', array_map(function ($v) {
            return $v->value();
        }, $itemSet->value('dcterms:subject', ['type' => 'literal', 'all' => true])));
        $params['publisher_url'] = $this->url()->fromRoute('top', [], ['force_canonical' => true]);
        $toFill = [
            'dcterms:creator',
            'dcterms:publisher',
            'dcterms:language',
            'dcterms:rights',
            'dcterms:identifier',
        ];
        foreach ($toFill as $term) {
            $value = $itemSet->value($term, ['type' => 'literal']);
            if ($value) {
                $params[$term] = $value->value();
            }
        }
        return $params;
    }
}
