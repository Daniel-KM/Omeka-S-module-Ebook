<?php declare(strict_types=1);
namespace Ebook\Media\FileRenderer;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Media\FileRenderer\RendererInterface;

class Epub implements RendererInterface
{
    /**
     * @var array
     */
    protected $defaultOptions = [
        'attributes' => 'allowfullscreen="1"',
        'style' => 'height: 600px; height: 70vh;',
    ];

    /**
     * @var PhpRenderer
     */
    protected $view;

    /**
     * Render a media via the library epub-reader.js.
     *
     * @param PhpRenderer $view,
     * @param MediaRepresentation $media
     * @param array $options These options are managed for sites:
     *   - attributes: set the attributes to add
     *   - style: set the style
     * @return string
     */
    public function render(PhpRenderer $view, MediaRepresentation $media, array $options = [])
    {
        $this->setView($view);

        $options += $this->defaultOptions;
        $css = $options['style'] ? '<style>.viewer-epub {' . $options['style'] . '}</style>' . "\n" : '';
        $html = '%1$s<iframe height="100%%" width="100%%" %2$s src="%3$s" class="viewer viewer-epub">%4$s</iframe>';
        $url = $view->assetUrl('vendor/epubjs-reader/index.html', 'Ebook') . '&bookPath=' . $media->originalUrl();

        return vsprintf($html, [
            $css,
            $options['attributes'],
            $url,
            $this->fallback($media),
        ]);
    }

    protected function fallback(MediaRepresentation $media)
    {
        $view = $this->getView();
        $text = $view->escapeHtml(sprintf($view->translate('This browser does not support %s (%s).'), // @translate
            $media->extension(), $media->mediaType()));
        $text .= ' ' . sprintf($view->translate('You may %sdownload it%s to view it offline.'), // @translate
            '<a href="' . $media->originalUrl() . '">', '</a>');
        $html = '<p>' . $text . '</p>'
            . '<img src="' . $media->thumbnailUrl('large') . '" height="600px" />';
        return $html;
    }

    /**
     * @param PhpRenderer $view
     */
    protected function setView(PhpRenderer $view): void
    {
        $this->view = $view;
    }

    /**
     * @return PhpRenderer
     */
    protected function getView()
    {
        return $this->view;
    }
}
