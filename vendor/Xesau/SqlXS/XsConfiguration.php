<?php

/**
 * SqlXS - A quick and easy solution for all your complex SQL queries
 *
 * @author Xesau <info@xesau.eu> https://xesau.eu/project/sqlxs
 * @version 1.0
 * @package SqlXS
 */

namespace Xesau\SqlXS;

use PDO;

/**
 * Class containing global configuration information for SqlXS
 */
class XsConfiguration
{

    const ERRMODE_EXCEPTION = 0;
    const ERRMODE_NULL = 1;

    /**
     * @var PDO $pdo The PDO used by SqlXS
     * @var int $erorrMode The error mode
     */
    private static $pdo;
    private static $errorMode;

    /**
     * Initiates SqlXS
     *
     * @param PDO $pdo The PDO to be used by the accessor
     * @param int $errorMode The error mode
     * @return void
     */
    public static function init(PDO $pdo, $errorMode = 0)
    {
        QueryBuilder::setPDO($pdo);
        self::$pdo = $pdo;
        self::$errorMode = ($errorMode >= 0 && $errorMode <= 1) ? $errorMode : 0;
    }

    /**
     * Gets the PDO used by SqlXS
     *
     * @return PDO The PDO
     */
    public static function getPDO()
    {
        return self::$pdo;
    }

    /**
     * Gets the error mode
     *
     * @return int The mode
     */
    public static function getErrorMode()
    {
        return self::$errorMode;
    }

}
