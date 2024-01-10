<?php


namespace OCA\BigBlueButton\Service;

use Exception;

use OCP\App\AppPathNotFoundException;
use OCP\App\IAppManager;
use OCP\IRequest;
use OCP\IUserSession;


/**
 * Class UserAuthService
 * @property IUserSession userSession
 * @property IRequest request
 * @property IAppManager appManager
 * @package OCA\BigBlueButton\Service
 */
class UserAuthService
{
    /**
     * UserAuthService constructor.
     * @param IRequest $request
     * @param IUserSession $userSession
     * @param IAppManager $appManager
     */
    public function __construct(IRequest $request, IUserSession $userSession, IAppManager $appManager) {
        $this->request = $request;
        $this->userSession = $userSession;
        $this->appManager = $appManager;
    }

    /**
     * Get encrypt user credentials.
     * @throws Exception
     */
    public function getEncryptedUserCredentials(): string
    {
        $username = $this->userSession->getUser()->getUID();
        $authHeader = $this->request->getHeader('Authorization');

        # check bearer token...
        if (!str_starts_with($authHeader, 'Bearer ')) {
            throw new Exception("Invalid Bearer", 403);
        }

        $token = substr($authHeader, 7);
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
}