<?php
namespace Ebook\Service\ControllerPlugin;

use Ebook\Mvc\Controller\Plugin\Ebook;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class EbookFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $entityManager = $services->get('Omeka\EntityManager');
        $tempFileFactory = $services->get('Omeka\File\TempFileFactory');
        $navigationLinkManager = $services->get('Omeka\Site\NavigationLinkManager');
        $viewRenderer = $services->get('ViewRenderer');
        $config = $services->get('Config');
        $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        $tempDir = $config['temp_dir'];
        return new Ebook(
            $entityManager,
            $tempFileFactory,
            $navigationLinkManager,
            $viewRenderer,
            $basePath,
            $tempDir
        );
    }
}
