<?php

class RewardType extends \Phalcon\Mvc\Model
{

    /**
     *
     * @var integer
     * @Primary
     * @Identity
     * @Column(type="integer", length=11, nullable=false)
     */
    public $rewardTypeID;

    /**
     *
     * @var string
     * @Column(type="string", length=50, nullable=false)
     */
    public $rewardName;

    /**
     *
     * @var string
     * @Column(type="string", length=150, nullable=true)
     */
    public $description;

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
        return 'reward_type';
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return RewardType[]
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return RewardType
     */
    public static function findFirst($parameters = null)
    {
        return parent::findFirst($parameters);
    }

}
