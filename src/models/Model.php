<?php

namespace App\Models;

use PDO;
use PDOException;
use App\Core\Database;
use App\Core\Collection;

abstract class Model
{
    /**
     * The database connection instance.
     *
     * @var \PDO
     */
    protected $db;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table;

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = true;

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['created_at', 'updated_at'];

    /**
     * The attributes that should be appended.
     *
     * @var array
     */
    protected $appends = [];

    /**
     * The model's attributes.
     *
     * @var array
     */
    protected $attributes = [];

    /**
     * The model attribute's original state.
     *
     * @var array
     */
    protected $original = [];

    /**
     * Indicates if the model exists.
     *
     * @var bool
     */
    public $exists = false;

    /**
     * Create a new model instance.
     *
     * @param  array  $attributes
     * @return void
     */
    public function __construct(array $attributes = [])
    {
        $this->db = Database::getInstance()->getConnection();
        $this->fill($attributes);
    }

    /**
     * Fill the model with an array of attributes.
     *
     * @param  array  $attributes
     * @return $this
     */
    public function fill(array $attributes)
    {
        foreach ($attributes as $key => $value) {
            $this->setAttribute($key, $value);
        }

        return $this;
    }

    /**
     * Set a given attribute on the model.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return mixed
     */
    public function setAttribute($key, $value)
    {
        $this->attributes[$key] = $value;
        return $this;
    }

    /**
     * Get an attribute from the model.
     *
     * @param  string  $key
     * @return mixed
     */
    public function getAttribute($key)
    {
        if (array_key_exists($key, $this->attributes)) {
            return $this->attributes[$key];
        }

        return null;
    }

    /**
     * Get all of the models from the database.
     *
     * @param  array|string  $columns
     * @return \App\Core\Collection
     */
    public static function all($columns = ['*'])
    {
        $instance = new static;
        $columns = is_array($columns) ? $columns : func_get_args();
        
        $query = "SELECT " . implode(', ', $columns) . " FROM " . $instance->getTable();
        $stmt = $instance->db->query($query);
        
        return new Collection($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Find a model by its primary key.
     *
     * @param  mixed  $id
     * @param  array  $columns
     * @return \App\Models\Model|null
     */
    public static function find($id, $columns = ['*'])
    {
        $instance = new static;
        $columns = is_array($columns) ? $columns : func_get_args();
        
        $query = "SELECT " . implode(', ', $columns) . " FROM " . $instance->getTable() . 
                 " WHERE " . $instance->getKeyName() . " = ? LIMIT 1";
        
        $stmt = $instance->db->prepare($query);
        $stmt->execute([$id]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? $instance->newFromBuilder($result) : null;
    }

    /**
     * Find a model by its primary key or throw an exception.
     *
     * @param  mixed  $id
     * @param  array  $columns
     * @return \App\Models\Model
     *
     * @throws \Exception
     */
    public static function findOrFail($id, $columns = ['*'])
    {
        $model = static::find($id, $columns);
        
        if (is_null($model)) {
            throw new \Exception("No query results for model [" . static::class . "] " . $id);
        }
        
        return $model;
    }

    /**
     * Create a new model instance that is existing.
     *
     * @param  array  $attributes
     * @return static
     */
    public function newFromBuilder($attributes = [])
    {
        $model = $this->newInstance([], true);
        $model->setRawAttributes((array) $attributes, true);
        
        return $model;
    }

    /**
     * Create a new instance of the given model.
     *
     * @param  array  $attributes
     * @param  bool  $exists
     * @return static
     */
    public function newInstance($attributes = [], $exists = false)
    {
        $model = new static((array) $attributes);
        $model->exists = $exists;
        
        return $model;
    }

    /**
     * Set the array of model attributes.
     *
     * @param  array  $attributes
     * @param  bool  $sync
     * @return $this
     */
    public function setRawAttributes(array $attributes, $sync = false)
    {
        $this->attributes = $attributes;
        
        if ($sync) {
            $this->syncOriginal();
        }
        
        return $this;
    }

    /**
     * Sync the original attributes with the current.
     *
     * @return $this
     */
    public function syncOriginal()
    {
        $this->original = $this->attributes;
        return $this;
    }

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable()
    {
        return $this->table ?? str_replace(
            '\\', '', Str::snake(Str::plural(class_basename($this)))
        );
    }

    /**
     * Get the primary key for the model.
     *
     * @return string
     */
    public function getKeyName()
    {
        return $this->primaryKey;
    }

    /**
     * Save the model to the database.
     *
     * @param  array  $options
     * @return bool
     */
    public function save(array $options = [])
    {
        $query = $this->newQuery();
        
        if ($this->exists) {
            $saved = $this->performUpdate($query);
        } else {
            $saved = $this->performInsert($query);
        }
        
        return $saved;
    }

    /**
     * Perform a model update operation.
     *
     * @param  \App\Core\Database\Query\Builder  $query
     * @return bool
     */
    protected function performUpdate($query)
    {
        $dirty = $this->getDirty();
        
        if (count($dirty) > 0) {
            $this->setKeysForSaveQuery($query)->update($dirty);
            
            $this->syncOriginal();
        }
        
        return true;
    }

    /**
     * Perform a model insert operation.
     *
     * @param  \App\Core\Database\Query\Builder  $query
     * @return bool
     */
    protected function performInsert($query)
    {
        $attributes = $this->getAttributes();
        
        if (empty($attributes)) {
            return true;
        }
        
        $this->setKeysForSaveQuery($query)->insert($attributes);
        
        $this->exists = true;
        $this->wasRecentlyCreated = true;
        
        $this->syncOriginal();
        
        return true;
    }

    /**
     * Get the attributes that have been changed since last sync.
     *
     * @return array
     */
    public function getDirty()
    {
        $dirty = [];
        
        foreach ($this->attributes as $key => $value) {
            if (! array_key_exists($key, $this->original) || 
                $value !== $this->original[$key]) {
                $dirty[$key] = $value;
            }
        }
        
        return $dirty;
    }

    /**
     * Get all of the current attributes on the model.
     *
     * @return array
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * Delete the model from the database.
     *
     * @return bool|null
     *
     * @throws \Exception
     */
    public function delete()
    {
        if (is_null($this->getKeyName())) {
            throw new \Exception('No primary key defined on model.');
        }
        
        if ($this->exists) {
            if ($this->fireModelEvent('deleting') === false) {
                return false;
            }
            
            $this->newQuery()->where($this->getKeyName(), $this->getKey())->delete();
            
            $this->exists = false;
            
            $this->fireModelEvent('deleted', false);
            
            return true;
        }
    }

    /**
     * Get a new query builder for the model's table.
     *
     * @return \App\Core\Database\Query\Builder
     */
    public function newQuery()
    {
        return new QueryBuilder(
            $this->db, $this->getTable()
        );
    }

    /**
     * Handle dynamic method calls into the model.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return $this->newQuery()->$method(...$parameters);
    }

    /**
     * Handle dynamic static method calls into the model.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public static function __callStatic($method, $parameters)
    {
        return (new static)->$method(...$parameters);
    }

    /**
     * Convert the model to its string representation.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->toJson();
    }

    /**
     * Convert the model instance to JSON.
     *
     * @param  int  $options
     * @return string
     */
    public function toJson($options = 0)
    {
        return json_encode($this->toArray(), $options);
    }

    /**
     * Convert the model instance to an array.
     *
     * @return array
     */
    public function toArray()
    {
        $attributes = $this->attributes;
        
        // Hide hidden attributes
        foreach ($this->hidden as $hidden) {
            unset($attributes[$hidden]);
        }
        
        // Add appends
        foreach ($this->appends as $append) {
            $attributes[$append] = $this->$append;
        }
        
        // Cast attributes
        foreach ($this->casts as $key => $type) {
            if (array_key_exists($key, $attributes)) {
                $attributes[$key] = $this->castAttribute($key, $attributes[$key]);
            }
        }
        
        return $attributes;
    }

    /**
     * Cast an attribute to a native PHP type.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return mixed
     */
    protected function castAttribute($key, $value)
    {
        if (is_null($value)) {
            return $value;
        }
        
        $type = $this->getCastType($key);
        
        switch ($type) {
            case 'int':
            case 'integer':
                return (int) $value;
            case 'real':
            case 'float':
            case 'double':
                return (float) $value;
            case 'string':
                return (string) $value;
            case 'bool':
            case 'boolean':
                return (bool) $value;
            case 'object':
                return $this->fromJson($value, true);
            case 'array':
            case 'json':
                return $this->fromJson($value);
            case 'date':
                return $this->asDate($value);
            case 'datetime':
                return $this->asDateTime($value);
            case 'timestamp':
                return $this->asTimestamp($value);
            default:
                return $value;
        }
    }
}
