<?php
namespace Ebook\Service\Form;

use Ebook\Form\EbookForm;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class EbookFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $form = new EbookForm(null, $options);
        $form->setServiceLocator($services);
        return $form;
    }
}
