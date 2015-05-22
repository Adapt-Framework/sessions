<?php

/* Prevent Direct Access */
defined('ADAPT_STARTED') or die;

$adapt = $GLOBALS['adapt'];
$sql = $adapt->data_source->sql;

/* Create the tables */
$sql->create_table('session')
    ->add('session_id', 'bigint')
    ->add('session_key', 'varchar(128)', false)
    ->add('user_id', 'bigint')
    ->add('ip4_address', 'ip4')
    ->add('ip6_address', 'ip6')
    ->add('user_agent', 'text')
    ->add('date_created', 'datetime')
    ->add('date_modified', 'timestamp')
    ->add('date_expires', 'datetime')
    ->add('date_deleted', 'datetime')
    ->primary_key('session_id')
    ->index('session_key(128)')
    ->foreign_key('user_id', 'user', 'user_id')
    ->execute();

$sql->create_table('session_data')
    ->add('session_data_id', 'bigint')
    ->add('session_id', 'bigint')
    ->add('session_data_key', 'varchar(128)', false)
    ->add('data', 'text')
    ->add('is_serialized', "enum('Yes', 'No')", false, 'No')
    ->add('date_created', 'datetime')
    ->add('date_modifed', 'timestamp')
    ->add('date_deleted', 'datetime')
    ->primary_key('session_data_id')
    ->foreign_key('session_id', 'session', 'session_id')
    ->index('session_data_key(64)')
    ->execute();
?>