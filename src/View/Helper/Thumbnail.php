<?php declare(strict_types=1);
namespace Ebook\View\Helper;

use Omeka\Api\Representation\AbstractRepresentation;

/**
 * View helper for rendering a thumbnail image.
 */
class Thumbnail extends \Omeka\View\Helper\Thumbnail
{
    /**
     * Render a thumbnail image as a xhtml tag.
     *
     * Same as core view helper, but as xhtml (ending with "/>").
     * @see \Omeka\View\Helper\Thumbnail
     * @todo use view helper "doctype" to set xhtml.
     *
     * @param AbstractRepresentation $representation
     * @param string $type
     * @param array $attribs
     * @return string
     */
    public function __invoke(AbstractRepresentation $representation, $type, array $attribs = [])
    {
        $html = parent::__invoke($representation, $type, $attribs);
        return $html
            ? mb_substr($html, 0, -1) . '/>'
            : '';
    }
}
