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
use PDOException, DomainException, RangeException;

class XsQueryBuilder extends QueryBuilder
{
    /**
     * @var string $xsClass The name of the class that uses SqlXS
     */
    private $xsClass;

    /**
     * Initiates a new Object Query Builder
     *
     * @throws XsException When the given class doesnt use SQlXS
     * @param int $type The type of the query
     * @param string $xsClass The name of the class that uses SqlXS
     */
    public function __construct($type, $xsClass) {
        // Make sure the given class uses SqlXS
        if (!in_array(__NAMESPACE__ .'\\XS', class_uses($xsClass)))
            throw new XsException('The given $xsClass ('. strip_tags($xsClass) .') doesnt use SqlXS.');

        $this->xsClass = $xsClass;
        parent::__construct($type, $xsClass::sqlXS()->getTable(), array($xsClass::sqlXS()->getUniqueField()));
    }

    /**
     * Fetch the first n objects
     *
     * @param int $amount The amount of objects
     * @throws XsException When the query type is not SELECT
     * @return XS|XS[] The result object if the amount = 1, else an array of all the result objects
     */
    public function find($amount = 1) {
        if ($this->type != QueryBuilder::SELECT)
            throw new XsException('Only SELECT queries can use find().');

        // Sanitize the $amount parameter
        $amount = intval($amount);
        if ($amount < 1)
            throw new DomainException('You cannot request 0 or less objects.');


        $this->limit($amount);
        $xs = $this->xsClass;

        $result = $this->perform();
        if(self::$pdo->errorInfo()) {

        }
        // If there are less rows available than requested for, throw a RangeException
        if ($result->rowCount() < $amount) {
            throw new RangeException('Only '. $result->rowCount() .' rows were found, while '. $amount .' were requested');
        } else {
            // If only one was requested, return that object
            if ($amount == 1) {
                $entry = $result->fetch(PDO::FETCH_NUM);
                return $xs::byID($entry[0]);

            // If multiple were requested, return those
            } else {
                $out = array();
                foreach ($result->fetchAll(PDO::FETCH_NUM) as $entry)
                    $out [] = $xs::byID($entry[0]);

                return $out;
            }
        }
    }

    /**
     * Select all results from the query
     *
     * @throws XsException When the query type is not SELECT
     * @return \Generator<XS> The objects
     */
    public function all()
    {
        if ($this->type != QueryBuilder::SELECT)
            throw new XsException('Only SELECT queries can use all().');

        // Create temporary string variable containing the XS class name
        $xs = $this->xsClass;

        // Fetch all the results and yield them as SqlXS object instances
        $result = $this->perform();
        foreach ($result->fetchAll(PDO::FETCH_NUM) as $entry)
            yield $xs::byID($entry[0]);
    }

}
