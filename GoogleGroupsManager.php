<?php

namespace polgarz\googlegroups;

use Yii;
use yii\base\InvalidConfigException;

class GoogleGroupsManager extends \yii\base\Module
{
    /**
     * Delegated user email
     * It's required if you use 'service' authentication method
     *
     * @var string
     */
    public $delegatedUserEmail;

    /**
     * Authentication method, can be 'service', or 'oauth'
     *
     * @see https://developers.google.com/identity/protocols/oauth2/service-account
     * @see https://developers.google.com/identity/protocols/oauth2/web-server
     *
     * @var string
     */
    public $authMethod;

    /**
     * File path, which contain the credentials data (json) (required)
     * - If you use service auth method, then this is a service account key
     * - If you use oauth method, then this is a oauth client id credentials file
     *
     * @var string
     */
    public $credentialFilePath;

    /**
     * The token storage file path, it's required when you use 'oauth' authentication method
     *
     * @var string
     */
    public $tokenStorageFilePath;

    /**
     * Groups config (for members sync)
     * This array should be contain another arrays, which has the following keys:
     * - model: The model class
     * - scope: A closure, which has a query attribute, you can specify
     *   the query, the query must return an email column
     * - groupKey: The group key (eg. the group email address)
     *
     * Example:
     *
     *  'groups' => [
     *      [
     *           'groupKey' => 'groupemail@yourgsuitedomain.com',
     *           'model' => 'app\models\User',
     *           'scope' => function($query) {
     *               return $query->select('email')
     *                   ->where(['active' => 1]);
     *            },
     *       ],
     *       ...
     *   ],
     *
     * @var array
     */
    public $groups;

    /**
     * @var string
     */
    const AUTH_METHOD_SERVICE = 'service';

    /**
     * @var string
     */
    const AUTH_METHOD_OAUTH = 'oauth';

    /**
     * {@inheritdoc}
     */
    public function init()
    {
        parent::init();

        if (Yii::$app instanceof \yii\console\Application) {
            $this->controllerNamespace = 'polgarz\googlegroups\commands';
        }

        if (!$this->authMethod) {
            throw new InvalidConfigException('You have to set authMethod property.');
        } elseif (!in_array($this->authMethod, [self::AUTH_METHOD_OAUTH, self::AUTH_METHOD_SERVICE])) {
            throw new InvalidConfigException('The authMethod property can only be \'' .
                self::AUTH_METHOD_SERVICE . '\' or \'' . self::AUTH_METHOD_OAUTH . '\'');
        }

        if (!$this->credentialFilePath) {
            throw new InvalidConfigException('You have to set credentialFilePath property.');
        } elseif (!is_file(Yii::getAlias($this->credentialFilePath))) {
            throw new InvalidConfigException('Credential file not found!');
        }

        if ($this->authMethod === self::AUTH_METHOD_OAUTH) {
            if (!$this->tokenStorageFilePath) {
                throw new InvalidConfigException('If you use OAuth2 authentication method, ' .
                    'you have to set tokenStorageFilePath property.');
            }
        } elseif ($this->authMethod === self::AUTH_METHOD_SERVICE) {
            if (!$this->delegatedUserEmail) {
                throw new InvalidConfigException('If you use service user authentication method, ' .
                    'you have to set delegatedUserEmail property.');
            }
        }

        if (is_array($this->groups)) {
            foreach ($this->groups as $group) {
                if (!isset($group['model'])) {
                    throw new InvalidConfigException('Each group must have a \'model\' property');
                }
                if (!isset($group['groupKey'])) {
                    throw new InvalidConfigException('Each group must have a \'groupKey\' property');
                }
            }
        }
    }

    /**
     * Returns an authorized API client.
     * @return Google_Client the authorized client object
     */
    public function getClient()
    {
        $client = new \Google_Client();
        $client->setScopes([
            \Google_Service_Directory::ADMIN_DIRECTORY_GROUP,
            \Google_Service_Directory::ADMIN_DIRECTORY_GROUP_MEMBER
        ]);

        if ($this->authMethod === self::AUTH_METHOD_SERVICE) {
            $client->useApplicationDefaultCredentials();
            $client->setSubject($this->delegatedUserEmail);
            $client->setAuthConfig(Yii::getAlias($this->credentialFilePath));
        } elseif ($this->authMethod === self::AUTH_METHOD_OAUTH) {
            $client->setAuthConfig(Yii::getAlias($this->credentialFilePath));
            $client->setAccessType('offline');
            $client->setPrompt('select_account consent');

            // Load previously authorized token from a file, if it exists.
            // The file token.json stores the user's access and refresh tokens, and is
            // created automatically when the authorization flow completes for the first
            // time.
            $tokenPath = Yii::getAlias($this->tokenStorageFilePath);
            if (file_exists($tokenPath)) {
                $accessToken = json_decode(file_get_contents($tokenPath), true);
                $client->setAccessToken($accessToken);
            }

            // If there is no previous token or it's expired.
            if ($client->isAccessTokenExpired()) {
                // Refresh the token if possible, else fetch a new one.
                if ($client->getRefreshToken()) {
                    $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
                } else {
                    // Request authorization from the user.
                    $authUrl = $client->createAuthUrl();
                    printf("Open the following link in your browser:\n%s\n", $authUrl);
                    print 'Enter verification code: ';
                    $authCode = trim(fgets(STDIN));

                    // Exchange authorization code for an access token.
                    $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
                    $client->setAccessToken($accessToken);

                    // Check to see if there was an error.
                    if (array_key_exists('error', $accessToken)) {
                        throw new Exception(join(', ', $accessToken));
                    }
                }
                // Save the token to a file.
                if (!file_exists(dirname($tokenPath))) {
                    mkdir(dirname($tokenPath), 0700, true);
                }
                file_put_contents($tokenPath, json_encode($client->getAccessToken()));
            }
        }

        return $client;
    }
}
