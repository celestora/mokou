<?php declare(strict_types=1);
namespace Illuminate\Database\Mokou;
use Nette\Database\Table\Selection;
use Illuminate\Support\Collection;

/**
 * QueryBuilder class.
 * 
 * This class is responsible for forwarding method calls to Nette Database Explorer
 * and generating Laravel collections from Models.
 * QueryBuilder can make only models of one type.
 * 
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
class QueryBuilder {
    /**
     * Active database context.
     *
     * @var Nette\Database\Context
     */
    private $ctx;
    
    /**
     * Model's class name.
     * 
     * @var string
     */
    protected $modelClassName;
    /**
     * Nette Database Explorer.
     * 
     * @var Selection
     */
    protected $builder;
    /**
     * Table name.
     * 
     * @var string
     */
    protected $table;
    
    /**
     * @param string $modelClassName Model's class name. 
     */
    function __construct(string $modelClassName)
    {
        $this->modelClassName = $modelClassName;
        $this->table          = (new $modelClassName)->getTableName();
        
        $this->ctx     = _mokouGetDefaultConnection()->context;
        $this->builder = $this->ctx->table($this->table); 
    }
    
    /**
     * Forwards method to Nette DBE. 
     */
    function __call(string $method, $params)
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
            $this->builder = $this->builder->{$method}(...$params);
            
            return $this;
        }
        
        throw new \LogicException("Call to undefined method $method.");
    }
    
    /**
     * Get Nette DBE.
     * 
     * @return Selection Nette DBE Selection.
     */
    function getBuilder(): Selection
    {
        return $this->builder;
    }
    
    /**
     * Access records as iterator using lazy-loading.
     * 
     * @returns iterable Iterator of records.
     */
    function cursor(): iterable
    {
        foreach($this->builder as $entity) {
            $class = $this->modelClassName;
            yield new $class($entity);
        }
        
        $this->builder = $this->ctx->table($this->table);
    }
    
    /**
     * Collects all records, that match current selection and returns
     * them as Laravel collection.
     * 
     * @returns Collection Collection.
     */
    function get(): Collection
    {
        return new Collection(iterator_to_array($this->cursor()));
    }
    
    /**
     * Get first matching model from matching records.
     * 
     * @return NULL|\Illuminate\Database\Mokou\Model Model.
     */
    function _fetch()
    {
        $row = $this->builder->fetch();
        if(is_null($row))
            return NULL;
        
        $class = $this->modelClassName;
        return new $class($row);
    }
}