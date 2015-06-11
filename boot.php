<?php

namespace extensions\sessions;
use \frameworks\adapt as adapt;

/* Prevent direct access */
defined('ADAPT_STARTED') or die;

$adapt = $GLOBALS['adapt'];

adapt\base::extend('pget_session', function($_this){
    return $_this->store('adapt.session');
});

adapt\base::extend('pset_session', function($_this, $value){
    $_this->store('adapt.session', $value);
});

$adapt->session = new model_session();


 



?>