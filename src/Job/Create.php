<?php

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

    public function perform()
    {
        $this->args = $this->job->getArgs();

        $services = $this->getServiceLocator();

        $this->connection = $services->get('Omeka\Connection');

        $job_id = $this->job->getId();

        $services->get('ControllerPluginManager')->get('ebook')
            ->task($this->args, $job_id);

        do {
            $resource_data = $this->getResourceData($job_id);
        } while ($resource_data == null);
    }

    protected function getResourceData($job_id)
    {
        $sql = 'SELECT * FROM `ebook_creation` WHERE `job_id` = "' . $job_id . '";';

        $setting = $this->connection->fetchAssoc($sql);

        return $setting['resource_data'];
    }
}
