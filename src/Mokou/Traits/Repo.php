<?php declare(strict_types=1);
namespace Illuminate\Database\Mokou\Traits;
use Illuminate\Database\Mokou\QueryBuilder;

/**
 * @method select
 * @method where
 * @method joinWhere
 * @method whereOr
 * @method group
 * @method having
 * @method order
 * @method limit
 * @method page
 */
trait Repo {
    /**
     * Table name.
     * 
     * @var string 
     */
    protected $table;
    /**
     * Primary key name.
     * 
     * @var string
     */
    protected $primaryKey = "id";
    /**
     * Has model got a primary key that autoincrements?
     * 
     * @var string
     */
    protected $incrementing = true;
    /**
     * PHP datatype counterpart of primary key type in DB.
     * 
     * @var string
     */
    protected $keyType      = "int";
    
    /**
     * Get model by id.
     * 
     * @param mixed $id ID.
     * @return \Illuminate\Database\Mokou\Model Model.
     */
    static function find($id)
    {
        return (new QueryBuilder(get_called_class()))
               ->where((new static)->getPrimaryKeyName(), $id)
               ->_fetch();
    }
    
    /**
     * Forward QueryBuilder's methods to it. 
     */
    static function __callStatic(string $method, $params)
    {
        if(in_array($method, [
            "select",
            "where",
            "joinWhere",
            "whereOr",
            "group",
            "having",
            "order",
            "limit",
            "page"
        ])) {
            return (new QueryBuilder(get_called_class()))->{$method}(...$params);
        }
        
        throw new \LogicException("Call to undefined method $method.");
    }
}

