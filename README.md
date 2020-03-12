Yii2 Google Groups manager module
=======

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist polgarz/google-groups-manager "~0.1"
```

or add

```
"polgarz/google-groups-manager": "~0.1"
```

to the require section of your `composer.json` file.

Console configuration
-----

```php
'modules' => [
    'google-groups-manager' => [
        'class' => 'polgarz\googlegroups\GoogleGroupsManager',
        'authMethod' => 'service', // can be 'service', or 'oauth' (Service Account, or OAuth2)
        'delegatedUserEmail' => 'example@example.com', // required when authMethod is 'service'
        'tokenStorageFilePath' => '@app/token.json', // required when authMethod is 'oauth'
        'credentialFilePath' => '@app/credentials.json', // OAuth2 Client ID credentials json or Service Account key
        'groups' => [ // if you'd like to syncronize group members
            [
                'groupKey' => 'yourgroup@yourgsuitedomain.com',
                'model' => 'app\models\User',
                'scope' => function($query) {
                    return $query->select('email')
                        ->where(['active' => 1]);
                },
            ]
        ],
    ],
],
```

Usage
-----
```bash
php yii google-groups-manager/members/add groupKey email
php yii google-groups-manager/members/syncronize
php yii google-groups-manager/members/delete groupKey email
php yii google-groups-manager/members/list groupKey
```

TBD