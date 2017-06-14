<?php

namespace adapt\sessions{
    
    /* Prevent Direct Access */
    defined('ADAPT_STARTED') or die;
    
    class bundle_sessions extends \adapt\bundle{
        
        public function __construct($data){
            parent::__construct('sessions', $data);
        }
        
        public function boot(){
            if (parent::boot()){
                
                \adapt\base::extend('pget_session', function($_this){
                    return $_this->store('adapt.session');
                });
                
                \adapt\base::extend('pset_session', function($_this, $value){
                    $_this->store('adapt.session', $value);
                });
                
                if (!isset($_SERVER['SHELL'])){
                    $this->session = new model_session();
                }
                
                return true;
            }
            
            return false;
        }
        
    }
    
    
}

