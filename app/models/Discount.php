<?php

class Discount extends \Phalcon\Mvc\Model
{

    /**
     *
     * @var integer
     * @Primary
     * @Identity
     * @Column(type="integer", length=11, nullable=false)
     */
    public $discountID;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    public $saleTypeID;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    public $productID;

    /**
     *
     * @var string
     * @Column(type="string", length=100, nullable=false)
     */
    public $agents;

    /**
     *
     * @var string
     * @Column(type="string", length=10, nullable=false)
     */
    public $rightHandOperand;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    public $discountConditionID;

    /**
     *
     * @var string
     * @Column(type="string", length=10, nullable=false)
     */
    public $leftHandOperand;

    /**
     *
     * @var string
     * @Column(type="string", length=10, nullable=false)
     */
    public $discountAmount;

    /**
     *
     * @var string
     * @Column(type="string", nullable=false)
     */
    public $startDate;

    /**
     *
     * @var string
     * @Column(type="string", nullable=false)
     */
    public $endDate;

    /**
     *
     * @var integer
     * @Column(type="integer", length=3, nullable=false)
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
        return 'discount';
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return Discount[]
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return Discount
     */
    public static function findFirst($parameters = null)
    {
        return parent::findFirst($parameters);
    }

}
