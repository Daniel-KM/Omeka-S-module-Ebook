<?php
/**
 * eBook Creator
 *
 * Merge selected resources into an ePub or a pdf file for publishing, report,
 * or archiving purpose.
 *
 * @copyright Daniel Berthereau, 2018
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

use Ebook\Form\ConfigForm;
use Omeka\Module\AbstractModule;
use Zend\EventManager\Event;
use Zend\EventManager\SharedEventManagerInterface;
use Zend\ModuleManager\ModuleManager;
use Zend\Mvc\Controller\AbstractController;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\View\Renderer\PhpRenderer;

class Module extends AbstractModule
{
    /**
     * @param ModuleManager $moduleManager
     */
    public function init(ModuleManager $moduleManager)
    {
        require_once __DIR__ . '/vendor/autoload.php';
    }

    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function install(ServiceLocatorInterface $serviceLocator)
    {
        $settings = $serviceLocator->get('Omeka\Settings');
        $this->manageSettings($settings, 'install');
    }

    public function uninstall(ServiceLocatorInterface $serviceLocator)
    {
        $settings = $serviceLocator->get('Omeka\Settings');
        $this->manageSettings($settings, 'uninstall');
    }

    protected function manageSettings($settings, $process, $key = 'config')
    {
        $config = require __DIR__ . '/config/module.config.php';
        $defaultSettings = $config[strtolower(__NAMESPACE__)][$key];
        foreach ($defaultSettings as $name => $value) {
            switch ($process) {
                case 'install':
                    $settings->set($name, $value);
                    break;
                case 'uninstall':
                    $settings->delete($name);
                    break;
            }
        }
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager)
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
            'Omeka\Controller\Admin\Media',
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
            'Omeka\Controller\Admin\Media',
            'view.show.sidebar',
            [$this, 'handleAdminViewShowSidebar']
        );
        $sharedEventManager->attach(
            'Omeka\Controller\SiteAdmin\Index',
            'view.layout',
            [$this, 'handleAdminViewLayoutAdminSiteNavigation']
        );
    }

    public function getConfigForm(PhpRenderer $renderer)
    {
        $services = $this->getServiceLocator();
        $config = $services->get('Config');
        $settings = $services->get('Omeka\Settings');
        $form = $services->get('FormElementManager')->get(ConfigForm::class);

        $data = [];
        $defaultSettings = $config[strtolower(__NAMESPACE__)]['config'];
        foreach ($defaultSettings as $name => $value) {
            $data[$name] = $settings->get($name, $value);
        }

        $form->init();
        $form->setData($data);
        $html = $renderer->formCollection($form);
        return $html;
    }

    public function handleConfigForm(AbstractController $controller)
    {
        $services = $this->getServiceLocator();
        $config = $services->get('Config');
        $settings = $services->get('Omeka\Settings');
        $form = $services->get('FormElementManager')->get(ConfigForm::class);

        $params = $controller->getRequest()->getPost();

        $form->init();
        $form->setData($params);
        if (!$form->isValid()) {
            $controller->messenger()->addErrors($form->getMessages());
            return false;
        }

        $params = $form->getData();
        $defaultSettings = $config[strtolower(__NAMESPACE__)]['config'];
        $params = array_intersect_key($params, $defaultSettings);
        foreach ($params as $name => $value) {
            $settings->set($name, $value);
        }
    }

    public function handleAdminViewLayoutAdminSiteNavigation(Event $event)
    {
        // There is no specific event "view.before" for admin/site/navigation,
        // so use the generic event "view.layout", but filter it here.
        $routeMatch = $this->getServiceLocator()->get('Application')
            ->getMvcEvent()->getRouteMatch();
        if ($routeMatch->getParam('action') !== 'navigation') {
            return;
        }

        $view = $event->getTarget();
        $view->headScript()->appendFile($view->assetUrl('js/ebook-admin.js', __NAMESPACE__));
    }

    public function handleAdminViewBrowseResource(Event $event)
    {
        $view = $event->getTarget();
        $view->headScript()->appendFile($view->assetUrl('js/ebook-admin.js', __NAMESPACE__));
    }

    public function handleAdminViewShowSidebar(Event $event)
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
