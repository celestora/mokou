Mokou
===============

Implementation of ActiveRecord pattern in PHP. This library aims to provide API **similiar** to Laravel's Eloquent, but be faster. Mokou is not a drop-in replacement for Eloquent.

Mokou relies on Nette Database Explorer, whille Eloquent depends on Doctrine. That's why Mokou is a bit faster.

Example usage
------------------
.. code-block:: php

    <?php declare(strict_types=1);
    use Illuminate\Database\Mokou\Model;
    
    class Person extends Model
    {
        protected $table = "people";
        
        function getFirstNameAttribute(): string
        {
            return ucfirst($this->attributes["first_name"]);
        }
    }
    
    $person = new Person;
    $person->first_name = "mars";
    $person->last_name  = "Argo";
    $person->age        = random_int(20, 28);
    $person->save(); // Flush to DB
    
    var_export($person->first_name); // "Mars"