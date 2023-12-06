<?php

namespace OCA\BigBlueButton\BackgroundJob;

use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\BackgroundJob\TimedJob;
use OCA\BigBlueButton\Service\RemoveExpiredRecordingService;

class CheckExpiredRecording extends TimedJob
{
    /** @var IJobList */
    private $jobList;
    /** @var RemoveExpiredRecordingService */
    private $service;

    public function __construct(
        ITimeFactory $time,
        IJobList $jobList,
        RemoveExpiredRecordingService $service
    )
    {
        parent::__construct($time);
        $this->jobList = $jobList;
        $this->service = $service;
        // Run twice a day
        $this->setInterval(12 * 3600);

    }

    protected function run($arguments = []) {
        $this->service->removeExpiredRecordings();
    }
}