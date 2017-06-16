<?php

class MetropolReportType extends \Phalcon\Mvc\Model
{

    /**
     *
     * @var integer
     * @Primary
     * @Identity
     * @Column(type="integer", length=11, nullable=false)
     */
    public $metropolReportTypeID;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    public $reportType;

    /**
     *
     * @var string
     * @Column(type="string", length=150, nullable=false)
     */
    public $reportName;

    /**
     *
     * @var string
     * @Column(type="string", length=300, nullable=false)
     */
    public $reportDescription;

    /**
     *
     * @var string
     * @Column(type="string", length=100, nullable=false)
     */
    public $endpoint;

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
        return 'metropol_report_type';
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return MetropolReportType[]
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return MetropolReportType
     */
    public static function findFirst($parameters = null)
    {
        return parent::findFirst($parameters);
    }

}
