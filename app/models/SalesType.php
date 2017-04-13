<?php

class SalesType extends \Phalcon\Mvc\Model
{

    /**
     *
     * @var integer
     * @Primary
     * @Identity
     * @Column(type="integer", length=11, nullable=false)
     */
    public $salesTypeID;

    /**
     *
     * @var string
     * @Column(type="string", length=100, nullable=false)
     */
    public $salesTypeName;

    /**
     *
     * @var string
     * @Column(type="string", length=50, nullable=false)
     */
    public $salesTypeDeposit;

    /**
     *
     * @var string
     * @Column(type="string", nullable=false)
     */
    public $createdAt;

    /**
     *
     * @var string
     * @Column(type="string", nullable=false)
     */
    public $updatedAt;

    /**
     * Returns table name mapped in the model.
     *
     * @return string
     */
    public function getSource()
    {
        return 'sales_type';
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return SalesType[]
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return SalesType
     */
    public static function findFirst($parameters = null)
    {
        return parent::findFirst($parameters);
    }

}
