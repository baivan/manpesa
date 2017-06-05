<?php

class Contacts extends \Phalcon\Mvc\Model
{

    /**
     *
     * @var integer
     * @Primary
     * @Identity
     * @Column(type="integer", length=11, nullable=false)
     */
    public $contactsID;

    /**
     *
     * @var string
     * @Column(type="string", length=50, nullable=true)
     */
    public $homeMobile;

    /**
     *
     * @var string
     * @Column(type="string", length=50, nullable=false)
     */
    public $workMobile;

    /**
     *
     * @var string
     * @Column(type="string", length=100, nullable=true)
     */
    public $homeEmail;

    /**
     *
     * @var string
     * @Column(type="string", length=100, nullable=false)
     */
    public $workEmail;

    /**
     *
     * @var string
     * @Column(type="string", length=100, nullable=false)
     */
    public $passportNumber;

    /**
     *
     * @var string
     * @Column(type="string", length=100, nullable=false)
     */
    public $nationalIdNumber;

    /**
     *
     * @var string
     * @Column(type="string", length=150, nullable=false)
     */
    public $fullName;

    /**
     *
     * @var string
     * @Column(type="string", length=200, nullable=false)
     */
    public $location;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    public $locationID;
    
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
    public function getSource()
    {
        return 'contacts';
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return Contacts[]
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return Contacts
     */
    public static function findFirst($parameters = null)
    {
        return parent::findFirst($parameters);
    }

}
