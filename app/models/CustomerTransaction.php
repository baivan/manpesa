<?php

class CustomerTransaction extends \Phalcon\Mvc\Model
{

    /**
     *
     * @var integer
     * @Primary
     * @Identity
     * @Column(type="integer", length=11, nullable=false)
     */
    public $customerTransactionID;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    public $transactionID;

     /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    public $contactsID;

     /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=true)
     */
    public $customerID;
    
         /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=true)
     */
    public $prospectsID;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=true)
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
        return 'customer_transaction';
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
