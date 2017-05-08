<?php

class Location extends \Phalcon\Mvc\Model
{

    /**
     *
     * @var integer
     * @Primary
     * @Identity
     * @Column(type="integer", length=11, nullable=false)
     */
    public $locationID;

    /**
     *
     * @var string
     * @Column(type="string", length=50, nullable=false)
     */
    public $country;

    /**
     *
     * @var string
     * @Column(type="string", length=100, nullable=false)
     */
    public $county;

    /**
     *
     * @var string
     * @Column(type="string", length=150, nullable=false)
     */
    public $townCenter;

    /**
     *
     * @var string
     * @Column(type="string", nullable=false)
     */
    public $dateCreated;

    /**
     *
     * @var string
     * @Column(type="string", nullable=false)
     */
    public $dateUpdated;

    /**
     * Returns table name mapped in the model.
     *
     * @return string
     */
    public function getSource()
    {
        return 'location';
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return Location[]
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return Location
     */
    public static function findFirst($parameters = null)
    {
        return parent::findFirst($parameters);
    }

}
