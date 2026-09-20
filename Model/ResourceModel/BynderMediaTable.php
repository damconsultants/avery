<?php

namespace DamConsultants\Avery\Model\ResourceModel;

class BynderMediaTable extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    /**
     * AverySyc Data
     *
     * @return $this
     */
    protected function _construct()
    {
        $this->_init('media_data', 'id');
    }
}
