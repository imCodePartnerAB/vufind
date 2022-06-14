<?php

return array (
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
    ),
  ),
);
