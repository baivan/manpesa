<?php

class SalesReward extends \Phalcon\Mvc\Model
{

    /**
     *
     * @var integer
     * @Primary
     * @Identity
     * @Column(type="integer", length=11, nullable=false)
     */
    public $saleRewardID;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    public $promotionID;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    public $salesID;

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
    public $expiredAt;

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
        return 'sales_reward';
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return SalesReward[]
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return SalesReward
     */
    public static function findFirst($parameters = null)
    {
        return parent::findFirst($parameters);
    }

}
