<?php

class SmsTemplates extends \Phalcon\Mvc\Model
{

    /**
     *
     * @var integer
     * @Primary
     * @Identity
     * @Column(type="integer", length=11, nullable=false)
     */
    public $templateID;

    /**
     *
     * @var string
     * @Column(type="string", length=500, nullable=false)
     */
    public $temptate;

    /**
     *
     * @var string
     * @Column(type="string", length=100, nullable=false)
     */
    public $templateType;

    /**
     *
     * @var string
     * @Column(type="string", length=50, nullable=true)
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
        return 'sms_templates';
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return SmsTemplates[]
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return SmsTemplates
     */
    public static function findFirst($parameters = null)
    {
        return parent::findFirst($parameters);
    }

}
