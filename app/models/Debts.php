<?php

class Debts extends \Phalcon\Mvc\Model
{

    /**
     *
     * @var integer
     * @Primary
     * @Identity
     * @Column(type="integer", length=11, nullable=false)
     */
    public $debtId;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    public $userId;

    /**
     *
     * @var string
     * @Column(type="string", length=50, nullable=false)
     */
    public $debtorName;

    /**
     *
     * @var string
     * @Column(type="string", length=20, nullable=false)
     */
    public $amount;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    public $paid;

    /**
     *
     * @var string
     * @Column(type="string", nullable=false)
     */
    public $dueDate;

    /**
     *
     * @var string
     * @Column(type="string", nullable=false)
     */
    public $lendDate;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    public $debtTypeId;

    /**
     *
     * @var string
     * @Column(type="string", nullable=false)
     */
    public $settleDate;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    public $status;

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
        return 'debts';
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return Debts[]
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return Debts
     */
    public static function findFirst($parameters = null)
    {
        return parent::findFirst($parameters);
    }

}
