<?php declare(strict_types=1);

namespace Ebook\Service\ControllerPlugin;

use Ebook\Mvc\Controller\Plugin\Ebook;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class EbookFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        // TODO Factory to be simplified (just send services).

        $plugins = $services->get('ControllerPluginManager');
        $helpers = $services->get('ViewHelperManager');
        $config = $services->get('Config');

        return new Ebook(
            $plugins->get('api'),
            $services->get('Omeka\Connection'),
            $services->get('Omeka\EntityManager'),
            $services->get('Omeka\Logger'),
            $services->get('Omeka\Site\NavigationLinkManager'),
            $services->get('Omeka\Settings'),
            $services->get('Omeka\Settings\Site'),
            $services->get('Omeka\File\TempFileFactory'),
            $helpers->get('translate'),
            $helpers->get('url'),
            $services->get('ViewRenderer'),
            $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files'),
            $config['temp_dir']
        );
    }
}
