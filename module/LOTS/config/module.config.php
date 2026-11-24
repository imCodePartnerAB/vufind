<?php
$config = array (
  'router' => [
    'routes' => [
      'forgotpassword-home' => [
        'type' => 'Laminas\Router\Http\Literal',
        'options' => [
          'route' => '/ForgotPassword',
          'defaults' => [
            'controller' => 'ForgotPassword',
            'action' => 'Home',
          ]
        ],
      ],
      'resetpassword-home' => [
        'type' => 'Laminas\Router\Http\Literal',
        'options' => [
          'route' => '/ResetPassword',
          'defaults' => [
            'controller' => 'ResetPassword',
            'action' => 'Home',
          ]
        ],
      ],
      'bankid-start' => [
        'type' => 'Laminas\Router\Http\Literal',
        'options' => [
          'route' => '/BankID/Start',
          'defaults' => [
            'controller' => 'BankID',
            'action' => 'Start',
          ]
        ],
      ],
      'bankid-callback' => [
        'type' => 'Laminas\Router\Http\Literal',
        'options' => [
          'route' => '/BankID/Callback',
          'defaults' => [
            'controller' => 'BankID',
            'action' => 'Callback',
          ]
        ],
      ],
      'bankid-simulate' => [
        'type' => 'Laminas\Router\Http\Literal',
        'options' => [
          'route' => '/BankID/Simulate',
          'defaults' => [
            'controller' => 'BankID',
            'action' => 'Simulate',
          ]
        ],
      ],
    ],
  ],
  'controllers' =>
  array (
    'factories' =>
    array (
      'LOTS\\Controller\\MyResearchController' => 'VuFind\\Controller\\AbstractBaseFactory',
      'LOTS\\Controller\\ForgotPasswordController' => 'VuFind\\Controller\\AbstractBaseFactory',
      'LOTS\\Controller\\ResetPasswordController' => 'VuFind\\Controller\\AbstractBaseFactory',
      'LOTS\\Controller\\SuggestionsController' => 'VuFind\\Controller\\AbstractBaseFactory',
      'LOTS\\Controller\\BankIDController' => 'VuFind\\Controller\\AbstractBaseFactory',
    ),
    'aliases' =>
    array (
      'MyResearch' => 'LOTS\\Controller\\MyResearchController',
      'myresearch' => 'LOTS\\Controller\\MyResearchController',
      'ForgotPassword' => 'LOTS\\Controller\\ForgotPasswordController',
      'forgotpassword' => 'LOTS\\Controller\\ForgotPasswordController',
      'ResetPassword' => 'LOTS\\Controller\\ResetPasswordController',
      'resetpassword' => 'LOTS\\Controller\\ResetPasswordController',
      'Suggestions' => 'LOTS\\Controller\\SuggestionsController',
      'suggestions' => 'LOTS\\Controller\\SuggestionsController',
      'BankID' => 'LOTS\\Controller\\BankIDController',
      'bankid' => 'LOTS\\Controller\\BankIDController',
    ),
  ),
  'service_manager' =>
  array (
    'allow_override' => true,
    'factories' =>
    array (
      'LOTS\\ILS\\Connection' => 'VuFind\\ILS\\ConnectionFactory',
      // LOTS-90 BankID
      'LOTS\\Auth\\ILSAuthenticator' => 'VuFind\\Auth\\ILSAuthenticatorFactory',
      'LOTS\\BankID\\SessionStore' => 'LOTS\\BankID\\SessionStoreFactory',
      'LOTS\\BankID\\ProviderBuilder' => 'LOTS\\BankID\\ProviderBuilderFactory',
    ),
    'aliases' =>
    array (
      'VuFind\\ILS\\Connection' => 'LOTS\\ILS\\Connection',
      'VuFind\\Auth\\ILSAuthenticator' => 'LOTS\\Auth\\ILSAuthenticator', // LOTS-90
    ),
  ),
  'view_helpers' =>
  array (
    'factories' =>
    array (
      'LOTS\\View\\Helper\\SessionTimeout' => 'LOTS\\View\\Helper\\SessionTimeoutFactory',
    ),
    'aliases' =>
    array (
      'sessionTimeout' => 'LOTS\\View\\Helper\\SessionTimeout',
    ),
  ),
  'vufind' =>
  array (
    'allow_override' => true,
    // BankID login: LOTS.ini [Authentication] login_method decides the effective
    // [Authentication] method in config.ini (see LOTS\Config\LoginMethodDelegatorFactory).
    'config_reader' =>
    array (
      'delegators' =>
      array (
        'config' => ['LOTS\\Config\\LoginMethodDelegatorFactory'],
      ),
    ),
    'plugin_managers' =>
    array (
      'auth' =>
      array (
        'factories' =>
        array (
          'LOTS\\Auth\\BankID' => 'LOTS\\Auth\\BankIDFactory',
        ),
        'aliases' =>
        array (
          'BankID' => 'LOTS\\Auth\\BankID',
          'bankid' => 'LOTS\\Auth\\BankID',
        ),
      ),
      'ils_driver' =>
      array (
        'factories' =>
        array (
          'LOTS\\ILS\\Driver\\KohaRest' => 'LOTS\\ILS\\Driver\\KohaRestFactory',
        ),
        'aliases' =>
        array (
          'VuFind\\ILS\\Driver\\KohaRest' => 'LOTS\\ILS\\Driver\\KohaRest',
        ),
      ),
      'ajaxhandler' =>
      array (
        'factories' =>
        array (
          'LOTS\\AjaxHandler\\GetItemStatuses' => 'LOTS\\AjaxHandler\\GetItemStatusesFactory',
        ),
        'aliases' =>
        array (
          'getItemStatuses' => 'LOTS\\AjaxHandler\\GetItemStatuses',
          'VuFind\\AjaxHandler\\GetItemStatuses' => 'LOTS\\AjaxHandler\\GetItemStatuses',
        ),
      ),
      'db_table' =>
      array (
        'factories' =>
        array (
          'LOTS\\Db\\Table\\PasswordResetToken' => 'VuFind\\Db\\Table\\GatewayFactory',
//          'LOTS\\Db\\Table\\Ratings' => 'VuFind\\Db\\Row\\GatewayFactory',
        ),
        'aliases' =>
        array (
          'PasswordResetToken' => 'LOTS\\Db\\Table\\PasswordResetToken',
//          'VuFind\\Db\\Table\\Ratings' => 'LOTS\\Db\Table\\Ratings',
        ),
      ),
     'db_row' =>
      array (
        'factories' =>
        array (
          'LOTS\\Db\\Row\\Ratings' => 'VuFind\\Db\\Row\\RowGatewayFactory',
        ),
        'aliases' =>
        array (
          'VuFind\\Db\\Row\\Ratings' => 'LOTS\\Db\Row\\Ratings',
        ),
      ),
    ),
  ),
);
// Define non tab record actions
$nonTabRecordActions = [
    'AddComment', 'DeleteComment', 'AddTag', 'DeleteTag', 'Save', 'Email', 'SMS',
    'Cite', 'Export', 'RDF', 'Hold', 'Home', 'StorageRetrievalRequest',
    'AjaxTab', 'ILLRequest', 'PDF', 'Epub', 'LinkedText', 'Permalink', 'AddRating'
];
$routeGenerator = new \VuFind\Route\RouteGenerator();
$routeGenerator->addNonTabRecordActions($config, $nonTabRecordActions);
return $config;
