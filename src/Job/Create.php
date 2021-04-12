<?php declare(strict_types=1);

namespace Ebook\Job;

use Omeka\Job\AbstractJob;

class Create extends AbstractJob
{
    /**
     * @var array
     */
    protected $args;

    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected $connection;

    public function perform(): void
    {
        $services = $this->getServiceLocator();
        $this->connection = $services->get('Omeka\Connection');

        $this->args = $this->job->getArgs();
        $jobId = $this->job->getId();

        $services->get('ControllerPluginManager')->get('ebook')
            ->task($this->args, $jobId);

        // Wait until the task is finished.
        do {
            $resourceData = $this->getResourceData($jobId);
        } while ($resourceData == null);
    }

    protected function getResourceData($jobId)
    {
        $sql = 'SELECT * FROM `ebook_creation` WHERE `job_id` = "' . $jobId . '";';

        $setting = $this->connection->fetchAssoc($sql);

        return $setting['resource_data'];
    }
}
