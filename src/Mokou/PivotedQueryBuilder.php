<?php declare(strict_types=1);
namespace Illuminate\Database\Mokou;

/**
 * QueryBuilder, but suitable for Many-to-Many relationships.
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
class PivotedQueryBuilder extends QueryBuilder
{
    /**
     * Active database context.
     *
     * @var Nette\Database\Context
     */
    private $ctx;
    
    /**
     * Class names of models.
     * 
     * This array has following structure:
     *  origin: origin model's class name.
     *  related: related model's class name.
     * 
     * @var array
     */
    protected $modelClassNames;
    /**
     * Key names.
     * 
     * This array has following structure:
     *  foreign: Name of key, that represents origin model's id in i. table.
     *  related: Name of key, that represents related model's id in i. table.
     * @var unknown
     */
    protected $keyNames;
    /**
     * Id of origin model.
     * 
     * @var mixed
     */
    protected $localVal;
    /**
     * Nette Database Explorer.
     * 
     * @var \Nette\Database\Table\Selection Nette Database Explorer.
     */
    protected $builder;
    /**
     * Array of table names.
     * 
     * This array has the following structure:
     *  intermideate: Name of the intermideate Many-to-Many table.
     *  origin: Name of origin model's table.
     *  related: Name of related model's table.
     * 
     * @var array
     */
    protected $tables;
    
    /**
     * Create Pivoted Many-to-Many Query Builder.
     * 
     * @param string $originModelName Origin model's class name.
     * @param string $relatedModelName Related model's class name.
     * @param string $table Name of intermideate table.
     * @param string $foreignPivotKey Name of key, that represents origin model's id in i. table.
     * @param string $relatedPivotKey Name of key, that represents related model's id in i. table.
     * @param mixed $localVal Id of origin model.
     */
    function __construct(string $originModelName, string $relatedModelName, string $table, string $foreignPivotKey, string $relatedPivotKey, $localVal)
    {
        $this->modelClassNames = (object) [
            "origin"  => $originModelName,
            "related" => $relatedModelName,
        ];
        $this->keyNames = (object) [
            "foreign" => $foreignPivotKey,
            "related" => $relatedPivotKey,
        ];
        $this->tables = (object) [
            "intermideate" => $table,
            "origin"       => strtolower(substr(strrchr($originModelName, "\\"), 1)) . "s",
            "related"      => strtolower(substr(strrchr($relatedModelName, "\\"), 1)) . "s",
        ];
        $this->localVal = $localVal;
        
        $this->ctx = _mokouGetDefaultConnection()->context;
        $this->resetBuilder();
    }
    
    /**
     * Reset internal query builder.
     * 
     * @returns void
     * @internal
     */
	private function resetBuilder(): void
	{
		$this->builder = $this->ctx->table($this->tables->intermideate);
        $this->builder = $this->builder->where($this->keyNames->foreign, $this->localVal);
	}
	
	/**
	 * {@inheritDoc}
	 * @see \Illuminate\Database\Mokou\QueryBuilder::cursor()
	 */
    function cursor(): iterable
    {
        foreach($this->builder as $pivot) {
            $class  = $this->modelClassNames->origin;
            $entity = $pivot->ref($this->tables->related, $this->keyNames->related);
            
            yield new $class($entity, $pivot);
        }
        
        $this->resetBuilder();
    }
	
    /**
     * Attach related model to origin model.
     * 
     * @param mixed $related Primary key of related model.
     * @param array $additional Additional parameters to add to pivot table.
     */
	function attach($related, array $additional): void
	{
		$data = [];
		$data[$this->keyNames->related] = $related;
		$data = array_merge_recursive($additional, $data);
		
		$this->ctx->table($this->tables->intermideate)->insert($data);
	}
	
	/**
	 * Detach related model from origin model.
	 *
	 * @param mixed $related Primary key of related model.
	 */
	function detach($related): void
	{
		$this->ctx->table($this->tables->intermideate)
		          ->where($this->keyNames->related, $related)
				  ->delete();
	}
}