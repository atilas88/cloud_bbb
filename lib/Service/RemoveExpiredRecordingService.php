<?php

namespace OCA\BigBlueButton\Service;

use OCA\BigBlueButton\BigBlueButton\API;
use Psr\Log\LoggerInterface;
use OCP\IConfig;
class RemoveExpiredRecordingService
{
    /** @var API */
    private $server;
    /** @var LoggerInterface */
    private $logger;
    /** @var IConfig */
    private $config;

    public function __construct(
        API $server,
        IConfig $config,
        LoggerInterface $logger) {

        $this->server = $server;
        $this->logger = $logger;
        $this->config = $config;
    }

    public function removeExpiredRecordings()
    {
        $all_recordings = $this->server->getAllRecordings();
        $now = new \DateTime();
        $expired_time = intval($this->config->getAppValue('bbb', 'expired.recording'));
        foreach ($all_recordings as $recording)
        {
            $id = $recording["id"];
            $meeting = $recording["meetingId"];
            $endTime = $recording["endTime"];
            //To convert endtime from miliseconds to seconds
            $div = bcdiv($endTime,1000);

            $date_recording = new \DateTime("@$div");
            $diff = $now->diff($date_recording);

            if($expired_time > 0 && $diff->d >= $expired_time)
            {
                $is_recording_deleted = $this->server->deleteRecording($id);
                if($is_recording_deleted)
                {
                    $this->logger->info("The recording:$id for meeting:$meeting was deleted");
                }
                else
                {
                    $this->logger->error("Failed to delete recording:$id for meeting:$meeting");
                }
            }
            else
            {
                $this->logger->info("There is not recording to delete for $expired_time days");
            }
        }
    }
}