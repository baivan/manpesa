<?php

class SalePromotion extends \Phalcon\Mvc\Model
{

    /**
     *
     * @var integer
     * @Primary
     * @Identity
     * @Column(type="integer", length=11, nullable=false)
     */
    public $salePromotionID;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=true)
     */
    public $isPendingSale;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=true)
     */
    public $isPartnerSale;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=true)
     */
    public $isAgentSale;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=true)
     */
    public $isGroup;

    /**
     *
     * @var string
     * @Column(type="string", nullable=false)
     */
    public $saleCreatedAt;

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
        return 'sale_promotion';
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return SalePromotion[]
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return SalePromotion
     */
    public static function findFirst($parameters = null)
    {
        return parent::findFirst($parameters);
    }

}
