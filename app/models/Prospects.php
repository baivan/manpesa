<?php

class Prospects extends \Phalcon\Mvc\Model
{

    /**
     *
     * @var integer
     * @Primary
     * @Identity
     * @Column(type="integer", length=11, nullable=false)
     */
    public $prospectsID;

    /**
     *
     * @var integer
     * @Column(type="integer", length=3, nullable=false)
     */
    public $status;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    public $userID;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    public $contactsID;
    
    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    public $sourceID;
    
    /**
     *
     * @var string
     * @Column(type="string", length=200, nullable=true)
     */
    public $otherSource;
    
    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=true)
     */
    public $updatedBy;

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
        return 'prospects';
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return Prospects[]
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return Prospects
     */
    public static function findFirst($parameters = null)
    {
        return parent::findFirst($parameters);
    }

}
