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
     * @Column(type="string", length=150, nullable=true)
     */
    public $saleAgentBehavior;

    /**
     *
     * @var string
     * @Column(type="string", length=150, nullable=true)
     */
    public $deliveryExperience;

    /**
     *
     * @var string
     * @Column(type="string", length=150, nullable=true)
     */
    public $referralScheme;

    /**
     *
     * @var string
     * @Column(type="string", length=150, nullable=true)
     */
    public $recommendation;

    /**
     *
     * @var string
     * @Column(type="string", length=150, nullable=true)
     */
    public $overallExperience;

    /**
     *
     * @var string
     * @Column(type="string", length=150, nullable=true)
     */
    public $productExperience;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    public $promoter;

    /**
     *
     * @var string
     * @Column(type="string", length=150, nullable=true)
     */
    public $previousTool;

    /**
     *
     * @var string
     * @Column(type="string", length=150, nullable=true)
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
