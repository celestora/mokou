<?php declare(strict_types=1);
use Nette\Database\Conventions\DiscoveredConventions;
use Nette\Database\{Structure, Connection, Context};
use Nette\Caching\Storages\FileStorage;
use Nette\InvalidStateException as ISE;

/**
 * Active DB Connection.
 * 
 * @var Connection $connection
 */
$connection = NULL;
/**
 * Active DB Context.
 *
 * @var Context $context
 */
$context    = NULL;

/**
 * Set default connection for Mokou models.
 * 
 * @param array $options = [
 *     "db" => [
 *         "driver" => "pgsql",
 *         "host"   => "127.0.0.1",
 *         "name"   => "defaultdb",
 *         "user"   => "postgres",
 *         "pass"   => "postgres",
 *     ],
 *     "cache" => [
 *         "tmpFolder" => "/tmp",
 *     ],
 * ]
 */
function mokouSetDefaultConnection(array $options): void
{
    global $connection, $context;
    
    $dsn  = $options["db"]["driver"] . ":host=" . $options["db"]["host"];
    $dsn .= ";dbname=" . $options["db"]["name"] . ";";
    $connection  = new Connection($dsn, $options["db"]["user"], $options["db"]["pass"]);
    $storage     = new FileStorage($options["cache"]["tmpFolder"]);
    $structure   = new Structure($connection, $storage);
    $conventions = new DiscoveredConventions($structure);
    $context     = new Context($connection, $structure, $conventions, $storage);
}

/**
 * Get default connection.
 * 
 * @param bool $throwOnUninitialized Throw if no connection is set.
 * @throws ISE
 * @returns Object with properties connection and context.
 */
function _mokouGetDefaultConnection(bool $throwOnUninitialized = true): object
{
    global $connection, $context;
    
    if($throwOnUninitialized && (is_null($connection) || is_null($context)))
        throw new ISE("DB Connection is uninitialized.");
    
    return (object) [
        "connection" => $connection,
        "context"    => $context,
    ];
}