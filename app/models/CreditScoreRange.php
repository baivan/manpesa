<?php

class CreditScoreRange extends \Phalcon\Mvc\Model
{

    /**
     *
     * @var integer
     * @Primary
     * @Identity
     * @Column(type="integer", length=11, nullable=false)
     */
    public $creditScoreRangeID;

    /**
     *
     * @var string
     * @Column(type="string", length=10, nullable=false)
     */
    public $upperLimit;

    /**
     *
     * @var string
     * @Column(type="string", length=10, nullable=false)
     */
    public $lowerLimit;

    /**
     *
     * @var string
     * @Column(type="string", length=100, nullable=false)
     */
    public $description;

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
        return 'credit_score_range';
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return CreditScoreRange[]
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return CreditScoreRange
     */
    public static function findFirst($parameters = null)
    {
        return parent::findFirst($parameters);
    }

}
