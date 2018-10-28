<?php
namespace Ebook\Form;

use Zend\Form\Element;
use Zend\Form\Form;

class ConfigForm extends Form
{
    public function init()
    {
        $this->add([
            'name' => 'ebook_pdftk',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Path to pdftk', // @translate
                'info' => 'Only needed to create a pdf file.', // @translate
            ],
        ]);
    }
}
