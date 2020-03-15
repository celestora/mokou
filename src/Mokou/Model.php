<?php declare(strict_types=1);
namespace Illuminate\Database\Mokou;

/**
 * Mokou base Model.
 * 
 * Abstract class, that uses both traits Entity and Repo
 * to become an implementation of ActiveRecord.
 * 
 * @author fkwa
 */
abstract class Model
{
    use Traits\Entity;
    use Traits\Repo;
}