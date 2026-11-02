<?php declare(strict_types=1);
/**
 * eBook Creator
 *
 * Merge selected resources into an ePub or a pdf file for publishing, report,
 * or archiving purpose.
 *
 * @copyright Daniel Berthereau, 2018-2024
 * @license http://www.cecill.info/licences/Licence_CeCILL_V2.1-en.txt
 *
 * This software is governed by the CeCILL license under French law and abiding
 * by the rules of distribution of free software.  You can use, modify and/ or
 * redistribute the software under the terms of the CeCILL license as circulated
 * by CEA, CNRS and INRIA at the following URL "http://www.cecill.info".
 *
 * As a counterpart to the access to the source code and rights to copy, modify
 * and redistribute granted by the license, users are provided only with a
 * limited warranty and the software's author, the holder of the economic
 * rights, and the successive licensors have only limited liability.
 *
 * In this respect, the user's attention is drawn to the risks associated with
 * loading, using, modifying and/or developing or reproducing the software by
 * the user in light of its specific status of free software, that may mean that
 * it is complicated to manipulate, and that also therefore means that it is
 * reserved for developers and experienced professionals having in-depth
 * computer knowledge. Users are therefore encouraged to load and test the
 * software's suitability as regards their requirements in conditions enabling
 * the security of their systems and/or data to be ensured and, more generally,
 * to use and operate it in the same conditions as regards security.
 *
 * The fact that you are presently reading this means that you have had
 * knowledge of the CeCILL license and that you accept its terms.
 */
namespace Ebook;

// Load the module dependencies when installed as a zip.
// With composer, libraries are stored in omeka vendor/ and the module has none.
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

if (!class_exists(\Common\TraitModule::class)) {
    require_once dirname(__DIR__) . '/Common/TraitModule.php';
}

use Common\TraitModule;
use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\ModuleManager\ModuleManager;
use Omeka\Module\AbstractModule;

class Module extends AbstractModule
{
    use TraitModule;

    const NAMESPACE = __NAMESPACE__;

    /**
     * @param ModuleManager $moduleManager
     */
    public function init(ModuleManager $moduleManager): void
    {
        // TODO Init view with view helper "doctype" to set xhtml.

    }

    protected function postInstall(): void
    {
        $this->updateWhitelist();
    }

    /**
     * Allow to store ebooks as media.
     */
    protected function updateWhitelist(): void
    {
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        foreach (['media_type_whitelist' => 'application/epub+zip', 'extension_whitelist' => 'epub'] as $key => $value) {
            $whitelist = $settings->get($key, []);
            if (!in_array($value, $whitelist)) {
                $whitelist[] = $value;
                sort($whitelist);
                $settings->set($key, $whitelist);
            }
        }
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager): void
    {
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\ItemSet',
            'view.browse.before',
            [$this, 'handleAdminViewBrowseResource']
        );
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Item',
            'view.browse.before',
            [$this, 'handleAdminViewBrowseResource']
        );
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\ItemSet',
            'view.show.sidebar',
            [$this, 'handleAdminViewShowSidebar']
        );
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Item',
            'view.show.sidebar',
            [$this, 'handleAdminViewShowSidebar']
        );
        $sharedEventManager->attach(
            'Omeka\Controller\SiteAdmin\Index',
            'view.layout',
            [$this, 'handleAdminViewLayoutAdminSiteNavigation']
        );
    }

    public function handleAdminViewLayoutAdminSiteNavigation(Event $event): void
    {
        // There is no specific event "view.before" for admin/site/navigation,
        // so use the generic event "view.layout", but filter it here.
        $routeMatch = $this->getServiceLocator()->get('Application')
            ->getMvcEvent()->getRouteMatch();
        if ($routeMatch->getParam('action') !== 'navigation') {
            return;
        }

        $view = $event->getTarget();
        $view->headScript()
            ->appendFile($view->assetUrl('js/ebook-admin.js', 'Ebook'), 'text/javascript', ['defer' => 'defer']);
    }

    public function handleAdminViewBrowseResource(Event $event): void
    {
        $controllersToResourceTypes = [
            'item' => 'items',
            'item-set' => 'item_sets',
            'Omeka\Controller\Admin\Item' => 'items',
            'Omeka\Controller\Admin\ItemSet' => 'item_sets',
        ];
        $controller = $this->getServiceLocator()->get('Application')
            ->getMvcEvent()->getRouteMatch()->getParam('controller');
        if (!isset($controllersToResourceTypes[$controller])) {
            return;
        }

        // The browse url may be "/admin/item" or "/admin/item/browse", so the
        // url and the resource type are not deduced from it.
        $view = $event->getTarget();
        $config = [
            'resourceType' => $controllersToResourceTypes[$controller],
            'urlCreate' => $view->url('admin/ebook'),
        ];
        $view->headScript()
            ->appendScript('var ebook = ' . json_encode($config, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) . ';')
            ->appendFile($view->assetUrl('js/ebook-admin.js', 'Ebook'), 'text/javascript', ['defer' => 'defer']);
    }

    public function handleAdminViewShowSidebar(Event $event): void
    {
        $view = $event->getTarget();
        $resource = $view->resource;
        $query = [];
        $query['resource_type'] = $resource->resourceName();
        $query['resource_ids'] = [$resource->id()];
        $link = $view->hyperlink(
            $view->translate('Create eBook'), // @translate
            $view->url('admin/ebook/default', ['action' => 'create'], ['query' => $query])
        );
        echo '<div class="meta-group">'
            . '<h4>' . $view->translate('EBook Creator') . '</h4>'
            . '<div class="value">' . $link . '</div>'
            . '</div>';
    }
}
