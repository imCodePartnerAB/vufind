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
    ],
  ],
  'controllers' =>
  array (
    'factories' =>
    array (
      'LOTS\\Controller\\MyResearchController' => 'VuFind\\Controller\\MyResearchControllerFactory',
      'LOTS\\Controller\\ForgotPasswordController' => 'VuFind\\Controller\\AbstractBaseFactory',
      'LOTS\\Controller\\ResetPasswordController' => 'VuFind\\Controller\\AbstractBaseFactory',
      'LOTS\\Controller\\SuggestionsController' => 'VuFind\\Controller\\AbstractBaseFactory',
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
    ),
  ),
  'service_manager' =>
  array (
    'allow_override' => true,
    'factories' =>
    array (
      'LOTS\\ILS\\Connection' => 'VuFind\\ILS\\ConnectionFactory',
    ),
    'aliases' =>
    array (
      'VuFind\\ILS\\Connection' => 'LOTS\\ILS\\Connection',
    ),
  ),
'view_helpers' =>
  array (
    'factories' =>
    array (
      'LOTS\\View\\Helper\\SessionTimeout' => 'LOTS\\View\\Helper\\SessionTimeoutFactory',
      'VuFind\\View\\Helper\\Root\\UserList' => 'VuFind\\View\\Helper\\Root\\UserListFactory',
    ),
    'aliases' =>
    array (
      'sessionTimeout' => 'LOTS\\View\\Helper\\SessionTimeout',
      'userList' => 'VuFind\\View\\Helper\\Root\\UserList',
    ),
  ),
  'vufind' =>
  array (
    'allow_override' => true,
    'plugin_managers' =>
    array (
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
/* vufind v11, off: */
/*
$nonTabRecordActions = [
    'AddComment', 'DeleteComment', 'AddTag', 'DeleteTag', 'Save', 'Email', 'SMS',
    'Cite', 'Export', 'RDF', 'Hold', 'Home', 'StorageRetrievalRequest',
    'AjaxTab', 'ILLRequest', 'PDF', 'Epub', 'LinkedText', 'Permalink', 'AddRating'
];
$routeGenerator = new \VuFind\Route\RouteGenerator();
$routeGenerator->addNonTabRecordActions($config, $nonTabRecordActions);
*/
return $config;
