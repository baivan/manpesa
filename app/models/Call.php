<?php

class Call extends \Phalcon\Mvc\Model {

    /**
     *
     * @var integer
     * @Primary
     * @Identity
     * @Column(type="integer", length=11, nullable=false)
     */
    public $callLogID;

    /**
     *
     * @var string
     * @Column(type="string", length=3, nullable=false)
     */
    public $status;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    public $disposition;

    /**
     *
     * @var integer
     * @Column(type="string", length=11, nullable=true)
     */
    public $callback;

    /**
     *
     * @var integer
     * @Column(type="string", length=200, nullable=false)
     */
    public $previousTool;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    public $promoterID;

    /**
     *
     * @var integer
     * @Column(type="string", length=500, nullable=true)
     */
    public $comment;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    public $customerID;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=true)
     */
    public $ticketID;

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
        return 'call_log';
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
