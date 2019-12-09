<?php

namespace Merkeleon\Log\Model;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Merkeleon\Log\Exceptions\LogException;
use Carbon\Carbon;
use Ramsey\Uuid\Uuid;

abstract class Log implements \ArrayAccess
{
    protected static $attributes = [
        'uuid'       => 'uuid',
        'created_at' => 'datetime',
    ];

    protected static $customAttributes = [];

    protected static $rules = [];

    public static $dateTimeFormat = 'Y-m-d H:i:s';

    protected static $relations = [];

    protected $values;

    protected static $table;

    public function __construct($row)
    {
        $this->validate($row);

        $this->values = array_intersect_key($row, array_flip(static::getAttributes()));
    }

    public static function getDefaultValues()
    {
        $attributes = static::getAttributes();

        $values = [];

        foreach ($attributes as $attribute)
        {
            $defaultValue = static::getDefaultValue($attribute);

            if ($defaultValue !== null)
            {
                $values[$attribute] = $defaultValue;
            }
        }

        return $values;

    }

    public static function getTableName()
    {
        return static::$table;
    }

    public static function getAttributes()
    {
        return array_merge(
            array_keys(static::getAttributesWithCasts()),
            array_keys(static::$relations)
        );
    }

    public static function getAttributesWithCasts()
    {
        return array_merge(static::$attributes, static::$customAttributes);
    }

    public function __get($name)
    {
        return $this->getAttribute($name);
    }

    /**
     * @param $name
     * @return mixed
     * @throws LogException
     */
    protected function getAttribute($name)
    {
        if (!in_array($name, static::getAttributes()))
        {
            throw new LogException('Attribute ' . $name . ' not exists');
        }

        if (array_get($this->values, $name) === null
            && array_key_exists($name, static::$relations)
        )
        {
            $relation = static::$relations[$name];

            $relationId = array_get($this->values, $relation['foreign_id']);
            if ($relationId)
            {
                $this->values[$name] = $relation['class']::where($relation['local_id'], $relationId)
                                                         ->first();
            }
        }

        return array_get($this->values, $name);
    }

    public function getValues()
    {
        return $this->values;
    }

    /**
     * @param $key
     * @param $value
     * @return $this
     * @throws LogException
     */
    public function setAttribute($key, $value)
    {
        if (!in_array($key, static::getAttributes()))
        {
            throw new LogException('Attribute ' . $key . ' not exists');
        }

        $this->values[$key] = $value;

        return $this;
    }

    public static function getRules()
    {
        return static::$rules;
    }

    protected function validate(array $data)
    {
        $validator = Validator::make($data, static::$rules);

        if ($validator->fails())
        {
            throw new LogException('Log is not valid' . $validator->getMessageBag());
        }
    }

    public function toLogFileArray()
    {
        return [];
    }

    protected static function getDefaultValue($attribute)
    {
        $defaultValueMethodName = studly_method_name('get_' . $attribute . '_default_value');
        if (!method_exists(static::class, $defaultValueMethodName))
        {
            return null;
        }

        return static::$defaultValueMethodName();
    }

    protected static function getCreatedAtDefaultValue()
    {
        return new Carbon();
    }

    protected static function getUuidDefaultValue()
    {
        return (string)Uuid::uuid4();
    }

    public static function getRelations()
    {
        return static::$relations;
    }

    /**
     * Determine if the given attribute exists.
     *
     * @param  mixed  $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return ! is_null($this->getAttribute($offset));
    }

    /**
     * Get the value for a given offset.
     *
     * @param  mixed  $offset
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return $this->getAttribute($offset);
    }

    /**
     * Set the value for a given offset.
     *
     * @param  mixed  $offset
     * @param  mixed  $value
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        $this->setAttribute($offset, $value);
    }

    /**
     * Unset the value for a given offset.
     *
     * @param  mixed  $offset
     * @return void
     */
    public function offsetUnset($offset)
    {
        unset($this->values[$offset], static::$relations[$offset]);
    }
}
