<?php declare(strict_types=1);

namespace Ebook;

use Common\Stdlib\PsrMessage;

/**
 * @var Module $this
 * @var \Laminas\ServiceManager\ServiceLocatorInterface $services
 * @var string $newVersion
 * @var string $oldVersion
 *
 * @var \Omeka\Api\Manager $api
 * @var \Laminas\Log\Logger $logger
 * @var \Omeka\Settings\Settings $settings
 * @var \Doctrine\DBAL\Connection $connection
 * @var \Omeka\Settings\SiteSettings $siteSettings
 * @var \Doctrine\ORM\EntityManager $entityManager
 * @var \Omeka\Mvc\Controller\Plugin\Messenger $messenger
 */
$plugins = $services->get('ControllerPluginManager');
$api = $plugins->get('api');
$logger = $services->get('Omeka\Logger');
$settings = $services->get('Omeka\Settings');
$translate = $plugins->get('translate');
// $translator = $services->get('MvcTranslator');
$connection = $services->get('Omeka\Connection');
$messenger = $plugins->get('messenger');
$siteSettings = $services->get('Omeka\Settings\Site');
$entityManager = $services->get('Omeka\EntityManager');

if (!method_exists($this, 'checkModuleActiveVersion') || !$this->checkModuleActiveVersion('Common', '3.4.95')) {
    $message = new \Omeka\Stdlib\Message(
        $translate('The module %1$s should be upgraded to version %2$s or later.'), // @translate
        'Common', '3.4.95'
    );
    $messenger->addError($message);
    throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $translate('Missing requirement. Unable to upgrade.')); // @translate
}

if (version_compare($oldVersion, '3.0.2', '<')) {
    $sql = <<<'SQL'
        CREATE TABLE IF NOT EXISTS ebook_creation (
            id INT AUTO_INCREMENT NOT NULL,
            job_id INT NOT NULL,
            resource_data VARCHAR(255) DEFAULT NULL,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;
        SQL;
    $connection->executeStatement($sql);
}

if (version_compare($oldVersion, '3.0.3', '<')) {
    $settings->delete('ebook_pdftk');
}

if (version_compare($oldVersion, '3.4.11', '<')) {
    $this->updateWhitelist();

    // The ebooks may contain private data, so move them from the temp directory
    // to a protected one.
    $config = $services->get('Config');
    $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
    $dirPath = $services->get('Common\DirectoryManager')->checkDestinationDir($basePath . '/ebook', true);
    $count = 0;
    foreach ($dirPath ? glob($basePath . '/temp/*.epub') : [] as $filepath) {
        $filename = basename($filepath);
        if (!preg_match('~^\d{4}-\d{2}-\d{2}_\d{6}_[\w.-]*\.epub$~', $filename)
            || !@rename($filepath, $dirPath . '/' . $filename)
        ) {
            continue;
        }
        $connection->executeStatement(
            'UPDATE `ebook_creation` SET `resource_data` = ? WHERE `resource_data` = ?',
            ['admin/ebook/download/' . $filename, 'files/temp/' . $filename]
        );
        ++$count;
    }
    if ($count) {
        $message = new PsrMessage(
            '{count} ebooks were moved to the protected directory "files/ebook".', // @translate
            ['count' => $count]
        );
        $messenger->addSuccess($message);
    }
}
