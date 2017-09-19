<?php

class Promotion extends \Phalcon\Mvc\Model
{

    /**
     *
     * @var integer
     * @Primary
     * @Identity
     * @Column(type="integer", length=11, nullable=false)
     */
    public $promotionID;

    /**
     *
     * @var string
     * @Column(type="string", length=150, nullable=false)
     */
    public $promotionName;

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
     * @Column(type="integer", length=6, nullable=false)
     */
    public $status;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=true)
     */
    public $salePromotionID;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=true)
     */
    public $productPromotionID;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=true)
     */
    public $promotionRewardID;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=true)
     */
    public $smsTemplateID;

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
        return 'promotion';
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return Promotion[]
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return Promotion
     */
    public static function findFirst($parameters = null)
    {
        return parent::findFirst($parameters);
    }

}
