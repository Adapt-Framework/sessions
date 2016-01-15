<?php

namespace adapt\sessions{
    
    /* Prevent direct access */
    defined('ADAPT_STARTED') or die;
    
    class model_session extends model{
        
        const EVENT_LOAD_BY_SESSION_KEY = 'model.on_load_by_session_key';
        
        public function __construct($id = null){
            parent::__construct('session', $id);
            
            if (!$this->is_loaded){
                /* Do we have a session id? */
                $session_key = $this->cookie('session_key');
                
                if (!is_null($session_key)){
                    $this->load_by_session_key($session_key);
                }
            }
            
            if (!$this->is_loaded){
                
                /* We need to clear any errors we have to prevent save from failing */
                $this->errors(true);
                
                /* We still don't have a session, so lets create a new one */
                
                /* Is the client connecting via ipv4 or ipv6? */
                $address = $_SERVER['REMOTE_ADDR'];
                if ($this->sanitize->validate('ip4', $address)){
                    $this->ip4_address = $address;
                }elseif($this->sanitize->validate('ip6', $address)){
                    $this->ip6_address = $address;
                }
                
                /* Set the user agent */
                $this->user_agent = $_SERVER['HTTP_USER_AGENT'];
                
                /* Set the key */
                $this->session_key = md5(time() . rand(1, 999999));
            }
            
            $this->date_accessed = new \adapt\sql('now()', $this->data_source);
            $this->save();
            
            
            //TODO: Invalidate old sessions
            
            /* Send the cookie */
            $this->cookie('session_key', $this->session_key);
        }
        
        public function __wakeup(){
            
        }
        
        /* Over-ride the initialiser to auto load children */
        public function initialise(){
            /* We must initialise first! */
            parent::initialise();
            
            /* We need to limit what we auto load */
            $this->_auto_load_only_tables = array(
                'session_data'
            );
            
            /* Switch on auto loading */
            $this->_auto_load_children = true;
        }
        
        public function load_by_session_key($key){
            $this->initialise();
            
            /* Make sure name is set */
            if (isset($key)){
                
                /* We need to check this table has a session_key field */
                $fields = array_keys($this->_data);
                
                if (in_array('session_key', $fields)){
                    $sql = $this->data_source->sql;
                    
                    $sql->select('*')
                        ->from($this->table_name);
                    
                    /* Do we have a date_deleted field? */
                    if (in_array('date_deleted', $fields)){
                        
                        $name_condition = new \adapt\sql_condition(new \adapt\sql('session_key'), '=', $key);
                        $date_deleted_condition = new \adapt\sql_condition(new\adapt\sql('date_deleted'), 'is', new \adapt\sql('null'));
                        
                        $sql->where(new \adapt\sql_and($name_condition, $date_deleted_condition));
                        
                    }else{
                        
                        $sql->where(new \adapt\sql_condition(new \adapt\sql('session_key'), '=', $key));
                    }
                    
                    /* Get the results */
                    $results = $sql->execute()->results();
                    
                    if (count($results) == 1){
                        $this->trigger(self::EVENT_ON_LOAD_BY_NAME);
                        return $this->load_by_data($results[0]);
                    }elseif(count($results) == 0){
                        $this->error("Unable to find a record with a session_key {$key}");
                    }elseif(count($results) > 1){
                        $this->error(count($results) . " records found with a session_key of '{$key}'.");
                    }
                    
                }else{
                    $this->error('Unable to load by name, this table has no \'session_key\' field.');
                }
            }else{
                $this->error('Unable to load by session_key, no session_key supplied');
            }
            
            return false;
        }
        
        
        public function remove_data($key){
            $children = $this->get();
            
            foreach($children as $child){
                if ($child instanceof \adapt\model && $child->table_name == 'session_data'){
                    if ($child->session_data_key == $key){
                        $child->delete();
                    }
                }
            }
                
            
            return null;
        }
        
        public function data($key, $value = null){
            $children = $this->get();
            
            if (is_null($value)){
                foreach($children as $child){
                    if ($child instanceof \adapt\model && $child->table_name == 'session_data'){
                        if ($child->session_data_key == $key){
                            if ($child->is_serialized == 'Yes'){
                                return unserialize($child->data);
                            }else{
                                return $child->data;
                            }
                        }
                    }
                }
                
            }else{
                foreach($children as $child){
                    if ($child instanceof \adapt\model && $child->table_name == 'session_data'){
                        if ($child->session_data_key == $key){
                            if (is_object($value) || is_array($value)){
                                $child->data = serialize($value);
                                $child->is_serialized = 'Yes';
                            }else{
                                $child->is_serialized = 'No';
                                $child->data = $value;
                            }
                            
                            return null;
                        }
                    }
                }
                
                /* We didn't find the setting, so let create a new one */
                $setting = new model_session_data();
                $setting->session_data_key = $key;
                if (is_object($value) || is_array($value)){
                    $setting->data = serialize($value);
                    $setting->is_serialized = 'Yes';
                }else{
                    $setting->is_serialized = 'No';
                    $setting->data = $value;
                }
                
                $this->add($setting);
            }
            
            return null;
        }
        
    }
    
}

?>