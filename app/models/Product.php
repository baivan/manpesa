<?php

class Product extends \Phalcon\Mvc\Model
{

    /**
     *
     * @var integer
     * @Primary
     * @Identity
     * @Column(type="integer", length=11, nullable=false)
     */
    public $productID;

    /**
     *
     * @var string
     * @Column(type="string", length=100, nullable=false)
     */
    public $productName;

    /**
     *
     * @var string
     * @Column(type="string", length=100, nullable=true)
     */
    public $productImage;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    public $categoryID;

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
        return 'product';
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return Product[]
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return Product
     */
    public static function findFirst($parameters = null)
    {
        return parent::findFirst($parameters);
    }

}
