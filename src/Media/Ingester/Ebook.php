<?php declare(strict_types=1);

namespace Ebook\Media\Ingester;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Request;
use Omeka\Entity\Media;
use Omeka\File\TempFile;
use Omeka\Media\Ingester\IngesterInterface;
use Omeka\Stdlib\ErrorStore;

class Ebook implements IngesterInterface
{
    public function getLabel()
    {
        return 'Ebook'; // @translate
    }

    public function getRenderer()
    {
        return 'file';
    }

    public function ingest(Media $media, Request $request, ErrorStore $errorStore): void
    {
        $tempFile = $request->getContent()['ingest_tempfile'] ?? null;
        if (!$tempFile instanceof TempFile) {
            $errorStore->addError('ingest_tempfile', 'The ebook is not a temp file.'); // @translate
            return;
        }

        // Keep a standard ingester: this one is only an internal step.
        $media->setIngester('upload');
        $media->setSource($tempFile->getSourceName());

        // The temp file is removed by the caller.
        $tempFile->mediaIngestFile($media, $request, $errorStore, true, true, false);
    }

    public function form(PhpRenderer $view, array $options = [])
    {
        return $view->translate('Used only for the internal process of ebook creation.'); // @translate
    }
}
