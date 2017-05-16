<?php

class DelinquencyTier extends \Phalcon\Mvc\Model {

    /**
     *
     * @var integer
     * @Primary
     * @Identity
     * @Column(type="integer", length=11, nullable=false)
     */
    public $tierID;

    /**
     *
     * @var string
     * @Column(type="string", length=150, nullable=false)
     */
    public $tierName;

    /**
     *
     * @var string
     * @Column(type="string", length=500, nullable=true)
     */
    public $tierDescription;

    /**
     *
     * @var string
     * @Column(type="string", length=500, nullable=true)
     */
    public $tierCount;

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
        return 'delinquency_tier';
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return Outbox[]
     */
    public static function find($parameters = null) {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return Outbox
     */
    public static function findFirst($parameters = null) {
        return parent::findFirst($parameters);
    }

}
