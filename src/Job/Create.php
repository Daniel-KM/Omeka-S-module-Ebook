<?php declare(strict_types=1);

namespace Ebook\Job;

use Omeka\Job\AbstractJob;

class Create extends AbstractJob
{
    public function perform(): void
    {
        $result = $this->getServiceLocator()->get('ControllerPluginManager')->get('ebook')
            ->task($this->job->getArgs(), $this->job->getId());

        if (empty($result['url'])) {
            throw new \RuntimeException('The ebook cannot be created. See logs for details.'); // @translate
        }
    }
}
