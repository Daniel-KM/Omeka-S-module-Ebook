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

        $entityManager = $services->get('Omeka\EntityManager');
        $tempFileFactory = $services->get('Omeka\File\TempFileFactory');
        $navigationLinkManager = $services->get('Omeka\Site\NavigationLinkManager');
        $viewRenderer = $services->get('ViewRenderer');
        $config = $services->get('Config');
        $plugins = $services->get('ControllerPluginManager');
        $api = $plugins->get('api');
        $connection = $services->get('Omeka\Connection');
        $logger = $services->get('Omeka\Logger');

        $helpers = $services->get('ViewHelperManager');
        $translate = $helpers->get('translate');
        $url = $helpers->get('url');
        $settings = $services->get('Omeka\Settings');
        $navigationLink = $helpers->get('navigationLink');

        $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        $tempDir = $config['temp_dir'];
        return new Ebook(
            $entityManager,
            $tempFileFactory,
            $navigationLinkManager,
            $viewRenderer,
            $api,
            $connection,
            $basePath,
            $tempDir,
            $logger,
            $translate,
            $url,
            $settings,
            $navigationLink
        );
    }
}
