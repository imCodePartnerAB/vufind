<?php

return array (
  'controllers' => 
  array (
    'factories' => 
    array (
      'LOTS\\Controller\\MyResearchController' => 'VuFind\\Controller\\AbstractBaseFactory',
    ),
    'aliases' => 
    array (
      'MyResearch' => 'LOTS\\Controller\\MyResearchController',
      'myresearch' => 'LOTS\\Controller\\MyResearchController',
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
);
