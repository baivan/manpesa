<?php

class Ticket extends \Phalcon\Mvc\Model {

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
     * @Column(type="string", length=50, nullable=false)
     */
    public $ticketTitle;

    /**
     *
     * @var string
     * @Column(type="string", length=500, nullable=false)
     */
    public $ticketDescription;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    public $userID;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=true)
     */
    public $contactsID;

    /**
     *
     * @var string
     * @Column(type="string", length=150, nullable=true)
     */
    public $otherOwner;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    public $assigneeID;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=true)
     */
    public $ticketCategoryID;

    /**
     *
     * @var string
     * @Column(type="string", length=150, nullable=true)
     */
    public $otherCategory;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    public $priorityID;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
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
        return 'ticket';
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return Ticket[]
     */
    public static function find($parameters = null) {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return Ticket
     */
    public static function findFirst($parameters = null) {
        return parent::findFirst($parameters);
    }

}
