<?php

class ProductPromotion extends \Phalcon\Mvc\Model
{

    /**
     *
     * @var integer
     * @Primary
     * @Identity
     * @Column(type="integer", length=11, nullable=false)
     */
    public $productPromotionID;

    /**
     *
     * @var string
     * @Column(type="string", length=50, nullable=false)
     */
    public $productID;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=true)
     */
    public $isWarranted;

    /**
     *
     * @var integer
     * @Column(type="integer", length=6, nullable=true)
     */
    public $sale_item_status;

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
        return 'product_promotion';
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return ProductPromotion[]
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return ProductPromotion
     */
    public static function findFirst($parameters = null)
    {
        return parent::findFirst($parameters);
    }

}
