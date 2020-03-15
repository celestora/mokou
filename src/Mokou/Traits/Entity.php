<?php declare(strict_types=1);
namespace Illuminate\Database\Mokou\Traits;
use Illuminate\Database\Mokou\QueryBuilder;
use Illuminate\Database\Mokou\Model;
use Nette\InvalidStateException as ISE;
use Nette\Database\Table\{ActiveRow, Selection};
use Moment\Moment;

/**
 * Entity trait. Represents the entity part of ActiveRecord model.s
 */
trait Entity {
    /**
     * Active database context. 
     * 
     * @var Nette\Database\Context
     */
    private $ctx;
    /**
     * Unpresisted changes to model.
     * 
     * @var array
     */
    private $changes = [];
    /**
     * Has model been deleted physically? 
     * 
     * @var bool
     */
    private $deleted = false;
    
    /**
     * Entity object or nothing.
     * 
     * @var ActiveRow|null
     */
    protected $row;
    /**
     * Table has columns for creation/update time?
     * 
     * @var bool
     */
    protected $timestamps = true;
    /**
     * Date format.
     * 
     * @var string
     */
    protected $dateFormat = "l, d-M-y H:i:s T";
    /**
     * Default attribute values.
     * 
     * @var array
     */
    protected $attributes = [];
    /**
     * Attributes, that should be mutated to dates.
     * 
     * @var array
     */
    protected $dates      = [];
    /**
     * Associative array of casts (attr. => type).
     * 
     * @var array
     */
    protected $casts      = [];
    /**
     * Attributes, that should be hidden in JSON/Array serialization.
     * 
     * @var array
     */
    protected $hidden     = [];
    
    /**
     * Column name for storing creation timestamp.
     * 
     * @var string
     */
    protected $created_at = "creation_date";
    /**
     * Column name for storing update timestamp.
     * 
     * @var string
     */
    protected $updated_at = "last_update";
    
    /**
     * @param ActiveRow|null $row Raw model record (if any).
     * @param ActiveRow|null $pivot Pivot model record (if any). 
     */
    function __construct(?ActiveRow $row = NULL, ?ActiveRow $pivot = NULL)
    {
		if(!is_null($row)) {
			if($row->getTable()->getName() !== $this->table) {
				$table = $row->getTable()->getName();
				throw new \LogicException(
					"Cannot implicitly cast data from table $table to $this->table's obj."
				);
			}
		}
        
        $this->row = $row;
        $this->ctx = _mokouGetDefaultConnection()->context;
    }
    
    /**
     * If there is relationship that can be matched to the given attribute, this
     * function will return either single model (has-one/belongs-to) or traversable
     * cursor to QueryBuilder (has-many/many-to-many).
     * 
     * If there is no matching relationship, $resolved will be set to false.
     * If there is, it will be returned and $resolved will be set to true.
     * 
     * @param string $attribute Attribute name.
     * @param bool $resolved Pointer to status variable.
     * @returns mixed Model or query builder.
     * @internal
     */
    private function tryResolveRelationship(string $attribute, &$resolved)
    {
        if(!is_callable([$this, $attribute]))
            return $resolved = false;
        
        $result = $this->{$attribute}();
        if($result instanceof Model) {
            $resolved = true;
            
            return $result;
        } else if($result instanceof QueryBuilder) {
            $resolved = true;
            
            return $result->cursor();
        }
        
        return $resolved = false;
    }
    
    /**
     * If mutator exists this function returns it name and null otherwise.
     * 
     * @param string $attribute Attribute name.
     * @param bool $accessor Get accesor instead of mutator?
     * @returns string Name of mutator/accessor.
     * @internal
     */
    private function getMutatorMethod(string $attribute, bool $accessor = false): ?string
    {
        $name  = ($accessor ? "get" : "set");
        $name .= str_replace('_', '', ucwords($attribute, '_'));
        $name .= "Attribute";
        
        return is_callable([$this, $name]) ? $name : NULL;
    }
    
    /**
     * Prepare changes that will be presisted upon save() call.
     * 
     * This method will bypass any mutators.
     * 
     * @param string $attribute Attribute name.
     * @param mixed $value Attribute value.
     * @returns void
     * @intetnal
     */
    private function stateChanges(string $attribute, $value): void
    {
		$this->changes[$attribute] = $value;
    }
    
    /**
     * @returns string Table name.
     */
    function getTableName(): string
    {
        return $this->table;
    }
    
    /**
     * @returns string Primary key name.
     */
    function getPrimaryKeyName(): string
    {
        return $this->primaryKey;
    }
    
    /**
     * Has the model/attribute been modified since it was (re-)loaded?
     * 
     * @param string|null $attribute Attribute to check. If not passed, all will be checked.
     * @returns bool Modified?
     */
    function isDirty(?string $attribute = NULL): bool
    {
        return is_null($attribute)
               ? sizeof($this->changes) > 0
               : in_array($attribute, $this->changes);
    }
    
    /**
     * Access attribute as dynamic property of model.
     * 
     * This method will call acessors, apply needed transformations and will try
     * to resolve relationships.
     */
    function __get(string $attribute)
    {
        if(is_null($this->row) && is_null($this->changes[$attribute]))
            throw new ISE("Can't get attribute of unpresisted model.");
        
		$relatedModel = $this->tryResolveRelationship($attribute, $resolved);
        if($resolved) {
            $data = $relatedModel;
		} else {
			$data = $this->changes[$attribute] ?? $this->row->{$attribute};
			$data = $data ?? ($this->attributes[$attribute] ?? NULL);
		}
        
        if(in_array($attribute, $this->dates))
            $data = (new Moment($data))->format($this->dateFormat);
        
        $accessor = $this->getMutatorMethod($attribute, true);
        if(!is_null($accessor))
            $data = $this->{$accessor}($data);
        
        settype($data, $this->casts[$attribute] ?? gettype($data));
        return $data;
    }
    
    /**
     * Write attribute as if it was a property of the model.
     *
     * This method will call mutators and apply needed transformations.
     * NOTICE: This method will not resolve relationships!
     */
    function __set(string $attribute, $newValue)
    {
        if($this->deleted)
            throw new ISE("Can't set attribute of deleted model.");
        
        settype($newValue, $this->casts[$attribute] ?? gettype($newValue));
        
        $mutator = $this->getMutatorMethod($attribute);
        if(!is_null($mutator))
            $newValue = $this->{$mutator}($newValue);
        
        $this->stateChanges($attribute, $newValue);
    }
    
    /**
     * Sets attribute to NULL if it was unset() externally.
     */
    function __unset(string $attribute)
    {
        $this->stateChanges($attribute, NULL);
    }
    
    /**
     * Convert this model to associative array.
     * 
     * This method will skip all hidden attributes.
     * 
     * @returns array Array.
     */
    function toArray(): array
    {
        $arr = [];
        foreach(array_diff($this->hidden, array_keys((array) $this->row)) as $attribute)
            $arr[$attribute] = $this->__get($attribute);
        
        return $arr;
    }
    
    /**
     * Serielize this model to JSON.
     *
     * Same as toArray() this method will skip all hidden attributes.
     *
     * @param int $options json_encode options.
     * @returns string JSON.
     */
    function toJSON(int $options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }
    
    /**
     * Presists model to DB.
     * 
     * This method will update model if it already exists.
     * 
     * @returns void
     */
    function save(): void
    {
		if(is_null($this->row)) {
            if($this->timestamps)
                $this->stateChanges($this->created_at, date("Y-m-d H:i:s"));
            
            $this->row     = $this->ctx->table($this->table)->insert($this->changes);
            $this->changes = [];
			
			if($this->row === 1)
				throw new \RuntimeException("Error updating model data: could not retrieve record. Have you set primary key correctly?");
        } else {
            if($this->timestamps)
                $this->stateChanges($this->updated_at, date("Y-m-d H:i:s"));
            
            $this->ctx->table($this->table)
                 ->where($this->primaryKey, $this->row->{$this->primaryKey})
                 ->update($this->changes);
            
            $this->changes = [];
            $this->row     = $this->ctx->table($this->table)
            ->where(
                $this->primaryKey,
                $this->row->{$this->primaryKey}
            )->fetch();
        }
    }
    
    /**
     * Physically delete model from DB.
     * 
     * @returns void
     */
    function delete(): void
    {
        if(is_null($this->row))
            throw new ISE("Nothing to delete: model unpresisted.");
        
        $this->ctx->table($this->table)
                  ->where($this->primaryKey, $this->row->{$this->primaryKey})
                  ->delete();
        
        $this->deleted = true;
    }
    
    use EntityRelations;
}