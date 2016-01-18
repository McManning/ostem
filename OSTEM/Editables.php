<?php

namespace OSTEM;

/**
 * Tracker for editable page content
 */
class Editables
{
    private $fields;

    function __construct()
    {
        $this->fields = json_decode(
            file_get_contents(DATA_DIR . 'landing.json', 'r')
        );
    }

    public function save()
    {
        // Persist the updates
        file_put_contents(
            DATA_DIR . 'landing.json', 
            json_encode($this->fields)
        );
    }

    public function __get($key)
    {
        return $this->fields->{$key};
    }

    public function __set($key, $value)
    {
        $this->fields->{$key} = $value;
    }

    public function __isset($key) 
    {
        return property_exists($this->fields, $key);
    }

    public function keys()
    {
        return array_keys((array)$this->fields);
    }
}
