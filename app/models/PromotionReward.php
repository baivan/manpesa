<?php

class PromotionReward extends \Phalcon\Mvc\Model
{

    /**
     *
     * @var integer
     * @Primary
     * @Identity
     * @Column(type="integer", length=11, nullable=false)
     */
    public $promotionRewardID;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    public $rewardTypeID;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    public $value;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    public $hoursToExpire;

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
    public $updatedAT;

    /**
     * Returns table name mapped in the model.
     *
     * @return string
     */
    public function getSource()
    {
        return 'promotion_reward';
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return PromotionReward[]
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return PromotionReward
     */
    public static function findFirst($parameters = null)
    {
        return parent::findFirst($parameters);
    }

}
