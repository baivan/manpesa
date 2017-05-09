<?php

class PromoterScore extends \Phalcon\Mvc\Model {

    /**
     *
     * @var integer
     * @Primary
     * @Identity
     * @Column(type="integer", length=11, nullable=false)
     */
    public $promoterScoreID;

    /**
     *
     * @var string
     * @Column(type="integer", length=11, nullable=false)
     */
    public $scoreCategoryID;

    /**
     *
     * @var integer
     * @Column(type="integer", length=15, nullable=true)
     */
    public $scoreResponse;

    /**
     *
     * @var integer
     * @Column(type="string", length=500, nullable=true)
     */
    public $extra;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    public $customerID;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    public $userID;

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
        return 'promoter_score';
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
