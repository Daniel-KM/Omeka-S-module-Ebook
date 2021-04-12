<?php declare(strict_types=1);
namespace Ebook;

/**
 * @var Module $this
 * @var \Laminas\ServiceManager\ServiceLocatorInterface $serviceLocator
 * @var string $newVersion
 * @var string $oldVersion
 *
 * @var \Doctrine\DBAL\Connection $connection
 * @var \Doctrine\ORM\EntityManager $entityManager
 * @var \Omeka\Api\Manager $api
 */
$services = $serviceLocator;
$settings = $services->get('Omeka\Settings');
$config = require dirname(__DIR__, 2) . '/config/module.config.php';
$connection = $services->get('Omeka\Connection');
$entityManager = $services->get('Omeka\EntityManager');
$plugins = $services->get('ControllerPluginManager');
$api = $plugins->get('api');
$space = strtolower(__NAMESPACE__);

if (version_compare($oldVersion, '3.0.2', '<')) {
    $sql = <<<'SQL'
CREATE TABLE ebook_creation (
    id INT AUTO_INCREMENT NOT NULL,
    job_id INT NOT NULL,
    resource_data VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;
SQL;
    $connection->exec($sql);
}

if (version_compare($oldVersion, '3.0.3', '<')) {
    $settings->delete('ebook_pdftk');
}
