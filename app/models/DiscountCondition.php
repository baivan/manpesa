<?php

class DiscountCondition extends \Phalcon\Mvc\Model
{

    /**
     *
     * @var integer
     * @Primary
     * @Identity
     * @Column(type="integer", length=11, nullable=false)
     */
    public $discountConditionID;

    /**
     *
     * @var string
     * @Column(type="string", length=50, nullable=false)
     */
    public $conditionName;

    /**
     *
     * @var string
     * @Column(type="string", length=150, nullable=false)
     */
    public $conditionDescription;

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
        return 'discount_condition';
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return DiscountCondition[]
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return DiscountCondition
     */
    public static function findFirst($parameters = null)
    {
        return parent::findFirst($parameters);
    }

}
