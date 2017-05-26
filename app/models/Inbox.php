<?php

class Inbox extends \Phalcon\Mvc\Model
{

    /**
     *
     * @var integer
     * @Primary
     * @Identity
     * @Column(type="integer", length=11, nullable=false)
     */
    public $inboxID;

    /**
     *
     * @var string
     * @Column(type="string", length=10, nullable=true)
     */
    public $shortCode;

    /**
     *
     * @var string
     * @Column(type="string", length=50, nullable=false)
     */
    public $MSISDN;

    /**
     *
     * @var string
     * @Column(type="string", length=600, nullable=false)
     */
    public $message;

    /**
     *
     * @var string
     * @Column(type="string", nullable=true)
     */
    public $receivedAt;

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
        return 'inbox';
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return Inbox[]
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return Inbox
     */
    public static function findFirst($parameters = null)
    {
        return parent::findFirst($parameters);
    }

}
