<?php
namespace Ebook\Service\Controller;

use Ebook\Controller\Admin\EbookController;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class EbookControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $serviceLocator, $requestedName, array $options = null)
    {
        return new EbookController(
            $serviceLocator->get('Omeka\Connection')
        );
    }
}
