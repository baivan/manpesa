<?php

class ProductSaleTypePrice extends \Phalcon\Mvc\Model
{

    /**
     *
     * @var integer
     * @Primary
     * @Identity
     * @Column(type="integer", length=11, nullable=false)
     */
    public $productSaleTypePriceID;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    public $productID;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    public $salesTypeID;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    public $categoryID;

    /**
     *
     * @var string
     * @Column(type="string", length=20, nullable=false)
     */
    public $price;

    /**
     *
     * @var string
     * @Column(type="string", length=10, nullable=false)
     */
    public $deposit;

    /**
     *
     * @var string
     * @Column(type="string", length=10, nullable=false)
     */
    public $discount;

    /**
     *
     * @var string
     * @Column(type="string", nullable=true)
     */
    public $startDate;

    /**
     *
     * @var string
     * @Column(type="string", nullable=true)
     */
    public $endDate;

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
     *
     * @var integer
     * @Column(type="integer", length=3, nullable=false)
     */
    public $status;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=true)
     */
    public $userID;

    /**
     * Returns table name mapped in the model.
     *
     * @return string
     */
    public function getSource()
    {
        return 'product_sale_type_price';
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return ProductSaleTypePrice[]
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return ProductSaleTypePrice
     */
    public static function findFirst($parameters = null)
    {
        return parent::findFirst($parameters);
    }

}
