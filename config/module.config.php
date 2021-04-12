<?php declare(strict_types=1);

namespace Ebook;

use Omeka\View\Helper\Thumbnail;

return [
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
    ],
    'file_renderers' => [
        'invokables' => [
            'epub' => Media\FileRenderer\Epub::class,
        ],
        'aliases' => [
            'application/epub+zip' => 'epub',
        ],
    ],
    'view_helpers' => [
        'invokables' => [
            Thumbnail::class => View\Helper\Thumbnail::class,
        ],
        'factories' => [
            'defaultSiteSlug' => Service\ViewHelper\DefaultSiteSlugFactory::class,
        ],
    ],
    'form_elements' => [
        'factories' => [
            Form\EbookForm::class => Service\Form\EbookFormFactory::class,
        ],
    ],
    'controllers' => [
        'factories' => [
            Controller\Admin\EbookController::class => Service\Controller\EbookControllerFactory::class,
        ],
    ],
    'controller_plugins' => [
        'factories' => [
            'ebook' => Service\ControllerPlugin\EbookFactory::class,
        ],
    ],
    'router' => [
        'routes' => [
            'admin' => [
                'child_routes' => [
                    'site' => [
                        'child_routes' => [
                            'slug' => [
                                'child_routes' => [
                                    'ebook' => [
                                        'type' => \Laminas\Router\Http\Literal::class,
                                        'options' => [
                                            'route' => '/ebook',
                                            'defaults' => [
                                                '__NAMESPACE__' => 'Ebook\Controller\Admin',
                                                'controller' => Controller\Admin\EbookController::class,
                                                'action' => 'create-site',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'ebook' => [
                        'type' => \Laminas\Router\Http\Literal::class,
                        'options' => [
                            'route' => '/ebook',
                            'defaults' => [
                                '__NAMESPACE__' => 'Ebook\Controller\Admin',
                                'controller' => Controller\Admin\EbookController::class,
                                'action' => 'create',
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes' => [
                            'default' => [
                                'type' => \Laminas\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/:action',
                                    'constraints' => [
                                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                    ],
                                    'defaults' => [
                                        'action' => 'create',
                                    ],
                                ],
                            ],
                            'created-ebooks' => [
                                'type' => \Laminas\Router\Http\Literal::class,
                                'options' => [
                                    'route' => '/created-ebooks',
                                    'defaults' => [
                                        'controller' => Controller\Admin\EbookController::class,
                                        'action' => 'created-ebooks',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'navigation' => [
        'AdminModule' => [
            [
                'label' => 'Ebooks',
                'route' => 'admin/ebook/created-ebooks',
                'resource' => Controller\Admin\EbookController::class,
                'action' => 'created-ebooks',
            ],
        ],
    ],
    'translator' => [
        'translation_file_patterns' => [
            [
                'type' => 'gettext',
                'base_dir' => dirname(__DIR__) . '/language',
                'pattern' => '%s.mo',
                'text_domain' => null,
            ],
        ],
    ],
    'js_translate_strings' => [
        'Create ebook', // @translate
        'Create ebook with all', // @translate
        'Create ebook with selected', // @translate
        'Go', // @translate
    ],
    'ebook' => [
    ],
];
