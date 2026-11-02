<?php declare(strict_types=1);

namespace EbookTest;

use CommonTest\AbstractHttpControllerTestCase;
use Ebook\Controller\Admin\EbookController;
use Ebook\Media\FileRenderer\Epub;
use Ebook\Module;

class ModuleTest extends AbstractHttpControllerTestCase
{
    public function testConfigRegistersServices(): void
    {
        $config = (new Module())->getConfig();

        $this->assertArrayHasKey(EbookController::class, $config['controllers']['factories']);
        $this->assertArrayHasKey('ebook', $config['controller_plugins']['factories']);
        $this->assertSame(Epub::class, $config['file_renderers']['invokables']['epub']);
        $this->assertSame('epub', $config['file_renderers']['aliases']['application/epub+zip']);
        $this->assertArrayHasKey('ebook', $config['router']['routes']['admin']['child_routes']);
    }

    public function testExternalAssetsAreInstalled(): void
    {
        $composer = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/composer.json'), true);

        $this->assertArrayHasKey('asset/vendor/epubjs-reader/', $composer['extra']['external-assets']);
        $this->assertFileExists(dirname(__DIR__, 2) . '/asset/vendor/epubjs-reader/reader/index.html');
    }

    public function testEpubRendererUsesReader(): void
    {
        $this->loginAsAdmin();
        $services = $this->getApplicationServiceLocator();
        $view = $services->get('ViewRenderer');

        $media = $this->createMock(\Omeka\Api\Representation\MediaRepresentation::class);
        $media->method('originalUrl')->willReturn('https://example.org/files/original/book.epub');

        $html = (new Epub())->render($view, $media);

        $this->assertStringContainsString('vendor/epubjs-reader/reader/index.html', $html);
        $this->assertStringContainsString('bookPath=https://example.org/files/original/book.epub', $html);
    }
}
