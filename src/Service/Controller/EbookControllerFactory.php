<?php
namespace Ebook\Service\Controller;

use Ebook\Controller\Admin\EbookController;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class EbookControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $serviceLocator, $requestedName, array $options = null)
    {
        $ebookController = new EbookController($serviceLocator);

        return $ebookController;
    }
}
