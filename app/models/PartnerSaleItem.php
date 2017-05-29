<?php

class PartnerSaleItem extends \Phalcon\Mvc\Model {

    /**
     *
     * @var integer
     * @Primary
     * @Identity
     * @Column(type="integer", length=11, nullable=false)
     */
    public $partnerSaleItemID;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    public $itemID;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    public $customerID;

    /**
     *
     * @var integer
     * @Column(type="string", length=200, nullable=false)
     */
    public $salesPartner;
    
    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=true)
     */
    public $productID;
    
    /**
     *
     * @var string
     * @Column(type="string", length=150, nullable=true)
     */
    public $serialNumber;
    
    /**
     *
     * @var integer
     * @Column(type="integer", length=3, nullable=false)
     */
    public $status;

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
    public function getSource() {
        return 'partner_sale_item';
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return SalesItem[]
     */
    public static function find($parameters = null) {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return SalesItem
     */
    public static function findFirst($parameters = null) {
        return parent::findFirst($parameters);
    }

}
