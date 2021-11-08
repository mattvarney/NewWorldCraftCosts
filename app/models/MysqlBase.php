<?php

use Phalcon\Mvc\Model;
use Phalcon\Db\RawValue;

class MysqlBase extends Model
{
    const CONNECTION_SERVICE = "db";

    protected $_defaulted_fields = array();
    // _unique_constraint_fields is to prevent issues with findFirstOrCreate
    protected $_unique_constraint_fields = array();
    protected static $_cache = false;
    protected static $_hash_type = 'md5';
    public $_cacheKey = null;
    public $_refreshDefaults = false;
    public $_duplicate = false;

    public function initialize()
    {
        $this->setConnectionService(self::CONNECTION_SERVICE);
    }

    public static function find($params = null): Phalcon\Mvc\Model\ResultsetInterface
    {
        return parent::find($params);
    }

    public static function findFirst($params = null)
    {
        return parent::findFirst($params);
    }

    public function create(): bool
    {
        self::handleHashes();
        $return = parent::create();
        if(!empty($this->getMessages())){
            echo $this->getSource();
            print_r($this->getMessages());
        }
        return $return;
    }

    public function save(): bool
    {
        // handle hashes
        self::handleHashes();
        return parent::save();
    }

    public function beforeValidation()
    {
        foreach ($this as $property => $value) {
            if (strpos($property, '_') !== 0 && $property != 'id') {

                if (in_array($property, $this->_defaulted_fields)) {
                    $rawValue = new RawValue('default');
                } else {
                    $rawValue = new RawValue('');
                }

                if ($value === '') {
                    $this->$property = $rawValue;
                }
            }
        }
    }

    public function beforeValidationOnCreate()
    {
        foreach ($this->_defaulted_fields as $field) {
            if (!isset($this->$field)) {
                $this->$field = new RawValue('default');
            }
        }
    }

    public static function findFirstByHash($value = null, $hashed = false, $hashField = 'hash')
    {
        $hash = $value;
        if (property_exists(get_called_class(), $hashField)) {
            if (!$hashed) {
                $hash = self::hashValue($value);
            }

            return static::findFirst(array(
                "$hashField = :hash:",
                'bind' => array('hash' => $hash)
            ));
        } else {
            throw new \Exception('Error in class ' . get_called_class() . ": $hashField is not defined.", 1);
        }
    }

    /**
     * Find the record of the current model if it exists by using all of the
     * set properties on the object. If the record existed then it will update
     * itself with the data found.
     *
     * @return Bool - Returns success
     */
    public function findFirstOrCreate()
    {
        $params = $this->createFindParams();

        $record = static::findFirst($params);

        if ($record) {
            // record was found, update itself with found data
            $this->mergeProperties($record);
            $this->_duplicate = true;
            return true;
        } else {
            // record didn't exist, create it, if there is an exception it's
            // likely a constraint issue due to race conditions
            try {
                $result = self::create();

                if ($result) {
                    return $result;
                } else {
                    throw new \Exception('Error saving record in ' . get_called_class() . " , probably duplicate entry issue.", 1);
                }
                return ;
            } catch (\Exception $e) {
                if (preg_match('/Duplicate entry/', $e->getMessage()) || preg_match('/23000/', $e->getMessage())) {
                    $record = static::findFirst($params);

                    if ($record) {
                        $this->mergeProperties($record);
                    } else {
                        $record = parent::findFirst($params);

                        if ($record) {
                            $this->mergeProperties($record);
                            return true;
                        }

//                        $this->logMessage("A record wasn't found when one should exist." . " Screw you, this piece of crap is broken", 3);
//                        $this->logMessage(var_export($this->toArray(), 1), 3);
//                        $this->logMessage("What was found: ", 3);
//                        $this->logMessage(var_export($record, 1), 3);
                        throw $e;
                    }
                    return true;
                }
//                $this->logMessage('ERROR: there was an error saving in findFirstOrCreate', 3);
                throw $e;
            }
        }
    }

    public function createFindParams()
    {
        $hashFields = self::handleHashes();

        $data = $this->toArray();

        if (isset($this->_unique_constraint_fields) && count($this->_unique_constraint_fields)) {
            $data = array_intersect_key($data, array_flip($this->_unique_constraint_fields));
        } else if (isset(static::$_hashable_fields) && $hashFields) {
            // look up the record with the hashes
            $data = array_intersect_key($data, static::$_hashable_fields);
        }

        $propertyBindings = array();
        $keyValues = array();

        foreach ($data as $key => $value) {
            if (isset($value) && !is_object($value) && (strpos($key, '_') !== 0)) {
                array_push($propertyBindings, $key . ' = :' . $key . ':');
                $keyValues[$key] = $value;
            }
        }

        return array(
            implode(' AND ', $propertyBindings),
            'bind' => $keyValues
        );
    }

    public function mergeProperties($record)
    {
        foreach ($record as $key => $value) {
            if (isset($value) && strpos($key, '_') !== 0) {
                $this->$key = $value;
            }
        }
    }

    public function refreshCheck()
    {
        if ($this->_refreshDefaults) {
            foreach ($this->_defaulted_fields as $field) {
                if ($this->$field instanceof Phalcon\Db\RawValue) {
                    if ($this->_cacheKey) {
                        $modelsCache = \Phalcon\DI::getDefault()->get('modelsCache');
                        $modelsCache->delete($this->_cacheKey);
                    }

                    $this->refresh();
                    break;
                }
            }
        }
    }

    public function handleHashes()
    {
        $hashFields = array();

        try {
            if (isset(static::$_hashable_fields)) {
                foreach (static::$_hashable_fields as $hashField => $fieldToHash) {
                    if (!isset($this->$fieldToHash)) {
                        // check if the field is defaultable first
                        if (isset($this->_defaulted_fields)
                            && (in_array($fieldToHash, $this->_defaulted_fields)
                                && in_array($hashField, $this->_defaulted_fields))) {
                            continue;
                        }

                        throw new \Exception('Error in class ' . get_class($this) . ": Failed hashing $fieldToHash, value must be set.", 1);
                    }

                    // check to make sure the hash field isn't already set
                    if (!isset($this->$hashField)) {
                        $this->$hashField = self::hashValue($this->$fieldToHash);
                    }

                    $hashFields[$hashField] = $fieldToHash;
                }
            }
        } catch (Exception $e) {
//            $this->logMessage($e->getMessage(), 3);
            throw $e;
        }

        return $hashFields;
    }

    protected static function _createKey($params)
    {
        if (isset($params['hydration'])) {
            unset($params['hydration']);
        }
        if (isset($params['di'])) {
            unset($params['di']);
        }

        try{
            $key = hash('haval192,3', serialize($params));
            return $key;
        } catch(Exception $e) {
//            $this->logMessage("ERROR: " . var_export($params, 1), 3);
            throw $e;
        }
    }

    protected static function _addCacheParams($params)
    {
        // Convert the parameters to an array
        if (!is_array($params)) {
            $params = array($params);
        }

        // Check if a cache key wasn't passed
        // and create the cache parameters
        if (!isset($params['cache']) && \Phalcon\DI::getDefault()->has('modelsCache')) {
            $params['cache'] = array(
                'key'      => static::class . '_' . self::_createKey($params),
                // 'lifetime' => 1200 // 20 min
            );
        }

        return $params;
    }

    protected function checkProperties()
    {
        if (is_array($this->_defaulted_fields)) {
            foreach ($this as $property => $value) {
                if (strpos($property, '_') !== 0 && $property != 'id') {
                    if (!in_array($property, $this->_defaulted_fields) && !isset($value)) {
//                        $this->logMessage("Error: $property is not set!", 3);
                    }
                }
            }
        } else {
//            $this->logMessage(get_class($this) . ' is missing _defaulted_fields property', 3);
        }
    }

    public static function hashValue($value, $binary = true)
    {
        return hash(static::$_hash_type, $value, $binary);
    }

    public function __get($name)
    {
        $result = parent::__get($name);
        $lowerProperty = strtolower($name);

        if (gettype($result) == "object" && isset($this->_related[$lowerProperty])) {
            // remove this entry to prevent cascading updates
            unset($this->_related[$lowerProperty]);
        }

        return $result;
    }

    // Expose this method so it can be used outside the class.
    // NOTE: this is a GLOBAL property for all instances of the same model, even though
    // it is a called on a single instance.
    public function publicSkipAttributes($attrs)
    {
        return $this->skipAttributes($attrs);
    }

}
