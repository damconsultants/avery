<?php

namespace DamConsultants\Avery\Model\ResourceModel;

class ApiBynderMediaTable extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    /**
     * AverySyc Data
     *
     * @return $this
     */
    protected function _construct()
    {
        $this->_init('api_response_media_data', 'id');
    }
}
