<?php declare(strict_types=1);

namespace EbookTest;

use CommonTest\AbstractHttpControllerTestCase;
use Ebook\Module;

class UpgradeTest extends AbstractHttpControllerTestCase
{
    public function testUpgradeMovesEbooksToProtectedDirectory(): void
    {
        $services = $this->getApplicationServiceLocator();
        $connection = $services->get('Omeka\Connection');
        $config = $services->get('Config');
        $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');

        // The directory "files" may be shared with a real install, so the
        // existing ebooks are restored after the upgrade.
        $existing = array_map('basename', glob($basePath . '/temp/*.epub') ?: []);

        $filename = '2000-01-01_000000_zz-upgrade-test.epub';
        $other = 'zz-upgrade-test-other.epub';
        @mkdir($basePath . '/temp', 0775, true);
        file_put_contents($basePath . '/temp/' . $filename, 'epub');
        file_put_contents($basePath . '/temp/' . $other, 'epub');
        $connection->executeStatement(
            'INSERT INTO `ebook_creation` (`job_id`, `resource_data`) VALUES (?, ?)',
            [999998, 'files/temp/' . $filename]
        );

        try {
            (new Module())->upgrade('3.4.10', '3.4.11', $services);

            $this->assertFileDoesNotExist($basePath . '/temp/' . $filename);
            $this->assertFileExists($basePath . '/ebook/' . $filename);
            $this->assertFileExists($basePath . '/ebook/.htaccess');
            $this->assertFileExists($basePath . '/temp/' . $other);
            $this->assertSame(
                'admin/ebook/download/' . $filename,
                $connection->fetchOne('SELECT `resource_data` FROM `ebook_creation` WHERE `job_id` = 999998')
            );
        } finally {
            foreach ($existing as $file) {
                if (file_exists($basePath . '/ebook/' . $file)) {
                    rename($basePath . '/ebook/' . $file, $basePath . '/temp/' . $file);
                }
            }
            @unlink($basePath . '/temp/' . $filename);
            @unlink($basePath . '/temp/' . $other);
            @unlink($basePath . '/ebook/' . $filename);
            $connection->executeStatement('DELETE FROM `ebook_creation` WHERE `job_id` = 999998');
        }
    }
}
