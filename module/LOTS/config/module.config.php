<?php

$config = array (
  'controllers' => 
  array (
    'factories' => 
    array (
      'LOTS\\Controller\\MyResearchController' => 'VuFind\\Controller\\AbstractBaseFactory',
      'LOTS\\Controller\\ForgotPasswordController' => 'VuFind\\Controller\\AbstractBaseFactory',
    ),
    'aliases' => 
    array (
      'MyResearch' => 'LOTS\\Controller\\MyResearchController',
      'myresearch' => 'LOTS\\Controller\\MyResearchController',
      'ForgotPassword' => 'LOTS\\Controller\\ForgotPasswordController',
      'forgotpassword' => 'LOTS\\Controller\\ForgotPasswordController',
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
      'db_table' => 
      array (
        'factories' => 
        array (
//          'LOTS\\Db\\Table\\Ratings' => 'VuFind\\Db\\Row\\GatewayFactory',
        ),
        'aliases' => 
        array (
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
#$routeGenerator->addNonTabRecordActions($config, $nonTabRecordActions);
$routeGenerator->addRecordRoutes($config, $nonTabRecordActions);

return $config;
