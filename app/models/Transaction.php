<?php

class Transaction extends \Phalcon\Mvc\Model
{

    /**
     *
     * @var integer
     * @Primary
     * @Identity
     * @Column(type="integer", length=11, nullable=false)
     */
    public $transactionID;

    /**
     *
     * @var string
     * @Column(type="string", length=50, nullable=true)
     */
    public $nationalIDNumber;

    /**
     *
     * @var string
     * @Column(type="string", length=50, nullable=true)
     */
    public $fullName;

    /**
     *
     * @var string
     * @Column(type="string", length=50, nullable=true)
     */
    public $referenceNumber;

    /**
     *
     * @var string
     * @Column(type="string", length=50, nullable=true)
     */
    public $mobile;

    /**
     *
     * @var string
     * @Column(type="string", length=10, nullable=false)
     */
    public $depositAmount;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    public $salesID;

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
        return 'transaction';
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return Transaction[]
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return Transaction
     */
    public static function findFirst($parameters = null)
    {
        return parent::findFirst($parameters);
    }

}
