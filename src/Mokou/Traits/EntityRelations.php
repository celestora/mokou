<?php declare(strict_types=1);
namespace Illuminate\Database\Mokou\Traits;
use Illuminate\Database\Mokou\PivotedQueryBuilder;
use Illuminate\Database\Mokou\QueryBuilder;
use Nette\InvalidStateException;

/**
 * Essential part of entity. Contains relationship methods. 
 */
trait EntityRelations {
    /**
     * Throw exception if model is not presisted, but someone still tries to
     * work with its relationships.
     * 
     * @throws InvalidStateException if model is unpresisted.
     * @returns void
     * @internal
     */
	private function throwOnUnpresisted(): void
	{
		if(is_null($this->row))
		    throw new InvalidStateException("Can't work with relations of unpresisted model.");
	}
	
	/**
	 * Has-Many relationship method.
	 * 
	 * @param string $model Related model class name.
	 * @param string $foreign_key Foreign key name.
	 * @param string $local_key Local key name.
	 * @returns QueryBuilder
	 */
    protected function hasMany(string $model, ?string $foreign_key = NULL, ?string $local_key = NULL): QueryBuilder
    {
		$this->throwOnUnpresisted();
		
        $foreign_key = $foreign_key
                       ?? strtolower((new \ReflectionClass($this))->getShortName()) . "_id";
        $local_key   = $local_key
                       ?? $this->primaryKey;
        $local_value = $this->row->{$local_key};
        
        return call_user_func("$model::where", $foreign_key, $local_value);
    }
    
    /**
     * Has-One relationship method.
     * 
     * @param string $model Related model class name.
	 * @param string $foreign_key Foreign key name.
	 * @param string $local_key Local key name.
	 * @returns mixed Relared model.
     */
    protected function hasOne(string $model, ?string $foreign_key = NULL, ?string $local_key = NULL)
    {
        return $this->hasMany($model, $foreign_key, $local_key)->_fetch();
    }
    
    /**
     * Many-To-Many relationship method.
     * 
     * @param string $model Related model class name.
     * @param string $intermideate Intermideate table name.
     * @param string $foreignPivotKey Name of key, which identifes this model in intermediate table.
     * @param string $relatedPivotKey Name of key, which identifes related model in intermediate table.
     * @returns \Illuminate\Database\Mokou\PivotedQueryBuilder
     */
    protected function belongsToMany(string $model, ?string $intermideate = NULL, ?string $foreignPivotKey = NULL, ?string $relatedPivotKey = NULL)
    {
		$this->throwOnUnpresisted();
		
        if(is_null($intermideate) || is_null($foreignPivotKey) || is_null($relatedPivotKey)) {
            $thisModelName = strtolower((new \ReflectionClass($this))->getShortName());
            $relModelName  = strtolower(substr(strrchr($model, "\\"), 1));
            
            $intermideate    = $intermideate ?? $relModelName . "_" . $thisModelName;
            $foreignPivotKey = $foreignPivotKey ?? $thisModelName . "_id";
            $relatedPivotKey = $relatedPivotKey ?? $relModelName . "_id"; 
        }
        
        return new PivotedQueryBuilder(get_class($this), $model, $intermideate, $foreignPivotKey, $relatedPivotKey, $this->row->{$this->primaryKey});
    }
    
    /**
     * Reverse of Has-One/Has-Many. Gets parent model.
     * 
     * @param string $model Parent model class name.
     * @param string $foreign_key Foreign key name.
     * @param string $local_key Local key name.
     * @returns mixed Parent model.
     */
    protected function belongsTo(string $model, ?string $foreign_key = NULL, ?string $local_key = NULL)
    {
		$this->throwOnUnpresisted();
		
        $foreign_key = $foreign_key
                       ?? (new $model)->getPrimaryKeyName();
        $local_key   = $local_key
                       ?? strtolower(substr(strrchr($model, "\\"), 1)) . "_id";
        $local_value = $this->row->{$local_key};
        
        $builder = call_user_func("$model::where", $foreign_key, $local_value);
        return $builder->_fetch();
    }
}