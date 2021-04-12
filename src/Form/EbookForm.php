<?php declare(strict_types=1);
namespace Ebook\Form;

use Laminas\Form\Element;
use Laminas\Form\Form;
use Laminas\View\Helper\Url;
use Omeka\Form\Element\Asset;
use Omeka\Form\Element\CkeditorInline;
use Omeka\Form\Element\ResourceSelect;

class EbookForm extends Form
{
    /**
     * @var Url
     */
    protected $urlHelper;

    public function init(): void
    {
        $urlHelper = $this->getUrlHelper();

        $this->add([
            'name' => 'batch_action',
            'type' => Element\Hidden::class,
        ]);
        $this->add([
            'name' => 'resource_type',
            'type' => Element\Hidden::class,
        ]);
        $this->add([
            'name' => 'resource_ids',
            'type' => Element\Hidden::class,
        ]);
        $this->add([
            'name' => 'query',
            'type' => Element\Hidden::class,
        ]);

        $this->add([
            'name' => 'dcterms:title',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Title', // @translate
            ],
            'attributes' => [
                'id' => 'dcterms-title',
                'required' => true,
            ],
        ]);

        $this->add([
            'name' => 'dcterms:creator',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Author', // @translate
            ],
            'attributes' => [
                'id' => 'dcterms-creator',
                'placeholder' => 'John Smith',
            ],
        ]);

        $this->add([
            'name' => 'author_sort_key',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Sort key of author', // @translate
                'info' => 'For better metadata, the author name should be formatted like "Smith, John".', // @translate
            ],
            'attributes' => [
                'id' => 'author-sort-key',
                'placeholder' => 'Lastname, Firstname',
            ],
        ]);

        $this->add([
            'name' => 'dcterms:subject',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Subjects', // @translate
                'info' => 'Comma-separated list of subjects, for better metadata.', // @translate
            ],
            'attributes' => [
                'id' => 'dcterms-subject',
            ],
        ]);

        $this->add([
            'name' => 'dcterms:description',
            'type' => CkeditorInline::class,
            'options' => [
                'label' => 'Description', // @translate
                'info' => 'The abstract or an extract of the content.',
            ],
            'attributes' => [
                'id' => 'dcterms-description',
            ],
        ]);

        $this->add([
            'name' => 'dcterms:publisher',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Publisher', // @translate
            ],
            'attributes' => [
                'id' => 'dcterms-publisher',
            ],
        ]);

        $this->add([
            'name' => 'publisher_url',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Publisher url', // @translate
            ],
            'attributes' => [
                'id' => 'publisher-url',
            ],
        ]);

        // TODO The Omeka param is a locale, not the language needed for ebook.
        $this->add([
            'name' => 'dcterms:language',
            'type' => 'Omeka\Form\Element\LocaleSelect',
            'options' => [
                'label' => 'Language', // @translate
            ],
            'attributes' => [
                'id' => 'dcterms-language',
                'class' => 'chosen-select',
            ],
        ]);

        $this->add([
            'name' => 'dcterms:rights',
            'type' => CkeditorInline::class,
            'options' => [
                'label' => 'Rights', // @translate
            ],
            'attributes' => [
                'id' => 'dcterms-rights',
                'value' => '<a href="https://creativecommons.org/licenses/by/4.0/">Creative Commons Attribution 4.0 International (CC BY 4.0)</a>',
            ],
        ]);

        $this->add([
            'name' => 'dcterms:identifier',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Identifier', // @translate
                'info' => 'This required metadata may be a unique url, isbn. If empty, a random uuid will be used.',
            ],
            'attributes' => [
                'id' => 'dcterms-identifier',
            ],
        ]);

        $this->add([
            'name' => 'o:resource_template',
            'type' => ResourceSelect::class,
            'options' => [
                'label' => 'Resource template', // @translate
                'info' => 'The resource template allows to order your metadata.', // @translate
                'empty_option' => 'Select a template', // @translate
                'resource_value_options' => [
                    'resource' => 'resource_templates',
                    'query' => [],
                    'option_text_callback' => function ($resourceTemplate) {
                        return $resourceTemplate->label();
                    },
                ],
            ],
            'attributes' => [
                'id' => 'o-resource-template',
                'class' => 'chosen-select',
                'data-placeholder' => 'Select a template', // @translate
                'data-api-base-url' => $urlHelper('api/default', ['resource' => 'resource_templates']),
            ],
        ]);

        // $this->add([
        //     'name' => 'resource_template_only',
        //     'type' => Element\Checkbox::class,
        //     'options' => [
        //         'label' => 'Only properties of template', // @translate
        //         'info' => 'If checked, properties that are not defined in the template will be skipped.',
        //     ],
        //     'attributes' => [
        //         'id' => 'resource-template-only',
        //     ],
        // ]);

        $this->add([
            'name' => 'cover',
            'type' => Asset::class,
            'options' => [
                'label' => 'Cover image', // @translate
            ],
            'attributes' => [
                'id' => 'cover',
            ],
        ]);

        $this->add([
            'name' => 'dcterms:format',
            'type' => Element\Radio::class,
            'options' => [
                'label' => 'Output format', // @translate
                'value_options' => [
                    'application/epub+zip; version=2.0' => 'ePub (v2)', // @translate
                    'application/epub+zip' => 'ePub (v3)', // @translate
                    // 'application/pdf' => 'pdf', // @translate
                    // 'text/html' => 'html', // @translate
                    // 'application/vnd.oasis.opendocument.text' => 'OpenDocument Text (odt)', // @translate
                ],
            ],
            'attributes' => [
                'id' => 'dcterms-format',
                'required' => true,
                'value' => 'application/epub+zip; version=2.0',
            ],
        ]);

        $this->add([
            'name' => 'htmlFormat',
            'type' => Element\Radio::class,
            'options' => [
                'label' => 'HTML format', // @translate
                'value_options' => [
                    'xhtml' => 'XHTML (standard)', // @translate
                    'html5' => 'HTML 5 (recent viewers and readers)', // @translate
                ],
            ],
            'attributes' => [
                'id' => 'html-format',
                'required' => true,
                'value' => 'xhtml',
            ],
        ]);

        $this->add([
            'name' => 'output',
            'type' => Element\Select::class,
            'options' => [
                'label' => 'Output', // @translate
                'value_options' => [
                    'download' => 'Temporary file to download', // @translate
                    'item' => 'New item with attached file', // @translate
                    // 'item_old' => 'Attach it to an existing item', // @translate
                    // 'item_set_add' => 'New item set with attached file', // @translate
                    // 'item_set' => 'Attach it to an item set', // @translate
                    'asset' => 'Asset to download', // @translate
                ],
            ],
            'attributes' => [
                'id' => 'output',
                'required' => true,
                'value' => 'download',
            ],
        ]);

        $inputFilter = $this->getInputFilter();
        $inputFilter->add([
            'name' => 'o:resource_template',
            'required' => false,
        ]);
        $inputFilter->add([
            'name' => 'dcterms:language',
            'required' => false,
        ]);
    }

    /**
     * @param Url $urlHelper
     */
    public function setUrlHelper(Url $urlHelper): void
    {
        $this->urlHelper = $urlHelper;
    }

    /**
     * @return \Laminas\View\Helper\Url
     */
    public function getUrlHelper()
    {
        return $this->urlHelper;
    }
}
