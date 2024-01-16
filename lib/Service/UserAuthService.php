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
     */
    public function getEncryptedUserCredentials(string $username): string
    {
        $token = $_COOKIE[$username];

        if (isset($token)) {
            $message = "UserAuthService:getEncryptedUserCredentials - There are no cookies for the user $username";
            $this->logger->error($message);
        }

        $userCredentials = json_encode(["user" => $username, "token" => $token], JSON_UNESCAPED_SLASHES);

        # clear user cookies.
        setcookie($username, "", time() - 3600);

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
     * Set user credentials to cookies. Ex: [username] => access_token.
     * NOTE: Should only be used when the user is logged in.
     */
    public function setCredentialsToCookies(): void {
        try {
            $server = $this->urlGenerator->getBaseUrl();

            $userCredentials = $this->store->getLoginCredentials();
            $username = $userCredentials->getUID();
            $password = $userCredentials->getPassword();

            $url = "$server/ocs/v2.php/core/getapppassword";
            $curl_command = "curl -u $username:$password -H 'OCS-APIRequest: true' $url";

            exec($curl_command,$data,$code);

            $parseXML = simplexml_load_string(implode("", $data));

            $response = json_decode(json_encode((array)$parseXML), True);
            $token = $response['data']['apppassword'];

            if (isset($token)) {
                $message = "UserAuthService:setCredentialsToCookies - App password is missing";
                $this->logger->error($message);
            }

            # set value into cookies...
            setcookie($username, $token, time()+30*24*60*60);
        }
        catch (Exception $e) {
            $exceptionMessage = $e->getMessage();
            $message = "UserAuthService::setCredentialsToCookies - Fail to set credentials to Cookies: $exceptionMessage";
            $this->logger->error($message);
        }
    }
}