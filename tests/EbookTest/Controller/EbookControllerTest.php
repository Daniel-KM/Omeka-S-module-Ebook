<?php declare(strict_types=1);

namespace EbookTest\Controller;

use CommonTest\AbstractHttpControllerTestCase;
use Laminas\EventManager\Event;
use Laminas\Router\Http\RouteMatch;

class EbookControllerTest extends AbstractHttpControllerTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        $this->loginAsAdmin();
    }

    public function testCreatedEbooksPageIsDisplayed(): void
    {
        $this->dispatch('/admin/ebook/created-ebooks');

        $this->assertResponseStatusCode(200);
        $this->assertControllerName(\Ebook\Controller\Admin\EbookController::class);
        $this->assertActionName('created-ebooks');
    }

    public function testDownloadSendsEbook(): void
    {
        $dirPath = $this->basePath() . '/ebook';
        @mkdir($dirPath, 0775, true);
        $file = $dirPath . '/zz-test-download.epub';
        file_put_contents($file, 'epub');
        try {
            // The file is sent directly to the output and the output buffers
            // are closed, so restore them for phpunit.
            $level = ob_get_level();
            $this->dispatch('/admin/ebook/download/zz-test-download.epub');
            while (ob_get_level() < $level) {
                ob_start();
            }
            $this->assertResponseStatusCode(200);
            $this->assertResponseHeaderContains('Content-Type', 'application/epub+zip');
        } finally {
            @unlink($file);
        }
    }

    public function testDownloadMissingEbookIsNotFound(): void
    {
        $this->dispatch('/admin/ebook/download/..htaccess');

        $this->assertResponseStatusCode(404);
    }

    public function testDownloadRequiresAuthentication(): void
    {
        $this->logout();
        $this->dispatch('/admin/ebook/download/zz-test-download.epub');

        $this->assertNotResponseStatusCode(200);
    }

    public function testCreateWithoutResourcesRedirects(): void
    {
        $this->dispatch('/admin/ebook');

        $this->assertResponseStatusCode(302);
    }

    /**
     * The resource type of the module takes precedence over the one appended
     * by other modules, deduced from the url (here "browse").
     */
    public function testCreateUsesOwnResourceType(): void
    {
        $item = $this->api()->create('items', [])->getContent();
        try {
            $this->dispatch('/admin/ebook', 'GET', [
                'ebook_resource_type' => 'items',
                'resource_type' => 'browse',
                'resource_ids' => [$item->id()],
            ]);
            $this->assertResponseStatusCode(200);
            $this->assertActionName('create');
        } finally {
            $this->api()->delete('items', $item->id());
        }
    }

    public function testCreateRefusesMedia(): void
    {
        $this->dispatch('/admin/ebook', 'GET', ['resource_type' => 'media', 'resource_ids' => [1]]);

        $this->assertResponseStatusCode(302);
    }

    /**
     * The browse url may be "/admin/item" or "/admin/item/browse", so the url
     * and the resource type are passed to the script.
     *
     * The core browse pages cannot be dispatched in tests (user settings), so
     * the listener is called directly.
     *
     * @dataProvider browseControllersProvider
     */
    public function testBrowseInjectsAbsoluteUrl(string $controller, ?string $resourceType): void
    {
        $this->dispatch('/admin/ebook/created-ebooks');
        $services = $this->getApplicationServiceLocator();
        $services->get('Application')->getMvcEvent()
            ->setRouteMatch(new RouteMatch(['controller' => $controller, 'action' => 'browse']));
        $view = $services->get('ViewRenderer');
        $view->headScript()->exchangeArray([]);

        $module = new \Ebook\Module();
        $module->setServiceLocator($services);
        $module->handleAdminViewBrowseResource(new Event('view.browse.before', $view));

        $script = (string) $view->headScript();
        if ($resourceType === null) {
            $this->assertStringNotContainsString('ebook-admin.js', $script);
            return;
        }
        $this->assertStringContainsString('ebook-admin.js', $script);
        $this->assertMatchesRegularExpression('~var ebook = \\{"resourceType":"' . $resourceType . '","urlCreate":"[^"]*/admin/ebook"\\};~', $script);
    }

    public function browseControllersProvider(): array
    {
        return [
            'items' => ['Omeka\\Controller\\Admin\\Item', 'items'],
            'item sets' => ['item-set', 'item_sets'],
            'media' => ['Omeka\\Controller\\Admin\\Media', null],
        ];
    }

    protected function basePath(): string
    {
        $config = $this->getApplicationServiceLocator()->get('Config');
        return $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
    }
}
