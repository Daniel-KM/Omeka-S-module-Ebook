<?php declare(strict_types=1);

namespace Ebook\Service\Controller;

use Ebook\Controller\Admin\EbookController;
use Psr\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class EbookControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $serviceLocator, $requestedName, ?array $options = null)
    {
        $config = $serviceLocator->get('Config');
        return new EbookController(
            $serviceLocator->get('Omeka\Connection'),
            $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files')
        );
    }
}
