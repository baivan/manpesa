<?php

class TicketUpdates extends \Phalcon\Mvc\Model {

    /**
     *
     * @var integer
     * @Primary
     * @Identity
     * @Column(type="integer", length=11, nullable=false)
     */
    public $ticketUpdateID;

    /**
     *
     * @var string
     * @Column(type="string", length=20, nullable=false)
     */
    public $updateMessage;

    /**
     *
     * @var integer
     * @Primary
     * @Identity
     * @Column(type="integer", length=11, nullable=false)
     */
    public $ticketID;

    /**
     *
     * @var string
     * @Column(type="string", nullable=false)
     */
    public $callback;
    
    /**
     *
     * @var integer
     * @Primary
     * @Identity
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
        return 'ticket_updates';
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return Category[]
     */
    public static function find($parameters = null) {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return Category
     */
    public static function findFirst($parameters = null) {
        return parent::findFirst($parameters);
    }

}
