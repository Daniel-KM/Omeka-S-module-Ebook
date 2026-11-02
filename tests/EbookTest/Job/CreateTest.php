<?php declare(strict_types=1);

namespace EbookTest\Job;

use CommonTest\AbstractHttpControllerTestCase;
use CommonTest\JobTestTrait;
use Ebook\Job\Create;
use Omeka\Entity\Job;

class CreateTest extends AbstractHttpControllerTestCase
{
    use JobTestTrait;

    /**
     * @var string[]
     */
    protected array $createdFiles = [];

    public function setUp(): void
    {
        parent::setUp();
        $this->loginAsAdmin();
    }

    public function tearDown(): void
    {
        $this->cleanupResources();
        foreach ($this->createdFiles as $file) {
            @unlink($file);
        }
        $this->getApplicationServiceLocator()->get('Omeka\Connection')
            ->executeStatement('DELETE FROM `ebook_creation`');
        parent::tearDown();
    }

    /**
     * An item linked by another one has subject values, rendered with urls.
     *
     * @see https://gitlab.com/Daniel-KM/Omeka-S-module-Ebook/-/work_items/9
     */
    public function testJobCreatesEbookWithSubjectValues(): void
    {
        $target = $this->createTrackedItem([
            'dcterms:title' => [['type' => 'literal', '@value' => 'Target item']],
        ]);
        $this->createTrackedItem([
            'dcterms:title' => [['type' => 'literal', '@value' => 'Source item']],
            'dcterms:relation' => [['type' => 'resource', 'value_resource_id' => $target->id()]],
        ]);

        $job = $this->runJob(Create::class, $this->jobArgs([$target->id()]));

        $this->assertSame(Job::STATUS_COMPLETED, $job->getStatus(), (string) $job->getLog());

        $url = $this->resourceData($job->getId());
        $this->assertStringStartsWith('admin/ebook/download/', (string) $url);
        $file = $this->basePath() . '/ebook/' . basename($url);
        $this->createdFiles[] = $file;
        $this->assertFileExists($file);
        $this->assertFileExists($this->basePath() . '/ebook/.htaccess');

        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($file));
        $this->assertSame('application/epub+zip', $zip->getFromName('mimetype'));
        // A toc entry without link breaks the reader epub.js.
        $this->assertStringNotContainsString('id="references"', (string) $zip->getFromName('OEBPS/epub3toc.xhtml'));
        $chapter = '';
        for ($i = 0; $i < $zip->numFiles; ++$i) {
            if (preg_match('~^OEBPS/item_\d+_' . $target->id() . '\.xhtml$~', $zip->getNameIndex($i))) {
                $chapter = (string) $zip->getFromIndex($i);
            }
        }
        $this->assertStringContainsString('Source item', $chapter);
        $zip->close();
    }

    public function testJobFailsWithoutWaitingWhenEbookIsNotSaved(): void
    {
        $item = $this->createTrackedItem([
            'dcterms:title' => [['type' => 'literal', '@value' => 'Item']],
        ]);

        $job = $this->runJob(Create::class, ['dcterms:format' => 'application/unknown'] + $this->jobArgs([$item->id()]));

        $this->assertSame(Job::STATUS_ERROR, $job->getStatus());
        $this->assertEmpty($this->resourceData($job->getId()));
    }

    public function testJobCreatesItemWithEbookLocally(): void
    {
        $item = $this->createTrackedItem([
            'dcterms:title' => [['type' => 'literal', '@value' => 'Item']],
        ]);

        $job = $this->runJob(Create::class, ['output' => 'item', 'dcterms:subject' => 'First, Second'] + $this->jobArgs([$item->id()]));

        $this->assertSame(Job::STATUS_COMPLETED, $job->getStatus(), (string) $job->getLog());

        $medias = $this->api()->search('media', ['media_type' => 'application/epub+zip', 'sort_by' => 'id', 'sort_order' => 'desc', 'limit' => 1])->getContent();
        $this->assertNotEmpty($medias);
        $media = reset($medias);
        $created = $media->item();
        $this->createdItemIds[] = $created->id();
        $this->assertSame('upload', $media->ingester());
        $this->assertSame($media->originalUrl(), $this->resourceData($job->getId()));
        $this->assertSame(['First', 'Second'], array_map('strval', $created->value('dcterms:subject', ['all' => true])));
    }

    public function testRegisterJobIsIdempotent(): void
    {
        $plugin = $this->getApplicationServiceLocator()->get('ControllerPluginManager')->get('ebook');
        $plugin->registerJob(999999);
        $plugin->registerJob(999999);

        $count = $this->getApplicationServiceLocator()->get('Omeka\Connection')
            ->fetchOne('SELECT COUNT(*) FROM `ebook_creation` WHERE `job_id` = 999999');
        $this->assertSame(1, (int) $count);
    }

    protected function jobArgs(array $ids): array
    {
        return [
            'batch_action' => 'selected',
            'resource_type' => 'items',
            'resource_ids' => $ids,
            'query' => [],
            'dcterms:title' => 'Test ebook',
            'output' => 'download',
        ];
    }

    protected function resourceData(int $jobId): ?string
    {
        $value = $this->getApplicationServiceLocator()->get('Omeka\Connection')
            ->fetchOne('SELECT `resource_data` FROM `ebook_creation` WHERE `job_id` = ?', [$jobId]);
        return $value === false ? null : $value;
    }

    protected function basePath(): string
    {
        $config = $this->getApplicationServiceLocator()->get('Config');
        return $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
    }
}
