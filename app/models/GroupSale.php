<?php

class GroupSale extends \Phalcon\Mvc\Model
{

    /**
     *
     * @var integer
     * @Primary
     * @Identity
     * @Column(type="integer", length=11, nullable=false)
     */
    public $groupID;

    /**
     *
     * @var string
     * @Column(type="string", length=20, nullable=false)
     */
    public $groupToken;

    /**
     *
     * @var string
     * @Column(type="string", length=100, nullable=false)
     */
    public $groupName;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    public $numberOfMembers;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    public $status;

    /**
     *
     * @var string
     * @Column(type="string", nullable=true)
     */
    public $expiredAt;

    /**
     *
     * @var string
     * @Column(type="string", nullable=true)
     */
    public $closedAt;

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
        return 'group_sale';
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return GroupSale[]
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return GroupSale
     */
    public static function findFirst($parameters = null)
    {
        return parent::findFirst($parameters);
    }

}
