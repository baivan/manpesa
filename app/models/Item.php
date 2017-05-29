<?php

class Item extends \Phalcon\Mvc\Model
{

    /**
     *
     * @var integer
     * @Primary
     * @Identity
     * @Column(type="integer", length=11, nullable=false)
     */
    public $itemID;

    /**
     *
     * @var string
     * @Column(type="string", length=100, nullable=false)
     */
    public $serialNumber;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    public $status;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=true)
     */
    public $productID;

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
    public $warrantedAt;

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
        return 'item';
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return Item[]
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return Item
     */
    public static function findFirst($parameters = null)
    {
        return parent::findFirst($parameters);
    }

}
