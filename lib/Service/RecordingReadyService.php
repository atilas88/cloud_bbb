<?php
namespace OCA\BigBlueButton\Service;

use OCA\BigBlueButton\Service\RoomService;
use OCA\BigBlueButton\Service\UserAuthService;
use OCA\BigBlueButton\BigBlueButton\API;
use OCP\IUserManager;
use OCP\IConfig;

use Psr\Log\LoggerInterface;

class RecordingReadyService {
    /**
     * @var UserAuthService
     */
    private UserAuthService $userAuthService;

    /** @var RoomService */
    private $room_service;
    /** @var API */
    private $server;
    /** @var IUserManager */
    private $userManager;
    /** @var IConfig */
    private $config;
    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        UserAuthService $userAuthService,
        RoomService $room_service,
        API $server,
        IUserManager $userManager,
        IConfig $config,
        LoggerInterface $logger
    ) {
        $this->userAuthService = $userAuthService;
        $this->room_service = $room_service;
        $this->server = $server;
        $this->userManager = $userManager;
        $this->config = $config;
        $this->logger = $logger;
    }
    public function downloadRecording($params,$moderator_mail ="")
    {
        //decode jwt to get meeting_id and record_id
        $decoded = json_decode(base64_decode(explode(".",$params)[1]),true);

        $meeting_id = $decoded["meeting_id"];
        $record_id = $decoded["record_id"];

        $recordingready = $this->server->getRecording($record_id);

        $recording_url = $recordingready["url"];
        //$recording_int_id = $recordingready["id"];
        $recording_name = $recordingready["name"];
        if(str_contains($recording_name,"."))
        {
            $recording_name = str_replace(".","_",$recording_name);
        }
        $coded_name = str_replace(" ","_",$recording_name);
        $user_email = $moderator_mail;

        $room = $this->room_service->findByUid($meeting_id);
        $user_id = $room->getUserId();
        $user = $this->userManager->get($user_id);

        $user_email = empty($user_email) ? $user->getEMailAddress() : $user_email;
        $username = $user->getUID();

        $download_server = $this->config->getAppValue('bbb', 'download.url');
        $bearer_token = $this->config->getAppValue('bbb', 'download.secret');

        if(!empty($username) && !empty($user_email) && !empty($download_server) && !empty($bearer_token))
        {

            $download_url = $download_server."video/".$recording_url.":".$coded_name."/".$user_email;
            //Do curl resquet
            $exec_req = $this->executeRequest($download_url, $bearer_token, $username);
            if($exec_req["ret_val"] === 0)
            {
                $this->logger->info("Executed download successfully",['user_email' => $user_email, 'url' => $download_url]);
            }
            else
            {
                $this->logger->error("Failed to download recording",['user_email' => $user_email, 'url' => $download_url]);
            }
            //Do curl resquest
        }
        else
        {
            $this->logger->error("Cannot download the recording, missing configuration");
        }


    }
    public function checkRecordingServer(string $url, string $secret):string{

        $curl_cmd = "curl $url -H \"Authorization: Bearer $secret\"";
        exec($curl_cmd,$out_put,$ret_val);
        $output = json_decode($out_put[0]);
        if(isset($output) && isset($output->code) && $output->code === 200)
        {
            return "success";
        }
        return "invalid-config";
    }

    /**
     * @param string $url
     * @param string $token
     * @param string $username
     * @return array
     */
    private function executeRequest(string $url, string $token, string $username): array {
        # get encrypt credentials...
        try {
            $x_bearer_json = $this->userAuthService->getEncryptedUserCredentials($username);
        }
        catch (\Exception $e) {
            $x_bearer_json = null;
            $this->logger->error($e->getMessage());
        }

        $curl_cmd = "curl $url -H \"Authorization: Bearer $token\" -H \"X-Bearer: $x_bearer_json\"";
        
        $out_put = null;
        $ret_val = null;
        exec($curl_cmd,$out_put,$ret_val);
        $result = array();
        $result["ret_val"] = $ret_val;
        $result["output"] = $out_put;
        return $result;
    }

}
