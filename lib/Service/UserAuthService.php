<?php


namespace OCA\BigBlueButton\Service;

use Exception;

use OCP\App\AppPathNotFoundException;
use OCP\App\IAppManager;
use OCP\Authentication\LoginCredentials\IStore;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\IURLGenerator;

use Psr\Log\LoggerInterface;

/**
 * Class UserAuthService
 * @property IUserSession userSession
 * @property IRequest request
 * @property IAppManager appManager
 * @property IStore store
 * @property IURLGenerator urlGenerator
 * @property LoggerInterface logger
 * @package OCA\BigBlueButton\Service
 */
class UserAuthService
{
    /**
     * UserAuthService constructor.
     * @param IRequest $request
     * @param IUserSession $userSession
     * @param IAppManager $appManager
     * @param IStore $store
     * @param IURLGenerator $urlGenerator
     * @param LoggerInterface $logger
     */
    public function __construct(
        IRequest $request, IUserSession $userSession, IAppManager $appManager,
        IStore $store, IURLGenerator $urlGenerator, LoggerInterface $logger
    ) {
        $this->request = $request;
        $this->userSession = $userSession;
        $this->appManager = $appManager;
        $this->store = $store;
        $this->urlGenerator = $urlGenerator;
        $this->logger = $logger;
    }

    /**
     * Get encrypt user credentials.
     * @param string $username
     * @return string
     * @throws AppPathNotFoundException
     * @throws Exception
     */
    public function getEncryptedUserCredentials(string $username): string
    {
        $token = $this->getCredentialFile($username);

        if (empty($token)) {
            $message = "UserAuthService:getEncryptedUserCredentials - Credential missing for user $username";
            $this->logger->error($message);
        }

        $userCredentials = json_encode(["user" => $username, "token" => $token], JSON_UNESCAPED_SLASHES);

        return $this->encryptMessage($userCredentials);
    }

    /**
     * Encrypt using RSA.
     * @param $message
     * @return string
     * @throws AppPathNotFoundException
     */
    public function encryptMessage($message): string
    {
        $appBasePath = $this->appManager->getAppPath('bbb');
        $path = "$appBasePath/lib/Resources/rsa/public.pem";
        $publicKey = file_get_contents($path);

        openssl_public_encrypt($message,$encrypted, $publicKey, OPENSSL_PKCS1_OAEP_PADDING);

        return base64_encode($encrypted);
    }

    /**
     * Set user credentials to file.
     * Ex: file_name: username.txt, file_content: access_token
     *
     * NOTE: Should only be used when the user is logged in.
     */
    public function setUserCredentials(): void {
        try {
            $server = $this->urlGenerator->getBaseUrl();

            $userCredentials = $this->store->getLoginCredentials();
            $username = $userCredentials->getLoginName();
            $password = $userCredentials->getPassword();

            $url = "$server/ocs/v2.php/core/getapppassword";
            $curl_command = "curl -u $username:$password -H 'OCS-APIRequest: true' $url";

            exec($curl_command,$data,$code);

            $parseXML = simplexml_load_string(implode("", $data));

            $response = json_decode(json_encode((array)$parseXML), True);
            $token = $response['data']['apppassword'];

            if (empty($token)) {
                $message = "UserAuthService:setUserCredentials - App password is missing";
                $this->logger->error($message);
            }

            # set user credential file...
            $this->setCredentialFile($userCredentials->getUID(), $token);
        }
        catch (Exception $e) {
            $exceptionMessage = $e->getMessage();
            $message = "UserAuthService::setUserCredentials - Fail to set user credentials: $exceptionMessage";
            $this->logger->error($message);
        }
    }

    /**
     * @param string $username
     * @param string $token
     */
    private function setCredentialFile(string $username, string $token): void
    {
        try {
            $resources = dirname(__DIR__) . '/Resources';
            $userData = $resources . '/user_data';

            if(!file_exists($userData)) {
                mkdir($userData);
            }

            $userFilePath = "$userData/$username.txt";

            file_put_contents($userFilePath, $token);
        }
        catch (Exception $e) {
            $exceptionMessage = $e->getMessage();
            $message = "UserAuthService::createCredentialFile - Fail to create file $username.txt. $exceptionMessage";
            $this->logger->error($message);
        }
    }

    /**
     * @param string $username
     * @return string
     * @throws Exception
     */
    private function getCredentialFile(string $username): string
    {
        $userDataPath = dirname(__DIR__) . "/Resources/user_data/$username.txt";
        if (!file_exists($userDataPath)) {
            $message = "UserAuthService::getCredentialFile - File $userDataPath is missing.";
            $this->logger->error($message);
            throw new Exception($message);
        }
        $file_contents = file_get_contents($userDataPath);
        unlink($userDataPath);
        return $file_contents;
    }
}
