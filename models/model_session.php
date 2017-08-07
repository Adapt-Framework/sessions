<?php

namespace adapt\sessions{
    
    /* Prevent direct access */
    defined('ADAPT_STARTED') or die;
    
    class model_session extends model{
        
        const EVENT_LOAD_BY_SESSION_KEY = 'model.on_load_by_session_key';
        
        public function __construct($id = null){
            parent::__construct('session', $id);
            
            $this->delete_expired_sessions();
            
            if (!$this->is_loaded){
                /* Do we have a session id? */
                if ($this->setting('session.set_cookie') == 'Yes'){
                    $session_key = $this->cookie('session_key');
                }
                
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
                $this->session_key = md5(time() . rand(1, 999999) . guid() . $address . $this->user_agent);
                
                /* Set the session expiry time (if required) */
                $session_timeout = $this->bundles->setting('sessions.expires');
                
                if (!$session_timeout){
                    $session_timeout = 60 * 24 * 365;
                }
                
                $this->session_timeout = $session_timeout;
                $this->date_expires = $this->data_source->sql->select("now() + interval {$session_timeout} minute as x")->execute(0)->results()[0]['x'];
            }
            
            $this->date_accessed = new sql_now();
            $this->save();
            
            /* Send the cookie */
            if ($this->setting('sessions.set_cookie') == 'Yes'){
                $this->cookie('session_key', $this->session_key);
            }
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
            $session_expires = $this->setting('sessions.expires') ?: 0;
            
            /* Make sure name is set */
            if (isset($key)){
                
                $sql = $this->data_source->sql;
                $sql->select('*')
                    ->from('session')
                    ->where(
                        new sql_and(
                            new sql_cond('session_key', sql::EQUALS, q($key)),
                            new sql_cond('date_deleted', sql::IS, sql::NULL),
                            new sql_cond('date_expires', sql::GREATER_THAN_OR_EQUALS, new sql_now())
                        )
                    );
                
                $results = $sql->execute(0)->results();
//                
//                /* We need to check this table has a session_key field */
//                $fields = array_keys($this->_data);
//                
//                if (in_array('session_key', $fields)){
//                    $sql = $this->data_source->sql;
//                    
//                    if ($session_expires){
//                        $sql->select("*, if (date_accessed > now() - interval {$session_expires} minute, 'Valid', 'Invalid') as valid");
//                    }else{
//                        $sql->select("*");
//                    }
//                    
//                    $sql->from($this->table_name);
//                    
//                    /* Do we have a date_deleted field? */
//                    if (in_array('date_deleted', $fields)){
//                        
//                        $name_condition = new sql_cond('session_key', sql::EQUALS, sql::q($key));
//                        $date_deleted_condition = new sql_cond('date_deleted', sql::IS, new sql_null());
//                        
//                        $sql->where(new sql_and($name_condition, $date_deleted_condition));
//                        
//                    }else{
//                        
//                        $sql->where(new sql_cond('session_key', sql::EQUALS, sql::q($key)));
//                    }
//                    
//                    if ($session_expires){
//                        $sql->having(new sql_cond('valid', sql::EQUALS, q('Valid')));
//                    }
//                    
//                    /* Get the results */
//                    $results = $sql->execute(0)->results();
                    
                    if (count($results) == 1){
                        $this->trigger(self::EVENT_ON_LOAD_BY_NAME);
                        return $this->load_by_data($results[0]);
                    }elseif(count($results) == 0){
                        $this->error("Unable to find a record with a session_key {$key}");
                    }elseif(count($results) > 1){
                        $this->error(count($results) . " records found with a session_key of '{$key}'.");
                    }
                    
                //}else{
                //    $this->error('Unable to load by name, this table has no \'session_key\' field.');
                //}
            }else{
                $this->error('Unable to load by session_key, no session_key supplied');
            }
            
            return false;
        }
        
        public function load_by_data($data = array()) {
            if (parent::load_by_data($data)){
                
                $results = $this->data_source->sql
                    ->select('now() as n', "now() + interval {$this->session_timeout} minute as e")
                    ->execute()
                    ->results()[0];
                
                $this->date_accessed = $results['n'];
                $this->date_expires = $results['e'];
                $this->save();
                
                return true;
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
        
        public function delete_expired_sessions(){
            $sql = $this->data_source->sql;
            $sql->update('session')
                ->set('date_deleted', new sql_now())
                ->where(
                    new sql_and(
                        new sql_cond('date_expires', sql::LESS_THAN, new sql_now()),
                        new sql_cond('date_deleted', sql::IS, sql::NULL)
                    )
                )
                ->execute(0);
        }
        
    }
    
}

